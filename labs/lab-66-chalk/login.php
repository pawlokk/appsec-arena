<?php
session_start();
require_once __DIR__ . '/config.php';

$e = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';

    if ($u && $p) {
        $c = get_db();
        $s = $c->prepare("SELECT * FROM users WHERE username = ?");
        $s->execute([$u]);
        $r = $s->fetch(PDO::FETCH_ASSOC);

        if ($r && password_verify($p, $r['password'])) {
            $_SESSION['user_id'] = $r['id'];
            $_SESSION['username'] = $r['username'];
            $_SESSION['display_name'] = $r['display_name'];
            $_SESSION['role'] = $r['role'];
            header('Location: /index.php');
            exit;
        } else {
            $e = 'Invalid username or password.';
        }
    } else {
        $e = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login — Chalk Wiki</title>
<style>
body { font-family: sans-serif; background: #f4f6f8; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
.box { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); width: 360px; }
h1 { margin-top: 0; color: #2c3e50; font-size: 1.5rem; }
input { width: 100%; padding: 0.5rem; margin: 0.4rem 0 1rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
button { width: 100%; padding: 0.6rem; background: #2980b9; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
button:hover { background: #2471a3; }
.error { color: #c0392b; margin-bottom: 1rem; }
.logo { font-size: 2rem; margin-bottom: 0.5rem; }
</style>
</head>
<body>
<div class="box">
    <div class="logo">📝</div>
    <h1>Chalk Wiki</h1>
    <?php if ($e): ?>
        <div class="error"><?= htmlspecialchars($e) ?></div>
    <?php endif; ?>
    <form method="POST">
        <label>Username</label>
        <input type="text" name="username" required autocomplete="username">
        <label>Password</label>
        <input type="password" name="password" required autocomplete="current-password">
        <button type="submit">Sign In</button>
    </form>
</div>
</body>
</html>
