<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/include/session.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/include/mysql_connect.inc.php');

$error = '';

if (isset($_POST['submit'])) {
    $this_login    = $_POST['form_login'];
    $this_password = $_POST['form_password'];

    $row = queryDB(
        "SELECT member_id, login FROM AT_members WHERE login='%s' AND password=SHA1('%s')",
        array($this_login, $this_password),
        true
    );

    if ($row) {
        $_SESSION['valid_user'] = true;
        $_SESSION['member_id']  = intval($row['member_id']);
        $_SESSION['login']      = $row['login'];
        queryDB("UPDATE AT_members SET last_login=NOW() WHERE member_id=%d", array($row['member_id']));
        header('Location: /dashboard.php');
        exit;
    } else {
        $error = 'Invalid login or password.';
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Academy - Login</title></head>
<body>
<h2>Academy Login</h2>
<?php if ($error): ?><p style="color:red"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
<form method="post">
<label>Username: <input type="text" name="form_login"></label><br><br>
<label>Password: <input type="password" name="form_password"></label><br><br>
<input type="submit" name="submit" value="Login">
</form>
<p><a href="/forgot_password.php">Forgot password?</a></p>
<p><a href="/profile.php?id=1">View instructor profile</a></p>
</body>
</html>
