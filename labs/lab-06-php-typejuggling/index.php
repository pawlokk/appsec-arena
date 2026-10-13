<?php
ini_set('session.save_path', '/tmp');
session_start();

function getDb() {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:/tmp/parity.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT DEFAULT 'user'
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            body TEXT NOT NULL,
            created_at TEXT NOT NULL
        )");
        $stmt = $db->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $adminHash = getenv('ADMIN_HASH');
            if ($adminHash === false || $adminHash === '') {
                die('Configuration error: ADMIN_HASH environment variable is not set.');
            }
            $users = [
                ['admin', $adminHash, 'admin'],
                ['alice', md5('alice2024'), 'user'],
                ['bob',   md5('bob2024'),   'user'],
            ];
            $ins = $db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
            foreach ($users as $u) {
                $ins->execute($u);
            }
            $sampleNotes = [
                [2, 'Meeting notes', "Q3 planning sync — action items assigned to team.", date('Y-m-d H:i:s')],
                [2, 'Reminder',      "Update SSH keys before end of month.",              date('Y-m-d H:i:s', strtotime('-2 days'))],
                [3, 'Ideas',         "New dashboard layout mockup ready for review.",     date('Y-m-d H:i:s', strtotime('-1 day'))],
            ];
            $ins2 = $db->prepare("INSERT INTO notes (user_id, title, body, created_at) VALUES (?, ?, ?, ?)");
            foreach ($sampleNotes as $n) {
                $ins2->execute($n);
            }
        }
    }
    return $db;
}

function isLoggedIn() {
    return !empty($_SESSION['user']);
}

function isAdmin() {
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
}

function currentUserId() {
    return $_SESSION['uid'] ?? 0;
}

function redirect($path) {
    header("Location: $path");
    exit;
}

