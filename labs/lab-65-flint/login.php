<?php
require_once __DIR__ . '/config.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: /admin.php');
    exit;
}

$e = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $n = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';

    if ($n && $p) {
        $c = get_db();
        $s = $c->prepare('SELECT id, username, password, role FROM users WHERE username = ?');
        $s->execute([$n]);
        $u = $s->fetch(PDO::FETCH_ASSOC);

        if ($u && password_verify($p, $u['password'])) {
            $_SESSION['user_id'] = $u['id'];
            $_SESSION['username'] = $u['username'];
            $_SESSION['role'] = $u['role'];
            header($u['role'] === 'admin' ? 'Location: /admin.php' : 'Location: /search.php');
            exit;
        } else {
            $e = 'Invalid username or password.';
        }
    } else {
        $e = 'Please enter username and password.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Flint CRM — Login</title>
<style>
body { font-family: Arial, sans-serif; background: #f0f2f5; margin: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
.box { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.15); width: 340px; }
h1 { margin: 0 0 1.5rem; font-size: 1.4rem; color: #333; }
label { display: block; margin-bottom: .3rem; font-size: .85rem; color: #555; }
input[type=text], input[type=password] { width: 100%; padding: .5rem; margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
button { width: 100%; padding: .6rem; background: #e85d04; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
button:hover { background: #c44d00; }
.error { color: #c00; font-size: .85rem; margin-bottom: 1rem; }
.links { margin-top: 1rem; font-size: .82rem; text-align: center; }
.links a { color: #e85d04; text-decoration: none; }
</style>
</head>
<body>
<div class="box">
    <h1>🔥 Flint CRM</h1>
    <?php if ($e): ?><div class="error"><?= htmlspecialchars($e) ?></div><?php endif; ?>
    <form method="POST">
        <label>Username</label>
        <input type="text" name="username" autocomplete="off">
        <label>Password</label>
        <input type="password" name="password">
        <button type="submit">Sign In</button>
    </form>
    <div class="links">
        <a href="/reset.php">Forgot password?</a>
        &nbsp;|&nbsp;
        <a href="/import.php">API Import</a>
    </div>
</div>
</body>
</html>
