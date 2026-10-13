<?php
/**
 * AppSec Arena — lab-02-php: Labyrinth
 * Stack: PHP 8 · SQLite (PDO) · Apache 2
 *
 * Labyrinth — internal document management portal
 */

session_start();

ini_set('log_errors', '0');
ini_set('display_errors', '0');

$db_path = '/tmp/labyrinth.db';

// ── DB init ───────────────────────────────────────────────────────────────────
$db = new PDO("sqlite:$db_path");
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    role TEXT DEFAULT 'user',
    email TEXT DEFAULT '',
    created_at TEXT DEFAULT (datetime('now'))
)");

$db->exec("CREATE TABLE IF NOT EXISTS documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    filename TEXT NOT NULL,
    category TEXT DEFAULT 'general',
    owner_id INTEGER DEFAULT 1
)");

$db->exec("CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event TEXT NOT NULL,
    actor TEXT DEFAULT 'system',
    created_at TEXT DEFAULT (datetime('now'))
)");

// Seed admin — use ADMIN_PASS env var; fall back to a random password so the
// app does not silently break when the env var is absent
$admin_pass = getenv('ADMIN_PASS');
if (!$admin_pass) {
    $admin_pass = bin2hex(random_bytes(16));
}
$admin_hash = password_hash($admin_pass, PASSWORD_BCRYPT);
$db->exec("INSERT OR IGNORE INTO users (id, username, password, role, email) VALUES
    (1, 'admin', " . $db->quote($admin_hash) . ", 'admin', 'admin@labyrinth.internal')");

// Regular staff accounts — passwords are bcrypt hashes; plaintext removed from source
// seeded staff account
$db->exec("INSERT OR IGNORE INTO users (id, username, password, role, email) VALUES
    (2, 'alice', '\$2y\$10\$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lHHy', 'user', 'alice@labyrinth.internal'),
    (3, 'bob',   '\$2y\$10\$VgRy2aJVWsCGAVyO3hCVAuEenVH91.Bnp5epFjkOGLQvA2C7xC.O6', 'user', 'bob@labyrinth.internal')");

// Seed document index
$db->exec("INSERT OR IGNORE INTO documents (id, title, filename, category, owner_id) VALUES
    (1, 'Welcome Guide',  'welcome.txt',    'general', 1),
    (2, 'FAQ',            'faq.txt',        'general', 1),
    (3, 'Changelog',      'changelog.txt',  'general', 1),
    (4, 'IT Onboarding',  'onboarding.txt', 'hr',      1)");

// ── Router ────────────────────────────────────────────────────────────────────
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// ── Login ─────────────────────────────────────────────────────────────────────
if ($path === '/login') {
    if ($method === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        // Authenticate user
        $sql = "SELECT * FROM users WHERE username = '$username'";
        $row = $db->query($sql)->fetch(PDO::FETCH_ASSOC);

        if ($row && password_verify($password, $row['password'])) {
            $_SESSION['user']    = $row['username'];
            $_SESSION['role']    = $row['role'];
            $_SESSION['user_id'] = $row['id'];
            header('Location: /dashboard');
            exit;
        }

        $error = "Invalid credentials";
    }
    ?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Labyrinth — Sign In</title>
<script>window.__ARENA_LAB__ = {
    id: 'lab-02-php', name: 'Labyrinth', level: 2,
    platform: 'http://localhost:8080'
};</script>
<style>
body{font-family:monospace;background:#0d0f14;color:#d4d8e8;display:flex;align-items:center;
     justify-content:center;min-height:100vh;margin:0}
.box{background:#13161e;border:1px solid #1f2535;border-radius:12px;padding:2rem;width:340px}
h2{color:#5c8aff;margin:0 0 1.5rem}
input{width:100%;background:#0a0c11;border:1px solid #1f2535;border-radius:5px;
      padding:8px 10px;color:#d4d8e8;font-family:monospace;margin-bottom:0.75rem;box-sizing:border-box}
button{width:100%;background:#1a2440;color:#5c8aff;border:1px solid #233060;
       border-radius:5px;padding:9px;font-family:monospace;cursor:pointer}
.err{color:#ff5c5c;font-size:0.85rem;margin-bottom:0.75rem}
</style></head>
<body><div class="box">
<h2>Labyrinth</h2>
<?php if (!empty($error)) echo "<p class='err'>" . htmlspecialchars($error) . "</p>"; ?>
<form method="POST" action="/login">
  <input name="username" placeholder="username" autocomplete="off">
  <input name="password" type="password" placeholder="password">
  <button type="submit">Sign in</button>
</form>
</div></body></html>
    <?php exit;
}

// ── Auth gate ─────────────────────────────────────────────────────────────────
if (empty($_SESSION['user'])) {
    header('Location: /login');
    exit;
}

$is_admin = ($_SESSION['role'] === 'admin');

// ── Dashboard ─────────────────────────────────────────────────────────────────
if ($path === '/dashboard' || $path === '/') {
    $docs = $db->query("SELECT id, title, filename, category FROM documents ORDER BY category, title")
               ->fetchAll(PDO::FETCH_ASSOC);
    $by_category = [];
    foreach ($docs as $d) {
        $by_category[$d['category']][] = $d;
    }
    ?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Labyrinth — Dashboard</title>
<script>window.__ARENA_LAB__ = {
    id: 'lab-02-php', name: 'Labyrinth', level: 2,
    platform: 'http://localhost:8080'
};</script>
<style>
body{font-family:monospace;background:#0d0f14;color:#d4d8e8;margin:0;padding:2rem}
h1{color:#5c8aff}
.card{background:#13161e;border:1px solid #1f2535;border-radius:8px;padding:1.5rem;margin:1rem 0}
a{color:#5c8aff}
ul{margin:0.5rem 0;padding-left:1.2rem}
li{margin:0.3rem 0}
.badge{font-size:0.7rem;background:#1a2440;color:#5c8aff;border:1px solid #233060;
       border-radius:3px;padding:1px 5px;margin-left:0.4rem;vertical-align:middle}
nav{display:flex;gap:1.5rem;margin-bottom:1.5rem;border-bottom:1px solid #1f2535;padding-bottom:1rem}
</style></head>
<body>
<h1>Labyrinth</h1>
<nav>
  <a href="/dashboard">Home</a>
  <a href="/profile">Profile</a>
  <a href="/search">Search</a>
  <?php if ($is_admin): ?>
  <a href="/admin/users">Users</a>
  <a href="/admin/reports">Reports</a>
  <?php endif; ?>
  <a href="/logout" style="margin-left:auto">Sign out</a>
</nav>

<p>Signed in as: <strong><?= htmlspecialchars($_SESSION['user']) ?></strong>
   <?php if ($is_admin) echo '<span class="badge">administrator</span>'; ?></p>

<div class="card">
  <h3>Document Library</h3>
  <?php foreach ($by_category as $cat => $items): ?>
    <p><strong><?= htmlspecialchars(ucfirst($cat)) ?></strong></p>
    <ul>
    <?php foreach ($items as $doc): ?>
      <li><a href="/docs/view/<?= urlencode($doc['filename']) ?>"><?= htmlspecialchars($doc['title']) ?></a></li>
    <?php endforeach; ?>
    </ul>
  <?php endforeach; ?>
</div>

<div class="card">
  <h3>Recent Activity</h3>
  <?php
  $events = $db->query("SELECT event, actor, created_at FROM audit_log ORDER BY id DESC LIMIT 5")
               ->fetchAll(PDO::FETCH_ASSOC);
  if ($events): ?>
  <ul>
    <?php foreach ($events as $e): ?>
      <li><?= htmlspecialchars($e['created_at']) ?> — <?= htmlspecialchars($e['event']) ?> (<?= htmlspecialchars($e['actor']) ?>)</li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?>
  <p style="color:#6b7a99">No recent activity.</p>
  <?php endif; ?>
</div>

</body></html>
    <?php exit;
}

// ── Profile ───────────────────────────────────────────────────────────────────
if ($path === '/profile') {
    $stmt = $db->prepare("SELECT id, username, email, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($method === 'POST') {
        $new_email = $_POST['email'] ?? $profile['email'];
        $stmt2 = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt2->execute([trim($new_email), $_SESSION['user_id']]);
        $db->exec("INSERT INTO audit_log (event, actor) VALUES ('profile updated', " .
            $db->quote($_SESSION['user']) . ")");
        header('Location: /profile');
        exit;
    }
    ?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Labyrinth — Profile</title>
<script>window.__ARENA_LAB__ = {
    id: 'lab-02-php', name: 'Labyrinth', level: 2,
    platform: 'http://localhost:8080'
};</script>
<style>
body{font-family:monospace;background:#0d0f14;color:#d4d8e8;margin:0;padding:2rem}
h1{color:#5c8aff}
.card{background:#13161e;border:1px solid #1f2535;border-radius:8px;padding:1.5rem;margin:1rem 0;max-width:480px}
input[type=email]{width:100%;background:#0a0c11;border:1px solid #1f2535;border-radius:5px;
      padding:8px 10px;color:#d4d8e8;font-family:monospace;margin-bottom:0.75rem;box-sizing:border-box}
button{background:#1a2440;color:#5c8aff;border:1px solid #233060;border-radius:5px;
       padding:7px 18px;font-family:monospace;cursor:pointer}
a{color:#5c8aff}
dl{display:grid;grid-template-columns:120px 1fr;gap:0.4rem 1rem}
dt{color:#6b7a99}
</style></head>
<body>
<h1>Profile</h1>
<p><a href="/dashboard">&larr; Dashboard</a></p>
<div class="card">
  <dl>
    <dt>Username</dt><dd><?= htmlspecialchars($profile['username']) ?></dd>
    <dt>Role</dt><dd><?= htmlspecialchars($profile['role']) ?></dd>
    <dt>Joined</dt><dd><?= htmlspecialchars($profile['created_at']) ?></dd>
  </dl>
  <hr style="border-color:#1f2535;margin:1rem 0">
  <form method="POST">
    <label>Email address<br>
      <input type="email" name="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>">
    </label>
    <button type="submit">Save</button>
  </form>
</div>
</body></html>
    <?php exit;
}

// ── Search ────────────────────────────────────────────────────────────────────
if ($path === '/search') {
    $q       = $_GET['q'] ?? '';
    $results = [];
    if ($q !== '') {
        $stmt = $db->prepare("SELECT id, title, filename, category FROM documents
                              WHERE title LIKE ? OR category LIKE ?");
        $stmt->execute(["%$q%", "%$q%"]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    ?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Labyrinth — Search</title>
<script>window.__ARENA_LAB__ = {
    id: 'lab-02-php', name: 'Labyrinth', level: 2,
    platform: 'http://localhost:8080'
};</script>
<style>
body{font-family:monospace;background:#0d0f14;color:#d4d8e8;margin:0;padding:2rem}
h1{color:#5c8aff}
.card{background:#13161e;border:1px solid #1f2535;border-radius:8px;padding:1.5rem;margin:1rem 0}
input{background:#0a0c11;border:1px solid #1f2535;border-radius:5px;
      padding:8px 10px;color:#d4d8e8;font-family:monospace;width:280px}
button{background:#1a2440;color:#5c8aff;border:1px solid #233060;border-radius:5px;
       padding:8px 14px;font-family:monospace;cursor:pointer;margin-left:0.5rem}
a{color:#5c8aff}
ul{margin:0.5rem 0;padding-left:1.2rem}
li{margin:0.3rem 0}
</style></head>
<body>
<h1>Search Documents</h1>
<p><a href="/dashboard">&larr; Dashboard</a></p>
<form method="GET">
  <input name="q" placeholder="keyword..." value="<?= htmlspecialchars($q) ?>">
  <button type="submit">Search</button>
</form>
<?php if ($q !== ''): ?>
<div class="card">
  <h3>Results for &ldquo;<?= htmlspecialchars($q) ?>&rdquo;</h3>
  <?php if ($results): ?>
  <ul>
    <?php foreach ($results as $doc): ?>
      <li><a href="/docs/view/<?= urlencode($doc['filename']) ?>"><?= htmlspecialchars($doc['title']) ?></a>
          <span style="color:#6b7a99">(<?= htmlspecialchars($doc['category']) ?>)</span></li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?>
  <p style="color:#6b7a99">No documents matched.</p>
  <?php endif; ?>
</div>
<?php endif; ?>
</body></html>
    <?php exit;
}

// ── Public document viewer ────────────────────────────────────────────────────
// Serves files from /var/www/html/docs only; path traversal is blocked
if (preg_match('#^/docs/view/(.+)$#', $path, $m)) {
    $requested = $m[1];
    $docs_dir  = realpath('/var/www/html/docs');
    $target    = realpath($docs_dir . '/' . $requested);

    if ($target === false || strpos($target, $docs_dir . '/') !== 0) {
        http_response_code(400);
        echo 'Bad request';
        exit;
    }

    $content = htmlspecialchars(file_get_contents($target));
    $title   = htmlspecialchars(basename($target));
    ?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Labyrinth — <?= $title ?></title>
<script>window.__ARENA_LAB__ = {
    id: 'lab-02-php', name: 'Labyrinth', level: 2,
    platform: 'http://localhost:8080'
};</script>
<style>
body{font-family:monospace;background:#0d0f14;color:#d4d8e8;margin:0;padding:2rem}
h1{color:#5c8aff}
.card{background:#13161e;border:1px solid #1f2535;border-radius:8px;padding:1.5rem;margin:1rem 0}
pre{white-space:pre-wrap;word-break:break-word}
a{color:#5c8aff}
</style></head>
<body>
<h1><?= $title ?></h1>
<p><a href="/dashboard">&larr; Back to dashboard</a></p>
<div class="card"><pre><?= $content ?></pre></div>
</body></html>
    <?php exit;
}

// ── Admin: document viewer ────────────────────────────────────────────────────
if (preg_match('#^/admin/view$#', $path)) {
    if (!$is_admin) { http_response_code(403); echo 'Forbidden'; exit; }

    $page    = $_GET['page'] ?? '';
    $content = '';
    $title   = 'Admin File Preview';

    if ($page !== '') {
        $title = $page;
        ob_start();
        include '/var/www/html/docs/' . $page;
        $content = ob_get_clean();
    }
    ?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Labyrinth — Admin Preview</title>
<script>window.__ARENA_LAB__ = {
    id: 'lab-02-php', name: 'Labyrinth', level: 2,
    platform: 'http://localhost:8080'
};</script>
<style>
body{font-family:monospace;background:#0d0f14;color:#d4d8e8;margin:0;padding:2rem}
h1{color:#5c8aff}
.card{background:#13161e;border:1px solid #1f2535;border-radius:8px;padding:1.5rem;margin:1rem 0}
pre{white-space:pre-wrap;word-break:break-word}
a{color:#5c8aff}
input{background:#0a0c11;border:1px solid #1f2535;border-radius:5px;
      padding:8px 10px;color:#d4d8e8;font-family:monospace;width:360px}
button{background:#1a2440;color:#5c8aff;border:1px solid #233060;border-radius:5px;
       padding:8px 14px;font-family:monospace;cursor:pointer;margin-left:0.5rem}
</style></head>
<body>
<h1>Admin File Preview</h1>
<p><a href="/dashboard">&larr; Dashboard</a></p>
<form method="GET" action="/admin/view">
  <input name="page" value="<?= htmlspecialchars($page) ?>" placeholder="filename">
  <button type="submit">Preview</button>
</form>
<?php if ($content !== ''): ?>
<div class="card">
  <h3><?= htmlspecialchars($title) ?></h3>
  <pre><?= $content ?></pre>
</div>
<?php endif; ?>
</body></html>
    <?php exit;
}

// ── Admin: user management ────────────────────────────────────────────────────
if ($path === '/admin/users') {
    if (!$is_admin) { http_response_code(403); echo 'Forbidden'; exit; }
    $users = $db->query("SELECT id, username, email, role, created_at FROM users ORDER BY id")
                ->fetchAll(PDO::FETCH_ASSOC);
    ?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Labyrinth — Users</title>
<script>window.__ARENA_LAB__ = {
    id: 'lab-02-php', name: 'Labyrinth', level: 2,
    platform: 'http://localhost:8080'
};</script>
<style>
body{font-family:monospace;background:#0d0f14;color:#d4d8e8;margin:0;padding:2rem}
h1{color:#5c8aff}
table{border-collapse:collapse;width:100%}
th,td{padding:8px 12px;border:1px solid #1f2535;text-align:left}
th{background:#13161e;color:#5c8aff}
a{color:#5c8aff}
</style></head>
<body>
<h1>User Management</h1>
<p><a href="/dashboard">&larr; Dashboard</a></p>
<table>
  <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Created</th></tr>
  <?php foreach ($users as $u): ?>
  <tr>
    <td><?= (int)$u['id'] ?></td>
    <td><?= htmlspecialchars($u['username']) ?></td>
    <td><?= htmlspecialchars($u['email']) ?></td>
    <td><?= htmlspecialchars($u['role']) ?></td>
    <td><?= htmlspecialchars($u['created_at']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
</body></html>
    <?php exit;
}

// ── Admin: reports ────────────────────────────────────────────────────────────
if ($path === '/admin/reports') {
    if (!$is_admin) { http_response_code(403); echo 'Forbidden'; exit; }
    $doc_count  = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn();
    $user_count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $log_count  = $db->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
    ?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Labyrinth — Reports</title>
<script>window.__ARENA_LAB__ = {
    id: 'lab-02-php', name: 'Labyrinth', level: 2,
    platform: 'http://localhost:8080'
};</script>
<style>
body{font-family:monospace;background:#0d0f14;color:#d4d8e8;margin:0;padding:2rem}
h1{color:#5c8aff}
.card{background:#13161e;border:1px solid #1f2535;border-radius:8px;padding:1.5rem;
      margin:1rem 0;display:inline-block;min-width:180px;margin-right:1rem}
.num{font-size:2rem;color:#5c8aff}
a{color:#5c8aff}
</style></head>
<body>
<h1>System Reports</h1>
<p><a href="/dashboard">&larr; Dashboard</a></p>
<div class="card"><div class="num"><?= (int)$doc_count ?></div>Documents</div>
<div class="card"><div class="num"><?= (int)$user_count ?></div>Users</div>
<div class="card"><div class="num"><?= (int)$log_count ?></div>Audit events</div>
</body></html>
    <?php exit;
}

// ── Logout ────────────────────────────────────────────────────────────────────
if ($path === '/logout') {
    session_destroy();
    header('Location: /login');
    exit;
}

http_response_code(404);
echo 'Not found';
