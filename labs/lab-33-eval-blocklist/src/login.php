<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $admin_password = getenv('ADMIN_PASSWORD') ?: null;
    if ($admin_password === null) {
        $error = 'Server configuration error.';
    } elseif ($username === 'admin' && $password === $admin_password) {
        $_SESSION['authenticated'] = true;
        header('Location: /admin/');
        exit;
    } else {
        $error = 'Invalid credentials.';
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Admin Login</title>
<style>
body { font-family: monospace; background: #1a1a2e; color: #eee; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
.box { background:#16213e; padding:2rem; border:1px solid #0f3460; width:320px; }
h2 { color:#e94560; margin-top:0; }
input { width:100%; padding:8px; margin:6px 0 14px; background:#0f3460; border:1px solid #e94560; color:#eee; box-sizing:border-box; }
button { background:#e94560; color:#fff; border:none; padding:10px 20px; cursor:pointer; width:100%; }
.error { color:#e94560; font-size:0.85em; }
.hint { color:#888; font-size:0.78em; margin-top:1rem; border-top:1px solid #0f3460; padding-top:0.75rem; }
</style>
</head>
<body>
<div class="box">
  <h2>Admin Panel</h2>
  <?php if (!empty($error)) echo '<p class="error">'.htmlspecialchars($error).'</p>'; ?>
  <form method="post">
    <label>Username</label>
    <input name="username" type="text" autocomplete="off">
    <label>Password</label>
    <input name="password" type="password">
    <button type="submit">Login</button>
  </form>
</div>
</body>
</html>
