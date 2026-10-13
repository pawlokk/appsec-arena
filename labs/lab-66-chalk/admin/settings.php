<?php
session_start();
require_once __DIR__ . '/../config.php';

$z = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($z && strpos($z, 'wiki.internal') !== false) {
    header('Access-Control-Allow-Origin: ' . $z);
    header('Access-Control-Allow-Credentials: true');
} elseif (!$z) {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_admin();

$m = '';
$e = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'change_password') {
    $p = $_POST['new_password'] ?? '';
    $q = $_POST['confirm_password'] ?? '';

    if (!$p || !$q) {
        $e = 'All fields are required.';
    } elseif ($p !== $q) {
        $e = 'Passwords do not match.';
    } elseif (strlen($p) < 8) {
        $e = 'Password must be at least 8 characters.';
    } else {
        $h = password_hash($p, PASSWORD_BCRYPT);
        $c = get_db();
        $s = $c->prepare("UPDATE users SET password=? WHERE username='admin'");
        $s->execute([$h]);
        $m = 'Admin password updated successfully.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="csrf-protection" content="enabled">
<title>Settings — Chalk Wiki Admin</title>
<style>
body { font-family: sans-serif; background: #f4f6f8; margin: 0; }
nav { background: #2c3e50; color: white; padding: 0.8rem 2rem; display: flex; align-items: center; gap: 1.5rem; }
nav a { color: #ecf0f1; text-decoration: none; }
nav a:hover { text-decoration: underline; }
nav .logo { font-weight: bold; font-size: 1.2rem; margin-right: auto; }
main { max-width: 600px; margin: 2rem auto; padding: 0 1rem; }
h1 { color: #2c3e50; }
.box { background: white; padding: 1.5rem 2rem; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
label { display: block; margin-bottom: 0.3rem; font-weight: bold; color: #555; }
input[type=password] { width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; margin-bottom: 1rem; }
button { padding: 0.6rem 1.5rem; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer; }
button:hover { background: #c0392b; }
.success { color: #27ae60; margin-bottom: 1rem; }
.error { color: #c0392b; margin-bottom: 1rem; }
</style>
</head>
<body>
<nav>
    <span class="logo">📝 Chalk Wiki</span>
    <a href="/index.php">Home</a>
    <a href="/admin/index.php">Admin</a>
    <a href="/logout.php">Logout</a>
</nav>
<main>
    <h1>Admin Settings</h1>
    <div class="box">
        <h2>Change Admin Password</h2>
        <?php if ($m): ?><div class="success"><?= htmlspecialchars($m) ?></div><?php endif; ?>
        <?php if ($e): ?><div class="error"><?= htmlspecialchars($e) ?></div><?php endif; ?>
        <form method="POST" action="/admin/settings.php?action=change_password">
            <label>New Password</label>
            <input type="password" name="new_password" required>
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required>
            <button type="submit">Update Password</button>
        </form>
    </div>
</main>
</body>
</html>
