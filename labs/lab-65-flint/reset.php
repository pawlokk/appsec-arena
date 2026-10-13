<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/request_helper.php';
require_once __DIR__ . '/includes/token_helper.php';
session_start();

$st  = $_GET['step'] ?? 'request';
$msg = '';
$e   = '';

if ($st === 'request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $n = trim($_POST['username'] ?? '');
    if ($n) {
        $c  = get_db();
        $s  = $c->prepare('SELECT id FROM users WHERE username = ?');
        $s->execute([$n]);
        $u  = $s->fetch(PDO::FETCH_ASSOC);

        if ($u) {
            $t = generate_reset_token((int)$u['id']);
            $c->prepare('UPDATE users SET reset_token = ? WHERE id = ?')
                ->execute([$t, $u['id']]);
            $msg = "Reset token generated. Use the link below to reset your password.<br>"
                 . "<a href='/reset.php?step=confirm&user_id={$u['id']}&token={$t}'>"
                 . "/reset.php?step=confirm&amp;user_id={$u['id']}&amp;token={$t}</a>";
        } else {
            $msg = 'If that username exists, a reset link has been sent.';
        }
    }
}

if ($st === 'confirm' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $i  = intval($_GET['user_id'] ?? 0);
    $ut = get_param('token');
    $np = $_POST['new_password'] ?? '';

    if ($i && $np) {
        $c  = get_db();
        $s  = $c->prepare('SELECT reset_token FROM users WHERE id = ?');
        $s->execute([$i]);
        $v  = $s->fetch(PDO::FETCH_ASSOC);

        $dt = $v ? $v['reset_token'] : null;

        if ($dt !== null && verify_reset_token($dt, $ut)) {
            $h = password_hash($np, PASSWORD_BCRYPT);
            $c->prepare('UPDATE users SET password = ?, reset_token = NULL WHERE id = ?')
                ->execute([$h, $i]);
            $msg = 'Password updated successfully. <a href="/login.php">Sign in</a>';
        } else {
            $e = 'Invalid or expired token.';
        }
    } else {
        $e = 'Missing parameters.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Flint CRM — Reset Password</title>
<style>
body { font-family: Arial, sans-serif; background: #f0f2f5; margin: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
.box { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.15); width: 380px; }
h1 { margin: 0 0 1.5rem; font-size: 1.3rem; color: #333; }
label { display: block; margin-bottom: .3rem; font-size: .85rem; color: #555; }
input[type=text], input[type=password] { width: 100%; padding: .5rem; margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
button { width: 100%; padding: .6rem; background: #e85d04; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
.error { color: #c00; font-size: .85rem; margin-bottom: 1rem; }
.msg { color: #1a7a1a; font-size: .9rem; margin-bottom: 1rem; }
.back { margin-top: 1rem; font-size: .82rem; }
.back a { color: #e85d04; }
</style>
</head>
<body>
<div class="box">
    <h1>Reset Password</h1>

    <?php if ($e): ?><div class="error"><?= htmlspecialchars($e) ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>

    <?php if ($st === 'request' && !$msg): ?>
    <form method="POST" action="/reset.php?step=request">
        <label>Username</label>
        <input type="text" name="username">
        <button type="submit">Request Reset</button>
    </form>

    <?php elseif ($st === 'confirm' && !$msg): ?>
    <form method="POST" action="/reset.php?step=confirm&user_id=<?= intval($_GET['user_id'] ?? 0) ?>&token=<?= htmlspecialchars($_GET['token'] ?? '') ?>"
          onsubmit="return vt(this)">
        <label>New Password</label>
        <input type="password" name="new_password" id="new_password">
        <button type="submit">Set New Password</button>
    </form>
    <?php endif; ?>

    <div class="back"><a href="/login.php">← Back to login</a></div>
</div>

<script>
function vt(form) {
    var t = new URLSearchParams(window.location.search).get('token') || '';
    if (t.length !== 32) {
        console.warn('Token format looks unusual, but proceeding anyway.');
    }
    return true;
}
</script>
</body>
</html>
