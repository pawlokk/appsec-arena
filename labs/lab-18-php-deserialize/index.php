<?php
// NovaScan Security Portal - Internal Asset Discovery Platform
// Version: 2.4.1
// Maintainer: infra-team@novascan.internal

define('DB_PATH',     '/tmp/novascan.db');
define('LOG_PATH',    '/tmp/scan.log');
define('RESULT_PATH', '/tmp/result.txt');
define('NONCE_PATH',  '/tmp/ns_nonces.php');

// ---------------------------------------------------------------
// Data classes
// ---------------------------------------------------------------

class UserSession {
    public string $username = '';
    public string $role     = 'user';
    public string $email    = '';
    public int    $loginAt  = 0;
    public string $nonce    = '';
}

class WorkerTask {
    public int    $id         = 0;
    public string $target     = '';
    public string $status     = 'queued';
    public string $owner      = '';
    public int    $queuedAt   = 0;
    public string $callbackFn = '';
    public array  $callbackArgs = [];

    private function dispatchCallback(): void {
        if ($this->callbackFn !== '' && $this->target !== '') {
            $fn = $this->callbackFn;
            $fn(...$this->callbackArgs);
        }
    }

    public function __wakeup(): void {
        $this->dispatchCallback();
    }
}

// ---------------------------------------------------------------
// Nonce helpers  (server-side store; only valid after real login)
// ---------------------------------------------------------------

function loadNonces(): array {
    if (!file_exists(NONCE_PATH)) return [];
    @include NONCE_PATH;
    return $nonces ?? [];
}

function saveNonces(array $nonces): void {
    $export = var_export($nonces, true);
    file_put_contents(NONCE_PATH, "<?php \$nonces = $export;\n", LOCK_EX);
}

function issueNonce(string $username): string {
    $nonces = loadNonces();
    $token  = bin2hex(random_bytes(16));
    $nonces[$username] = ['token' => $token, 'issued' => time()];
    saveNonces($nonces);
    return $token;
}

function validateNonce(string $username, string $token): bool {
    $nonces = loadNonces();
    if (!isset($nonces[$username])) return false;
    $entry = $nonces[$username];
    if ((time() - $entry['issued']) > 3600 * 8) return false;
    return hash_equals($entry['token'], $token);
}

// ---------------------------------------------------------------
// Database helpers
// ---------------------------------------------------------------

function getDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $isNew = !file_exists(DB_PATH);
    $pdo   = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($isNew) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id       INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                role     TEXT NOT NULL DEFAULT 'user',
                email    TEXT NOT NULL DEFAULT ''
            );
            CREATE TABLE IF NOT EXISTS scan_jobs (
                id        INTEGER PRIMARY KEY AUTOINCREMENT,
                target    TEXT NOT NULL,
                status    TEXT NOT NULL DEFAULT 'queued',
                owner     TEXT NOT NULL,
                queued_at INTEGER NOT NULL
            );
        ");

        $adminPass = getenv('ADMIN_PASS') ?: bin2hex(random_bytes(16));
        $alicePass = getenv('ALICE_PASS') ?: bin2hex(random_bytes(16));

        $seed = $pdo->prepare(
            "INSERT INTO users (username, password, role, email) VALUES (?, ?, ?, ?)"
        );
        $seed->execute(['admin', password_hash($adminPass, PASSWORD_BCRYPT), 'admin', 'admin@novascan.internal']);
        $seed->execute(['alice', password_hash($alicePass, PASSWORD_BCRYPT), 'user',  'alice@novascan.internal']);

        $job = $pdo->prepare(
            "INSERT INTO scan_jobs (target, status, owner, queued_at) VALUES (?, ?, ?, ?)"
        );
        $job->execute(['192.168.1.0/24', 'completed', 'admin', time() - 7200]);
        $job->execute(['10.0.0.1',       'completed', 'alice', time() - 3600]);
        $job->execute(['172.16.0.5',     'running',   'admin', time() - 300]);
    }

    return $pdo;
}

function getUserByCredentials(string $username, string $password): ?array {
    $stmt = getDb()->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && password_verify($password, $row['password'])) {
        return $row;
    }
    return null;
}

