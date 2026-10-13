<?php
require_once __DIR__ . '/config.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');
    $db = db();
    $s = $db->prepare('SELECT * FROM accounts WHERE email=? AND password=SHA1(?)');
    $s->bind_param('ss', $email, $pass);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    if ($row) {
        $token = bin2hex(random_bytes(32));
        $st = $db->prepare('INSERT INTO sessions (token, account_id) VALUES (?,?)');
        $st->bind_param('si', $token, $row['id']);
        $st->execute();
        setcookie('hexmail6', $token, 0, '/', '', false, true);
        if ($row['is_admin']) {
            $atoken = bin2hex(random_bytes(32));
            $sa = $db->prepare('INSERT INTO admin_sessions (token, account_id) VALUES (?,?)');
            $sa->bind_param('si', $atoken, $row['id']);
            $sa->execute();
            setcookie('hexmail6_admin', $atoken, 0, '/', '', false, false);
        }
        header('Location: /mail/'); exit;
    }
    $error = 'Invalid credentials.';
}
?><!DOCTYPE html>
<html><head><title>Hexmail — Login</title>
<style>body{font-family:Arial,sans-serif;background:#f0f2f5;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
.box{background:#fff;padding:2rem;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,.15);width:320px}
h2{margin:0 0 1.5rem;text-align:center;color:#1a73e8}
input{width:100%;box-sizing:border-box;padding:.6rem;margin-bottom:1rem;border:1px solid #ccc;border-radius:4px}
button{width:100%;padding:.7rem;background:#1a73e8;color:#fff;border:0;border-radius:4px;cursor:pointer}
.err{color:red;font-size:.85rem;margin-bottom:.8rem}</style>
</head><body>
<div class="box">
<h2>Hexmail Webmail</h2>
<?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST">
<input type="email" name="email" placeholder="Email address" required>
<input type="password" name="password" placeholder="Password" required>
<button type="submit">Sign In</button>
</form>
</div></body></html>
