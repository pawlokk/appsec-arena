<?php
require_once __DIR__ . '/lib/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['authenticated'])) {
    header('Location: /dashboard.php');
    exit;
}
$error = isset($_GET['err']) ? 'Invalid username or password.' : '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Delibr ERP</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0f0f0f;color:#d0d0d0;font-family:monospace;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#1a1a1a;border:1px solid #2a2a2a;padding:36px;width:360px;border-radius:4px}
h1{color:#fff;font-size:18px;margin-bottom:4px}
.sub{color:#666;font-size:12px;margin-bottom:24px}
label{display:block;margin-bottom:14px;font-size:13px;color:#aaa}
label span{display:block;margin-bottom:4px}
input{width:100%;background:#111;border:1px solid #333;color:#d0d0d0;padding:8px 10px;border-radius:3px;font-family:inherit;font-size:13px}
button{width:100%;background:#1a3a5c;border:none;color:#fff;padding:10px;border-radius:3px;font-size:13px;cursor:pointer;margin-top:4px}
button:hover{background:#224a72}
.err{color:#cc6666;font-size:12px;margin-top:10px}
</style>
</head>
<body>
<div class="card">
<h1>Delibr ERP</h1>
<p class="sub">Enterprise Resource Platform</p>
<form method="post" action="/login.php">
<label><span>Username</span><input type="text" name="username" autocomplete="off"></label>
<label><span>Password</span><input type="password" name="password"></label>
<button type="submit">Sign In</button>
<?php if ($error): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
</form>
</div>
</body>
</html>
