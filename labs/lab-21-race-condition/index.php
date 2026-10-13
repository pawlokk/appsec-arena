<?php
/**
 * CreditVault - Employee Credit & Reward Portal
 * Internal tool for managing employee reward credits and gift code redemptions.
 */

session_start();
require_once 'db.php';

$db     = get_db();
$action = $_GET['action'] ?? 'home';
$error  = '';
$message = '';

// -------------------------------------------------------------------------
// Auth helpers
// -------------------------------------------------------------------------

function current_user(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    $db   = get_db();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /index.php?action=login');
        exit;
    }
}

// -------------------------------------------------------------------------
// Routing
// -------------------------------------------------------------------------

switch ($action) {

    // ------------------------------------------------------------------
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                header('Location: /index.php?action=dashboard');
                exit;
            } else {
                $error = 'Invalid credentials.';
            }
        }
        render_login($error);
        break;

    // ------------------------------------------------------------------
    case 'logout':
        session_destroy();
        header('Location: /index.php?action=login');
        exit;

    // ------------------------------------------------------------------
    case 'dashboard':
        require_login();
        $user = current_user();
        render_dashboard($user, '');
        break;

    // ------------------------------------------------------------------
    case 'redeem':
        require_login();
        $user = current_user();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $code = trim($_POST['code'] ?? '');

            $stmt = $db->prepare("SELECT * FROM gift_codes WHERE code = ?");
            $stmt->execute([$code]);
            $gift = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$gift) {
                $message = 'Unknown gift code.';
            } elseif ($gift['redeemed']) {
                $message = 'This code has already been redeemed.';
            } else {
                // Apply credit balance
                $db->exec(
                    "UPDATE users SET balance = balance + " . (int)$gift['value'] .
                    " WHERE id = " . (int)$user['id']
                );

                usleep(200000);

                // Mark code as used
                $db->exec("UPDATE gift_codes SET redeemed = 1 WHERE code = " . $db->quote($code));

                $message = 'Gift code redeemed! +' . $gift['value'] . ' credits added to your account.';
                $user    = current_user();
            }
        }

        render_dashboard($user, $message);
        break;

    // ------------------------------------------------------------------
    case 'flag':
        require_login();
        $user = current_user();

        if ((int)$user['balance'] < 1000) {
            render_flag_locked($user);
            break;
        }

        // Check whether this user has already purchased flag access
        $stmt = $db->prepare("SELECT id FROM purchases WHERE user_id = ? AND item = 'flag_access'");
        $stmt->execute([(int)$user['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            // Deduct cost and record the purchase
            $db->exec("UPDATE users SET balance = balance - 1000 WHERE id = " . (int)$user['id']);
            $db->prepare("INSERT INTO purchases (user_id, item) VALUES (?, 'flag_access')")
               ->execute([(int)$user['id']]);
            $user = current_user();
        }

        $flag = trim(file_get_contents('/var/www/html/flag_local.txt'));
        render_flag($user, $flag);
        break;

    // ------------------------------------------------------------------
    case 'admin/proof':
        require_login();
        $user = current_user();

        // Premium transfer — requires sufficient account balance.
        if ((int)$user['balance'] < 1000) {
            render_flag_locked($user);
            break;
        }

        // Read the premium transfer token.
        $proof = trim(@file_get_contents('/flag_proof.txt') ?: '');
        if ($proof === '') {
            // Fallback: ask the OS (intentional server-side exec for lab purposes).
            $proof = trim(shell_exec('cat /flag_proof.txt 2>/dev/null') ?? '');
        }
        render_proof($user, $proof);
        break;

    // ------------------------------------------------------------------
    default:
        header('Location: /index.php?action=login');
        exit;
}

// =========================================================================
// Views
// =========================================================================

function html_head(string $title): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?> — CreditVault</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; color: #222; }
  .topbar { background: #1a1a2e; color: #e0e0e0; padding: 14px 32px; display: flex;
            justify-content: space-between; align-items: center; }
  .topbar a { color: #7ecfff; text-decoration: none; font-size: 0.9rem; }
  .topbar .brand { font-size: 1.2rem; font-weight: 700; letter-spacing: 1px; color: #fff; }
  .container { max-width: 860px; margin: 40px auto; padding: 0 20px; }
  .card { background: #fff; border-radius: 10px; padding: 32px; box-shadow: 0 2px 12px rgba(0,0,0,.08); margin-bottom: 24px; }
  h2 { margin-bottom: 20px; color: #1a1a2e; }
  label { display: block; margin-bottom: 6px; font-weight: 600; font-size: .9rem; }
  input[type=text], input[type=password] { width: 100%; padding: 10px 14px; border: 1px solid #ccc;
    border-radius: 6px; font-size: 1rem; margin-bottom: 16px; }
  button, .btn { background: #1a1a2e; color: #fff; border: none; padding: 10px 24px;
    border-radius: 6px; cursor: pointer; font-size: 1rem; text-decoration: none; display: inline-block; }
  button:hover, .btn:hover { background: #16213e; }
  .btn-secondary { background: #555; }
  .btn-danger  { background: #c0392b; }
  .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: .95rem; }
  .alert-error   { background: #fdecea; color: #b71c1c; border: 1px solid #f5c6cb; }
  .alert-success { background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9; }
  .alert-info    { background: #e3f2fd; color: #0d47a1; border: 1px solid #bbdefb; }
  .balance-badge { font-size: 2rem; font-weight: 700; color: #1a1a2e; }
  .credit-label  { font-size: .85rem; color: #888; margin-top: 4px; }
  pre { background: #1e1e2e; color: #cdd6f4; padding: 16px; border-radius: 8px;
        overflow-x: auto; font-size: .9rem; white-space: pre-wrap; }
  table { width: 100%; border-collapse: collapse; }
  td, th { padding: 10px 14px; text-align: left; border-bottom: 1px solid #eee; }
  th { background: #f8f9fa; font-weight: 600; }
</style>
</head>
<body>
<?php }

function html_topbar(?array $user): void { ?>
<div class="topbar">
  <span class="brand">&#128179; CreditVault</span>
  <?php if ($user): ?>
  <span>
    Logged in as <strong><?= htmlspecialchars($user['username']) ?></strong>
    &nbsp;|&nbsp; <a href="/index.php?action=dashboard">Dashboard</a>
    &nbsp;|&nbsp; <a href="/index.php?action=logout">Logout</a>
  </span>
  <?php endif; ?>
</div>
<?php }

function html_foot(): void { ?>
</body></html>
<?php }

// ------------------------------------------------------------------

function render_login(string $error): void {
    html_head('Login');
    html_topbar(null); ?>
<div class="container">
  <div class="card" style="max-width:420px;margin:60px auto;">
    <h2>Sign In</h2>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST" action="/index.php?action=login">
      <label>Username</label>
      <input type="text" name="username" autofocus required>
      <label>Password</label>
      <input type="password" name="password" required>
      <button type="submit">Login</button>
    </form>
    <div class="alert alert-info" style="margin-top:20px;font-size:.85rem;">
      <strong>Demo accounts:</strong> alice / alicepass &nbsp;&nbsp; bob / bobpass
    </div>
  </div>
</div>
<?php html_foot(); }

// ------------------------------------------------------------------

function render_dashboard(array $user, string $message): void {
    html_head('Dashboard');
    html_topbar($user); ?>
<div class="container">
  <div class="card">
    <h2>Welcome, <?= htmlspecialchars($user['username']) ?></h2>
    <div class="balance-badge"><?= (int)$user['balance'] ?></div>
    <div class="credit-label">credits available</div>
  </div>

  <?php if ($message): ?>
  <div class="alert <?= str_starts_with($message, 'Gift code redeemed') ? 'alert-success' : 'alert-error' ?>">
    <?= htmlspecialchars($message) ?>
  </div>
  <?php endif; ?>

  <div class="card">
    <h2>Redeem Gift Code</h2>
    <form method="POST" action="/index.php?action=redeem">
      <label>Gift Code</label>
      <input type="text" name="code" required>
      <button type="submit">Redeem</button>
    </form>
  </div>

  <div class="card">
    <h2>Store</h2>
    <table>
      <tr><th>Item</th><th>Cost</th><th></th></tr>
      <tr>
        <td>&#127937; Premium Access</td>
        <td>1,000 credits</td>
        <td>
          <?php if ((int)$user['balance'] >= 1000): ?>
            <a class="btn" href="/index.php?action=flag">Unlock</a>
          <?php else: ?>
            <span style="color:#999">Need more credits</span>
          <?php endif; ?>
        </td>
      </tr>
    </table>
  </div>
</div>
<?php html_foot(); }

// ------------------------------------------------------------------

function render_flag_locked(array $user): void {
    html_head('Premium Access');
    html_topbar($user); ?>
<div class="container">
  <div class="card">
    <h2>&#128274; Premium Access</h2>
    <div class="alert alert-info">
      You need <strong>1,000 credits</strong> to unlock this content.<br>
      Your current balance: <strong><?= (int)$user['balance'] ?></strong> credits.
    </div>
    <a class="btn btn-secondary" href="/index.php?action=dashboard">Back to Dashboard</a>
  </div>
</div>
<?php html_foot(); }

// ------------------------------------------------------------------

function render_flag(array $user, string $flag): void {
    html_head('Premium Access');
    html_topbar($user); ?>
<div class="container">
  <div class="card">
    <h2>&#127937; Premium Content</h2>
    <div class="alert alert-success">Access granted.</div>
    <pre><?= htmlspecialchars($flag) ?></pre>
    <br>
    <a class="btn" href="/index.php?action=admin/proof">&#128196; Premium Transfer (FLAG_PROOF)</a>
    &nbsp;
    <a class="btn btn-secondary" href="/index.php?action=dashboard">Back to Dashboard</a>
  </div>
</div>
<?php html_foot(); }

// ------------------------------------------------------------------

function render_proof(array $user, string $proof): void {
    html_head('Premium Transfer');
    html_topbar($user); ?>
<div class="container">
  <div class="card">
    <h2>&#128196; Premium Transfer — Proof of Exploit</h2>
    <div class="alert alert-success">Race condition confirmed. Server-side proof retrieved.</div>
    <pre><?= htmlspecialchars($proof) ?></pre>
    <br>
    <a class="btn btn-secondary" href="/index.php?action=flag">Back to Premium Access</a>
  </div>
</div>
<?php html_foot(); }