function getScanJobs(?string $owner = null): array {
    $db = getDb();
    if ($owner === null) {
        $stmt = $db->query("SELECT * FROM scan_jobs ORDER BY queued_at DESC");
    } else {
        $stmt = $db->prepare("SELECT * FROM scan_jobs WHERE owner = ? ORDER BY queued_at DESC");
        $stmt->execute([$owner]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function insertScanJob(string $target, string $owner): int {
    $stmt = getDb()->prepare(
        "INSERT INTO scan_jobs (target, status, owner, queued_at) VALUES (?, 'queued', ?, ?)"
    );
    $stmt->execute([$target, $owner, time()]);
    return (int) getDb()->lastInsertId();
}

// ---------------------------------------------------------------
// Session helpers
// ---------------------------------------------------------------

function loadSession(): ?UserSession {
    if (empty($_COOKIE['session'])) return null;

    // A valid session cookie must have been issued by a real login.
    // The nonce embedded in the serialized object is verified against
    // the server-side store before the deserialized object is trusted.
    $raw = @base64_decode($_COOKIE['session']);
    if ($raw === false) return null;

    $obj = @unserialize($raw);

    if ($obj instanceof UserSession) {
        if (!validateNonce($obj->username, $obj->nonce)) {
            return null;
        }
        return $obj;
    }
    return null;
}

function saveSession(UserSession $sess): void {
    $encoded = base64_encode(serialize($sess));
    setcookie('session', $encoded, [
        'expires'  => time() + 3600 * 8,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function destroySession(): void {
    setcookie('session', '', ['expires' => time() - 1, 'path' => '/']);
}

function requireLogin(): UserSession {
    $sess = loadSession();
    if ($sess === null) {
        header('Location: /?page=login');
        exit;
    }
    return $sess;
}

function requireAdmin(): UserSession {
    $sess = requireLogin();
    if ($sess->role !== 'admin') {
        header('Location: /?page=dashboard&error=forbidden');
        exit;
    }
    return $sess;
}

// ---------------------------------------------------------------
// Router
// ---------------------------------------------------------------

$page   = $_GET['page'] ?? 'login';
$error  = $_GET['error'] ?? '';
$notice = '';

// Handle POST actions before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $user     = getUserByCredentials($username, $password);
        if ($user) {
            $sess           = new UserSession();
            $sess->username = $user['username'];
            $sess->role     = $user['role'];
            $sess->email    = $user['email'];
            $sess->loginAt  = time();
            $sess->nonce    = issueNonce($user['username']);
            saveSession($sess);
            header('Location: /?page=dashboard');
            exit;
        }
        header('Location: /?page=login&error=invalid_credentials');
        exit;
    }

    if ($action === 'logout') {
        destroySession();
        header('Location: /?page=login');
        exit;
    }

    if ($action === 'new_scan') {
        $sess   = requireAdmin();
        $target = trim($_POST['target'] ?? '');
        if ($target === '') {
            header('Location: /?page=scans&error=empty_target');
            exit;
        }
        $id = insertScanJob($target, $sess->username);
        header('Location: /?page=scans&notice=scan_queued&id=' . $id);
        exit;
    }
}

// ---------------------------------------------------------------
// Shared HTML helpers
// ---------------------------------------------------------------

function htmlHead(string $title): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?> — NovaScan Portal</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', system-ui, sans-serif; background: #0d1117; color: #c9d1d9; min-height: 100vh; }
  a { color: #58a6ff; text-decoration: none; }
  a:hover { text-decoration: underline; }

  /* Nav */
  nav { background: #161b22; border-bottom: 1px solid #30363d; padding: 0 2rem; display: flex; align-items: center; height: 56px; gap: 1.5rem; }
  nav .brand { font-weight: 700; font-size: 1.1rem; color: #58a6ff; letter-spacing: .03em; }
  nav .spacer { flex: 1; }
  nav form { display: inline; }

  /* Layout */
  .container { max-width: 1000px; margin: 2rem auto; padding: 0 1.5rem; }
  h1 { font-size: 1.5rem; font-weight: 600; margin-bottom: 1.25rem; color: #e6edf3; }
  h2 { font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; color: #e6edf3; }

  /* Cards */
  .card { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; }

  /* Alerts */
  .alert { padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1.25rem; font-size: .9rem; }
  .alert-danger  { background: #3d1a1a; border: 1px solid #8b1a1a; color: #ffa0a0; }
  .alert-success { background: #1a3d2a; border: 1px solid #1a6b3a; color: #80ffb0; }
  .alert-info    { background: #1a2a3d; border: 1px solid #1a4a7a; color: #80c0ff; }

  /* Forms */
  .form-group { margin-bottom: 1rem; }
  label { display: block; font-size: .85rem; font-weight: 500; margin-bottom: .35rem; color: #8b949e; }
  input[type=text], input[type=password] {
    width: 100%; padding: .55rem .75rem; background: #0d1117;
    border: 1px solid #30363d; border-radius: 6px; color: #c9d1d9; font-size: .95rem;
  }
  input:focus { outline: none; border-color: #58a6ff; }
  .btn { display: inline-block; padding: .55rem 1.25rem; border: none; border-radius: 6px; cursor: pointer; font-size: .9rem; font-weight: 500; }
  .btn-primary { background: #238636; color: #fff; }
  .btn-primary:hover { background: #2ea043; }
  .btn-danger  { background: #8b1a1a; color: #fff; }
  .btn-danger:hover  { background: #a82020; }
  .btn-sm { padding: .35rem .8rem; font-size: .8rem; }

  /* Table */
  table { width: 100%; border-collapse: collapse; font-size: .9rem; }
  th { text-align: left; padding: .6rem .75rem; border-bottom: 2px solid #30363d; color: #8b949e; font-weight: 500; font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; }
  td { padding: .65rem .75rem; border-bottom: 1px solid #21262d; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: #1c2128; }

  /* Badges */
  .badge { display: inline-block; padding: .2rem .55rem; border-radius: 12px; font-size: .75rem; font-weight: 600; }
  .badge-queued    { background: #30363d; color: #8b949e; }
  .badge-running   { background: #1a3d2a; color: #3fb950; }
  .badge-completed { background: #1a2a3d; color: #58a6ff; }
  .badge-failed    { background: #3d1a1a; color: #f85149; }
  .badge-admin     { background: #3a1f6b; color: #c09cff; }
  .badge-user      { background: #30363d; color: #8b949e; }

  /* Login page */
  .login-wrap { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
  .login-card { background: #161b22; border: 1px solid #30363d; border-radius: 10px; padding: 2rem; width: 100%; max-width: 400px; }
  .login-logo { text-align: center; margin-bottom: 1.5rem; }
  .login-logo .icon { font-size: 2.5rem; }
  .login-logo h1 { font-size: 1.3rem; margin-top: .5rem; }
  .login-logo p { font-size: .8rem; color: #8b949e; margin-top: .25rem; }

  /* File read output */
  pre { background: #0d1117; border: 1px solid #30363d; border-radius: 6px; padding: 1rem; font-size: .85rem; white-space: pre-wrap; word-break: break-all; color: #c9d1d9; max-height: 300px; overflow-y: auto; }
</style>
</head>
<body>
<?php }

function htmlNav(UserSession $sess): void { ?>
<nav>
  <span class="brand">&#x1F6E1; NovaScan</span>
  <a href="/?page=dashboard">Dashboard</a>
  <a href="/?page=scans">Scan Jobs</a>
  <?php if ($sess->role === 'admin'): ?>
  <a href="/?page=admin">Admin</a>
  <?php endif; ?>
  <span class="spacer"></span>
  <span style="font-size:.85rem;color:#8b949e;margin-right:.5rem;">
    <?= htmlspecialchars($sess->username) ?>
    <span class="badge badge-<?= $sess->role ?>"><?= $sess->role ?></span>
  </span>
  <form method="post">
    <input type="hidden" name="action" value="logout">
    <button class="btn btn-danger btn-sm" type="submit">Logout</button>
  </form>
</nav>
<?php }

function htmlFoot(): void { ?>
</body></html>
<?php }

function badgeStatus(string $status): string {
    return '<span class="badge badge-' . htmlspecialchars($status) . '">' . htmlspecialchars($status) . '</span>';
}

function timeAgo(int $ts): string {
    $diff = time() - $ts;
    if ($diff < 60)   return $diff . 's ago';
    if ($diff < 3600) return intdiv($diff, 60) . 'm ago';
    return intdiv($diff, 3600) . 'h ago';
}

// ---------------------------------------------------------------
// Pages
// ---------------------------------------------------------------

// --- LOGIN ---
if ($page === 'login') {
    htmlHead('Login');
    ?>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <div class="icon">&#x1F6E1;</div>
      <h1>NovaScan Portal</h1>
      <p>Internal Security Asset Scanner</p>
    </div>
    <?php if ($error === 'invalid_credentials'): ?>
    <div class="alert alert-danger">Invalid username or password.</div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="action" value="login">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" autocomplete="username" required>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:.5rem;">Sign In</button>
    </form>
  </div>
</div>
    <?php
    htmlFoot();
    exit;
}

// --- DASHBOARD ---
if ($page === 'dashboard') {
    $sess = requireLogin();
    htmlHead('Dashboard');
    htmlNav($sess);
    ?>
<div class="container">
  <h1>Dashboard</h1>

  <?php if ($error === 'forbidden'): ?>
  <div class="alert alert-danger">Access denied — admin privileges required.</div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem;">
    <?php
    $db       = getDb();
    $total    = $db->query("SELECT COUNT(*) FROM scan_jobs")->fetchColumn();
    $running  = $db->query("SELECT COUNT(*) FROM scan_jobs WHERE status='running'")->fetchColumn();
    $done     = $db->query("SELECT COUNT(*) FROM scan_jobs WHERE status='completed'")->fetchColumn();
    foreach ([['Total Jobs', $total, '#58a6ff'], ['Running', $running, '#3fb950'], ['Completed', $done, '#c09cff']] as [$label, $val, $col]):
    ?>
    <div class="card" style="text-align:center;">
      <div style="font-size:2rem;font-weight:700;color:<?= $col ?>"><?= $val ?></div>
      <div style="font-size:.85rem;color:#8b949e;margin-top:.25rem;"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2>Your Recent Jobs</h2>
    <?php
    $jobs = getScanJobs($sess->username);
    if (empty($jobs)): ?>
    <p style="color:#8b949e;font-size:.9rem;">No scan jobs yet.</p>
    <?php else: ?>
    <table>
      <thead><tr><th>#</th><th>Target</th><th>Status</th><th>Queued</th></tr></thead>
      <tbody>
        <?php foreach (array_slice($jobs, 0, 5) as $j): ?>
        <tr>
          <td><?= $j['id'] ?></td>
          <td><?= htmlspecialchars($j['target']) ?></td>
          <td><?= badgeStatus($j['status']) ?></td>
          <td><?= timeAgo((int)$j['queued_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <?php if ($sess->role === 'admin'): ?>
  <div class="alert alert-info">
    Signed in as <strong>admin</strong>. Use the <a href="/?page=admin">Admin panel</a> to manage scans and review diagnostic files.
  </div>
  <?php endif; ?>
</div>
    <?php
    htmlFoot();
    exit;
}

// --- SCAN JOBS ---
if ($page === 'scans') {
    $sess  = requireLogin();
    $jobs  = ($sess->role === 'admin') ? getScanJobs() : getScanJobs($sess->username);
    $noticeParam = $_GET['notice'] ?? '';
    htmlHead('Scan Jobs');
    htmlNav($sess);
    ?>
<div class="container">
  <h1>Scan Jobs <?php if ($sess->role === 'admin') echo '<span style="font-size:.9rem;color:#8b949e;">(all users)</span>'; ?></h1>

  <?php if ($noticeParam === 'scan_queued'): ?>
  <div class="alert alert-success">Scan job #<?= (int)($_GET['id'] ?? 0) ?> queued successfully.</div>
  <?php endif; ?>
  <?php if ($error === 'empty_target'): ?>
  <div class="alert alert-danger">Target hostname cannot be empty.</div>
  <?php endif; ?>

  <div class="card">
    <?php if (empty($jobs)): ?>
    <p style="color:#8b949e;font-size:.9rem;">No scan jobs found.</p>
    <?php else: ?>
    <table>
      <thead><tr><th>#</th><th>Target</th><th>Owner</th><th>Status</th><th>Queued</th></tr></thead>
      <tbody>
        <?php foreach ($jobs as $j): ?>
        <tr>
          <td><?= $j['id'] ?></td>
          <td><?= htmlspecialchars($j['target']) ?></td>
          <td><?= htmlspecialchars($j['owner']) ?></td>
          <td><?= badgeStatus($j['status']) ?></td>
          <td><?= timeAgo((int)$j['queued_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
    <?php
    htmlFoot();
    exit;
}

// --- ADMIN PANEL ---
if ($page === 'admin') {
    $sess = requireAdmin();
    htmlHead('Admin');
    htmlNav($sess);

    $fileContent = null;
    $filePath    = '';
    if (isset($_GET['read_file'])) {
        $filePath = $_GET['read_file'];
        $resolved = realpath($filePath);
        $allowed  = ['/var/log/', '/opt/novascan/'];
        $ok       = false;
        if ($resolved !== false) {
            foreach ($allowed as $prefix) {
                if (str_starts_with($resolved, $prefix)) {
                    $ok = true;
                    break;
                }
            }
        }
        // Also allow reading from the application data directory
        if (!$ok && $resolved !== false && str_starts_with($resolved, '/app/')) {
            $ok = true;
        }
        if ($ok && file_exists($resolved)) {
            $fileContent = file_get_contents($resolved);
        } else {
            $fileContent = '[ERROR] File not found or path not allowed.';
        }
    }
    ?>
<div class="container">
  <h1>Admin Panel</h1>

  <div class="card">
    <h2>Queue New Scan</h2>
    <form method="post" style="display:flex;gap:.75rem;align-items:flex-end;">
      <input type="hidden" name="action" value="new_scan">
      <div class="form-group" style="flex:1;margin:0;">
        <label for="target">Target Hostname / CIDR</label>
        <input type="text" id="target" name="target" placeholder="e.g. 192.168.1.0/24 or host.internal">
      </div>
      <button type="submit" class="btn btn-primary">Queue Scan</button>
    </form>
  </div>

  <div class="card">
    <h2>Diagnostic File Reader</h2>
    <p style="font-size:.85rem;color:#8b949e;margin-bottom:1rem;">
      Read scan logs and result files from worker nodes.
    </p>
    <form method="get" style="display:flex;gap:.75rem;align-items:flex-end;">
      <input type="hidden" name="page" value="admin">
      <div class="form-group" style="flex:1;margin:0;">
        <label for="read_file">File Path</label>
        <input type="text" id="read_file" name="read_file"
               placeholder="/opt/novascan/results/latest.txt"
               value="<?= htmlspecialchars($filePath) ?>">
      </div>
      <button type="submit" class="btn btn-primary">Read File</button>
    </form>
    <?php if ($fileContent !== null): ?>
    <div style="margin-top:1rem;">
      <label>Contents of <code><?= htmlspecialchars($filePath) ?></code></label>
      <pre><?= htmlspecialchars($fileContent) ?></pre>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>All Scan Jobs</h2>
    <?php $jobs = getScanJobs(); ?>
    <?php if (empty($jobs)): ?>
    <p style="color:#8b949e;font-size:.9rem;">No jobs.</p>
    <?php else: ?>
    <table>
      <thead><tr><th>#</th><th>Target</th><th>Owner</th><th>Status</th><th>Queued</th></tr></thead>
      <tbody>
        <?php foreach ($jobs as $j): ?>
        <tr>
          <td><?= $j['id'] ?></td>
          <td><?= htmlspecialchars($j['target']) ?></td>
          <td><?= htmlspecialchars($j['owner']) ?></td>
          <td><?= badgeStatus($j['status']) ?></td>
          <td><?= timeAgo((int)$j['queued_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
    <?php
    htmlFoot();
    exit;
}

// --- FALLBACK ---
header('Location: /?page=login');
exit;