function htmlPage($title, $body, $nav = '') {
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Parity — {$title}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #0d0f14; color: #c9d1e0; font-family: 'Segoe UI', sans-serif; min-height: 100vh; }
  a { color: #5c8aff; text-decoration: none; }
  a:hover { text-decoration: underline; }
  .container { max-width: 760px; margin: 60px auto; padding: 0 20px; }
  .card { background: #13161e; border: 1px solid #1e2330; border-radius: 8px; padding: 32px; margin-bottom: 24px; }
  h1 { font-size: 1.5rem; margin-bottom: 24px; color: #e8ecf4; }
  h2 { font-size: 1.15rem; margin-bottom: 16px; color: #e8ecf4; }
  h3 { font-size: 1rem; margin-bottom: 10px; color: #c9d1e0; }
  label { display: block; font-size: 0.85rem; color: #8891a5; margin-bottom: 6px; }
  input[type=text], input[type=password], textarea, select {
    width: 100%; background: #0d0f14; border: 1px solid #1e2330;
    border-radius: 4px; padding: 10px 12px; color: #c9d1e0; font-size: 0.95rem;
  }
  input:focus, textarea:focus, select:focus { outline: none; border-color: #5c8aff; }
  .form-group { margin-bottom: 18px; }
  button, .btn {
    background: #5c8aff; color: #fff; border: none; border-radius: 4px;
    padding: 10px 22px; font-size: 0.95rem; cursor: pointer;
  }
  button:hover, .btn:hover { background: #4a76f0; }
  .btn-sm { padding: 6px 14px; font-size: 0.85rem; }
  .btn-danger { background: #c0392b; }
  .btn-danger:hover { background: #a93226; }
  .alert { background: #1e2330; border-left: 3px solid #e05c5c; padding: 12px 16px;
           border-radius: 4px; margin-bottom: 20px; font-size: 0.9rem; color: #e05c5c; }
  .success { border-color: #5ce07a; color: #5ce07a; }
  .info { border-color: #5c8aff; color: #5c8aff; }
  .nav { display: flex; gap: 16px; margin-bottom: 28px; font-size: 0.9rem; align-items: center; }
  .nav .spacer { flex: 1; }
  .flag-box { background: #0d0f14; border: 1px dashed #5c8aff; border-radius: 4px;
              padding: 14px 18px; font-family: monospace; color: #5ce07a; word-break: break-all; }
  .note-item { border: 1px solid #1e2330; border-radius: 6px; padding: 16px; margin-bottom: 14px; background: #0d0f14; }
  .note-meta { font-size: 0.78rem; color: #555e72; margin-top: 8px; }
  .note-body { white-space: pre-wrap; word-break: break-all; font-size: 0.9rem; margin-top: 6px; }
  textarea { resize: vertical; min-height: 100px; }
  .diag-output { background: #0d0f14; border: 1px solid #1e2330; border-radius: 4px;
                 padding: 14px 18px; font-family: monospace; font-size: 0.85rem;
                 white-space: pre-wrap; word-break: break-all; color: #c9d1e0; max-height: 400px; overflow-y: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
  th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #1e2330; }
  th { color: #8891a5; font-weight: 600; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; }
  .badge-admin { background: #2a1e40; color: #b08cff; }
  .badge-user  { background: #1a2535; color: #5c8aff; }
</style>
</head>
<body>
<div class="container">
{$nav}
{$body}
</div>
</body>
</html>
HTML;
}

function navBar() {
    $adminLinks = '';
    if (isAdmin()) {
        $adminLinks = '<a href="/admin/users">Users</a><a href="/admin/diagnostics">Diagnostics</a>';
    }
    $user = htmlspecialchars($_SESSION['user'] ?? '');
    return <<<HTML
<div class="nav">
  <a href="/dashboard"><strong>Parity</strong></a>
  <a href="/notes">Notes</a>
  {$adminLinks}
  <span class="spacer"></span>
  <span style="color:#8891a5;font-size:0.85rem;">{$user}</span>
  <a href="/logout">Logout</a>
</div>
HTML;
}

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// --- LOGIN ---
if ($uri === '/' && $method === 'GET') {
    if (isLoggedIn()) redirect('/dashboard');
    echo htmlPage('Login', <<<HTML
<div class="card">
  <h1>Parity</h1>
  <p style="color:#8891a5;font-size:0.85rem;margin-bottom:20px;">Collaborative workspace for your team.</p>
  <form method="POST" action="/login">
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" autocomplete="off" required>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" required>
    </div>
    <button type="submit">Sign in</button>
  </form>
</div>
HTML);
    exit;
}

// --- LOGIN HANDLER ---
if ($uri === '/login' && $method === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $db   = getDb();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $loginOk = false;
    if ($user) {
        $submitted = md5($password);
        if ($submitted == $user['password_hash']) {
            $loginOk = true;
        }
    }

    if ($loginOk) {
        $_SESSION['user'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['uid']  = (int)$user['id'];
        redirect('/dashboard');
    }

    echo htmlPage('Login', <<<HTML
<div class="card">
  <h1>Parity</h1>
  <p style="color:#8891a5;font-size:0.85rem;margin-bottom:20px;">Collaborative workspace for your team.</p>
  <div class="alert">Invalid username or password.</div>
  <form method="POST" action="/login">
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" autocomplete="off" required>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" required>
    </div>
    <button type="submit">Sign in</button>
  </form>
</div>
HTML);
    exit;
}

// --- REQUIRE AUTH BELOW ---
if (!isLoggedIn()) {
    redirect('/');
}

// --- DASHBOARD ---
if ($uri === '/dashboard' && $method === 'GET') {
    $db      = getDb();
    $uid     = currentUserId();
    $uname   = htmlspecialchars($_SESSION['user']);
    $role    = $_SESSION['role'] ?? 'user';
    $badgeClass = $role === 'admin' ? 'badge-admin' : 'badge-user';

    $stmt = $db->prepare("SELECT id, title, created_at FROM notes WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$uid]);
    $recentNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $noteRows = '';
    foreach ($recentNotes as $n) {
        $t = htmlspecialchars($n['title']);
        $d = htmlspecialchars($n['created_at']);
        $id = (int)$n['id'];
        $noteRows .= "<tr><td><a href=\"/notes/{$id}\">{$t}</a></td><td style=\"color:#555e72;font-size:0.85rem;\">{$d}</td></tr>\n";
    }
    if (!$noteRows) {
        $noteRows = '<tr><td colspan="2" style="color:#555e72;">No notes yet.</td></tr>';
    }

    $adminPanel = '';
    if ($role === 'admin') {
        $flag = trim(@file_get_contents('/flag_local.txt') ?: '(flag unavailable)');
        $flag = htmlspecialchars($flag);
        $adminPanel = <<<HTML
<div class="card">
  <h2>Administration</h2>
  <p style="color:#8891a5;font-size:0.85rem;margin-bottom:16px;">System credential: <span class="flag-box" style="display:inline;padding:4px 10px;">{$flag}</span></p>
  <div style="display:flex;gap:12px;flex-wrap:wrap;">
    <a href="/admin/users" class="btn btn-sm">Manage Users</a>
    <a href="/admin/diagnostics" class="btn btn-sm">Diagnostics</a>
  </div>
</div>
HTML;
    }

    echo htmlPage('Dashboard', <<<HTML
{$adminPanel}
<div class="card">
  <h1>Welcome back, {$uname} <span class="badge {$badgeClass}">{$role}</span></h1>
  <h2 style="margin-top:20px;">Recent Notes</h2>
  <table>
    <thead><tr><th>Title</th><th>Date</th></tr></thead>
    <tbody>{$noteRows}</tbody>
  </table>
  <div style="margin-top:16px;"><a href="/notes/new" class="btn btn-sm">New Note</a></div>
</div>
HTML, navBar());
    exit;
}

// --- NOTES LIST ---
if ($uri === '/notes' && $method === 'GET') {
    $db  = getDb();
    $uid = currentUserId();
    $stmt = $db->prepare("SELECT id, title, created_at FROM notes WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$uid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = '';
    foreach ($rows as $n) {
        $t  = htmlspecialchars($n['title']);
        $d  = htmlspecialchars($n['created_at']);
        $id = (int)$n['id'];
        $items .= <<<HTML
<div class="note-item">
  <h3><a href="/notes/{$id}">{$t}</a></h3>
  <div class="note-meta">{$d}</div>
</div>
HTML;
    }
    if (!$items) {
        $items = '<p style="color:#555e72;">You have no notes. <a href="/notes/new">Create one</a>.</p>';
    }

    echo htmlPage('Notes', <<<HTML
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1 style="margin-bottom:0;">Notes</h1>
    <a href="/notes/new" class="btn btn-sm">New Note</a>
  </div>
  {$items}
</div>
HTML, navBar());
    exit;
}

// --- NEW NOTE FORM ---
if ($uri === '/notes/new' && $method === 'GET') {
    echo htmlPage('New Note', <<<HTML
<div class="card">
  <h1>New Note</h1>
  <form method="POST" action="/notes/new">
    <div class="form-group">
      <label>Title</label>
      <input type="text" name="title" required>
    </div>
    <div class="form-group">
      <label>Content</label>
      <textarea name="body" required></textarea>
    </div>
    <button type="submit">Save</button>
    <a href="/notes" class="btn btn-sm" style="margin-left:10px;background:#1e2330;color:#c9d1e0;">Cancel</a>
  </form>
</div>
HTML, navBar());
    exit;
}

// --- NEW NOTE SAVE ---
if ($uri === '/notes/new' && $method === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body  = trim($_POST['body']  ?? '');
    if ($title === '' || $body === '') {
        redirect('/notes/new');
    }
    $db  = getDb();
    $uid = currentUserId();
    $stmt = $db->prepare("INSERT INTO notes (user_id, title, body, created_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$uid, $title, $body, date('Y-m-d H:i:s')]);
    redirect('/notes');
}

// --- VIEW NOTE ---
if (preg_match('#^/notes/(\d+)$#', $uri, $m) && $method === 'GET') {
    $id  = (int)$m[1];
    $uid = currentUserId();
    $db  = getDb();
    $stmt = $db->prepare("SELECT * FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $uid]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$note) {
        http_response_code(404);
        echo htmlPage('Not Found', '<div class="card"><p>Note not found.</p></div>', navBar());
        exit;
    }
    $title = htmlspecialchars($note['title']);
    $body  = htmlspecialchars($note['body']);
    $date  = htmlspecialchars($note['created_at']);
    echo htmlPage($title, <<<HTML
<div class="card">
  <h1>{$title}</h1>
  <div class="note-meta" style="margin-bottom:16px;">{$date}</div>
  <div class="note-body">{$body}</div>
  <div style="margin-top:20px;">
    <a href="/notes" class="btn btn-sm" style="background:#1e2330;color:#c9d1e0;">Back</a>
  </div>
</div>
HTML, navBar());
    exit;
}

// --- DELETE NOTE ---
if (preg_match('#^/notes/(\d+)/delete$#', $uri, $m) && $method === 'POST') {
    $id  = (int)$m[1];
    $uid = currentUserId();
    $db  = getDb();
    $db->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?")->execute([$id, $uid]);
    redirect('/notes');
}

// --- ADMIN: USER LIST ---
if ($uri === '/admin/users' && $method === 'GET') {
    if (!isAdmin()) { http_response_code(403); echo htmlPage('Forbidden', '<div class="card"><p>Access denied.</p></div>', navBar()); exit; }
    $db   = getDb();
    $rows = $db->query("SELECT id, username, role FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $trows = '';
    foreach ($rows as $u) {
        $un = htmlspecialchars($u['username']);
        $r  = htmlspecialchars($u['role']);
        $bc = $r === 'admin' ? 'badge-admin' : 'badge-user';
        $trows .= "<tr><td>{$u['id']}</td><td>{$un}</td><td><span class=\"badge {$bc}\">{$r}</span></td></tr>\n";
    }
    echo htmlPage('Users', <<<HTML
<div class="card">
  <h1>User Management</h1>
  <table>
    <thead><tr><th>ID</th><th>Username</th><th>Role</th></tr></thead>
    <tbody>{$trows}</tbody>
  </table>
</div>
HTML, navBar());
    exit;
}

// --- ADMIN: DIAGNOSTICS ---
if ($uri === '/admin/diagnostics' && $method === 'GET') {
    if (!isAdmin()) { http_response_code(403); echo htmlPage('Forbidden', '<div class="card"><p>Access denied.</p></div>', navBar()); exit; }

    $logDir   = '/var/log/parity';
    $logFiles = ['app.log', 'access.log', 'error.log'];

    $output  = '';
    $alert   = '';
    $reqFile = $_GET['log'] ?? '';

    if ($reqFile !== '') {
        $target = $logDir . '/' . $reqFile;
        if (@is_readable($target)) {
            $raw    = file_get_contents($target);
            $output = htmlspecialchars($raw !== false ? $raw : '(empty)');
        } else {
            $output = htmlspecialchars(@file_get_contents($target) ?: '(file not readable or empty)');
            if ($output === '(file not readable or empty)') {
                $alert = '<div class="alert">Could not read file: ' . htmlspecialchars($reqFile) . '</div>';
            }
        }
    }

    $fileOptions = '';
    foreach ($logFiles as $lf) {
        $sel = ($reqFile === $lf) ? ' selected' : '';
        $fileOptions .= "<option value=\"{$lf}\"{$sel}>" . htmlspecialchars($lf) . "</option>\n";
    }

    $outputSection = $output !== '' ? "<h2 style=\"margin-top:24px;\">Output</h2><div class=\"diag-output\">{$output}</div>" : '';

    echo htmlPage('Diagnostics', <<<HTML
<div class="card">
  <h1>System Diagnostics</h1>
  <p style="color:#8891a5;font-size:0.85rem;margin-bottom:20px;">View application log files for troubleshooting.</p>
  {$alert}
  <form method="GET" action="/admin/diagnostics">
    <div class="form-group">
      <label>Log file</label>
      <select name="log">
        <option value="">— select —</option>
        {$fileOptions}
      </select>
    </div>
    <div class="form-group">
      <label>Or enter filename directly</label>
      <input type="text" name="log" value="{$reqFile}" placeholder="e.g. app.log">
    </div>
    <button type="submit">Read</button>
  </form>
  {$outputSection}
</div>
HTML, navBar());
    exit;
}

// --- LOGOUT ---
if ($uri === '/logout') {
    session_destroy();
    redirect('/');
}

http_response_code(404);
echo htmlPage('Not Found', '<div class="card"><p>Page not found.</p></div>', navBar());
