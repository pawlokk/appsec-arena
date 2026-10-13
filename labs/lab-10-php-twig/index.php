<?php
require_once __DIR__ . '/vendor/autoload.php';

use Twig\Environment;
use Twig\Loader\ArrayLoader;

session_start();

function getDB(): PDO {
    $db = new PDO('sqlite:/tmp/relay.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $db;
}

function initDB(): void {
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'user',
        bio TEXT DEFAULT ''
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sender TEXT NOT NULL,
        recipient TEXT NOT NULL,
        subject TEXT NOT NULL,
        body TEXT NOT NULL,
        sent_at TEXT NOT NULL DEFAULT (datetime('now')),
        status TEXT NOT NULL DEFAULT 'queued'
    )");

    $adminPass = md5(getenv('ADMIN_PASS') ?: bin2hex(random_bytes(16)));
    $alicePass = md5(getenv('ALICE_PASS') ?: bin2hex(random_bytes(16)));

    $stmt = $db->prepare("INSERT OR IGNORE INTO users (username, password, role, bio) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', $adminPass, 'admin', 'Platform administrator']);
    $stmt->execute(['alice', $alicePass, 'user', 'Relay operator']);
    $stmt->execute(['bob', md5(bin2hex(random_bytes(16))), 'user', 'DevOps integration account']);

    $check = $db->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
    if ((int)$check === 0) {
        $seed = $db->prepare("INSERT INTO notifications (sender, recipient, subject, body, sent_at, status) VALUES (?, ?, ?, ?, ?, ?)");
        $seed->execute(['system', 'alice', 'Welcome to Relay', 'Your account has been provisioned. Please review the onboarding guide.', '2024-11-01 09:00:00', 'delivered']);
        $seed->execute(['alice', 'bob', 'Integration credentials', 'Updated API keys have been rotated. See the vault entry relay/prod.', '2024-11-03 14:22:10', 'delivered']);
        $seed->execute(['bob', 'admin', 'CI pipeline alert', 'Build #441 failed on staging. Deployment rolled back automatically.', '2024-11-04 08:47:55', 'delivered']);
    }
}

initDB();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

function requireLogin(): void {
    if (empty($_SESSION['user'])) {
        header('Location: /');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo renderPage('Access Denied', '<p style="color:#ff5c5c">Admin access required.</p>');
        exit;
    }
}

function renderPage(string $title, string $body): string {
    $nav = '';
    if (!empty($_SESSION['user'])) {
        $u = htmlspecialchars($_SESSION['user']['username']);
        $nav = '<nav style="padding:12px 24px;background:#161922;border-bottom:1px solid #222">
            <a href="/dashboard" style="color:#5c8aff;text-decoration:none;margin-right:16px">Dashboard</a>';
        if ($_SESSION['user']['role'] === 'admin') {
            $nav .= '<a href="/admin/template" style="color:#5c8aff;text-decoration:none;margin-right:16px">Templates</a>';
        }
        $nav .= '<a href="/logout" style="color:#888;text-decoration:none;float:right">Logout ('.$u.')</a>
        </nav>';
    }
    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<title>Relay — '.$title.'</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:#0d0f14;color:#c9d1e0;font-family:system-ui,sans-serif;min-height:100vh}
  .container{max-width:640px;margin:60px auto;padding:0 24px}
  h1{color:#e2e8f0;margin-bottom:24px;font-size:1.6rem}
  h2{color:#e2e8f0;margin-bottom:16px;font-size:1.2rem}
  input,textarea{width:100%;padding:10px 12px;background:#161922;border:1px solid #2a2f3e;
    color:#c9d1e0;border-radius:6px;margin-bottom:12px;font-size:0.95rem}
  input:focus,textarea:focus{outline:none;border-color:#5c8aff}
  button,input[type=submit]{background:#5c8aff;color:#fff;border:none;padding:10px 20px;
    border-radius:6px;cursor:pointer;font-size:0.95rem;width:auto}
  button:hover,input[type=submit]:hover{background:#4a78ff}
  .card{background:#161922;border:1px solid #222;border-radius:8px;padding:24px;margin-bottom:20px}
  .error{color:#ff5c5c;margin-bottom:12px;font-size:0.9rem}
  label{display:block;margin-bottom:4px;color:#8892a4;font-size:0.85rem}
  .result{background:#0d1520;border:1px solid #1e3a5f;border-radius:6px;padding:16px;
    font-family:monospace;font-size:0.9rem;white-space:pre-wrap;margin-top:16px;color:#7eb8ff}
  table{width:100%;border-collapse:collapse;font-size:0.88rem}
  th{text-align:left;padding:8px 10px;color:#8892a4;border-bottom:1px solid #2a2f3e;font-weight:500}
  td{padding:8px 10px;border-bottom:1px solid #1a1f2e;vertical-align:top}
  .badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:0.78rem}
  .badge-ok{background:#0e2a1a;color:#4caf7d}
  .badge-q{background:#1a1f2e;color:#8892a4}
</style></head><body>'.$nav.'
<div class="container">'.$body.'</div></body></html>';
}

function loginForm(string $errorHtml = ''): string {
    return '
<h1>Relay</h1>
<p style="color:#8892a4;margin-bottom:24px">Internal notification relay platform</p>
<div class="card">
  <h2>Sign In</h2>
  '.$errorHtml.'
  <form method="POST" action="/login">
    <label>Username</label>
    <input type="text" name="username" required autocomplete="off">
    <label>Password</label>
    <input type="password" name="password" required>
    <input type="submit" value="Login">
  </form>
</div>';
}

// Builds and executes the credential lookup query.
// Returns the user row on success, false otherwise.
function lookupUser(PDO $db, string $username, string $passHash) {
    $query  = "SELECT * FROM users WHERE username='" . $username . "'";
    $query .= " AND password='" . $passHash . "'";
    try {
        $result = $db->query($query);
        return $result ? $result->fetch(PDO::FETCH_ASSOC) : false;
    } catch (Exception $e) {
        return false;
    }
}

if ($uri === '/' && $method === 'GET') {
    echo renderPage('Login', loginForm());
    exit;
}

if ($uri === '/login' && $method === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $passHash = md5($password);

    $db   = getDB();
    $user = lookupUser($db, $username, $passHash);

    if ($user) {
        $_SESSION['user'] = [
            'id'       => $user['id'],
            'username' => $user['username'],
            'role'     => $user['role'],
        ];
        header('Location: /dashboard');
    } else {
        echo renderPage('Login', loginForm('<p class="error">Invalid credentials.</p>'));
    }
    exit;
}

if ($uri === '/dashboard' && $method === 'GET') {
    requireLogin();
    $u      = $_SESSION['user'];
    $isAdmin = $u['role'] === 'admin';

    $db   = getDB();
    $msgs = $db->prepare("SELECT sender, subject, sent_at, status FROM notifications WHERE recipient = ? ORDER BY sent_at DESC LIMIT 10");
    $msgs->execute([$u['username']]);
    $rows = $msgs->fetchAll(PDO::FETCH_ASSOC);

    $tableRows = '';
    foreach ($rows as $r) {
        $badge = $r['status'] === 'delivered'
            ? '<span class="badge badge-ok">delivered</span>'
            : '<span class="badge badge-q">queued</span>';
        $tableRows .= '<tr>
            <td>'.htmlspecialchars($r['sender']).'</td>
            <td>'.htmlspecialchars($r['subject']).'</td>
            <td style="color:#8892a4">'.htmlspecialchars($r['sent_at']).'</td>
            <td>'.$badge.'</td>
        </tr>';
    }

    $notifSection = '<div class="card" style="margin-top:24px">
  <h2>Inbox</h2>
  <table>
    <thead><tr><th>From</th><th>Subject</th><th>Sent</th><th>Status</th></tr></thead>
    <tbody>'.($tableRows ?: '<tr><td colspan="4" style="color:#8892a4">No messages.</td></tr>').'</tbody>
  </table>
</div>';

    $adminSection = '';
    if ($isAdmin) {
        $adminSection = '<div class="card">
  <h2>Administration</h2>
  <p style="color:#8892a4;margin-bottom:8px">Manage platform resources.</p>
  <p><a href="/admin/template" style="color:#5c8aff">Template Preview Tool &rarr;</a></p>
  <p style="margin-top:8px"><a href="/dashboard/flag" style="color:#5c8aff">Access Control Token &rarr;</a></p>
</div>';
    }

    $username = htmlspecialchars($u['username']);
    $role     = htmlspecialchars($u['role']);

    echo renderPage('Dashboard', '
<h1>Dashboard</h1>
<div class="card">
  <h2>Welcome, '.$username.'</h2>
  <p style="color:#8892a4">Role: '.$role.'</p>
</div>'.$adminSection.$notifSection);
    exit;
}

if ($uri === '/dashboard/flag' && $method === 'GET') {
    requireAdmin();
    $flag = trim(@file_get_contents('/flag_local.txt') ?: '');
    $display = $flag !== '' ? htmlspecialchars($flag) : '[flag not set]';
    echo renderPage('Access Control Token', '
<h1>Access Control Token</h1>
<div class="card">
  <p style="color:#8892a4;margin-bottom:16px">Platform root credential for emergency access.</p>
  <div class="result">'.$display.'</div>
</div>');
    exit;
}

if ($uri === '/admin/template' && $method === 'GET') {
    requireAdmin();
    echo renderPage('Template Preview', '
<h1>Template Preview</h1>
<p style="color:#8892a4;margin-bottom:20px">Render notification templates before deploying them to users.</p>
<div class="card">
  <h2>Preview Template</h2>
  <form method="POST" action="/admin/template">
    <label>Template Source</label>
    <textarea name="template" rows="8" placeholder="Enter template source..."></textarea>
    <br>
    <button type="submit">Render Preview</button>
  </form>
</div>');
    exit;
}

if ($uri === '/admin/template' && $method === 'POST') {
    requireAdmin();

    $templateSrc = $_POST['template'] ?? '';
    $rendered = '';
    $error = '';

    if ($templateSrc !== '') {
        try {
            $loader = new ArrayLoader(['preview' => $templateSrc]);
            $twig = new Environment($loader);
            $rendered = $twig->render('preview', ['name' => 'User', 'platform' => 'Relay']);
        } catch (\Exception $e) {
            $error = 'Template rendering failed.';
        }
    }

    $resultHtml = '';
    if ($error) {
        $resultHtml = '<p class="error">'.$error.'</p>';
    } elseif ($rendered !== '') {
        $resultHtml = '<div class="result">'.htmlspecialchars($rendered).'</div>';
    }

    echo renderPage('Template Preview', '
<h1>Template Preview</h1>
<p style="color:#8892a4;margin-bottom:20px">Render notification templates before deploying them to users.</p>
<div class="card">
  <h2>Preview Template</h2>
  <form method="POST" action="/admin/template">
    <label>Template Source</label>
    <textarea name="template" rows="8">'.htmlspecialchars($templateSrc).'</textarea>
    <br>
    <button type="submit">Render Preview</button>
  </form>
  '.$resultHtml.'
</div>');
    exit;
}

if ($uri === '/logout') {
    session_destroy();
    header('Location: /');
    exit;
}

http_response_code(404);
echo renderPage('Not Found', '<h1>404</h1><p style="color:#8892a4">Page not found.</p>');
