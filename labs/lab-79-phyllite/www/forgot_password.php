<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/include/mysql_connect.inc.php');

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = $_POST['email'];

    $row = queryDB(
        "SELECT member_id, email, creation_date FROM AT_members WHERE email='%s'",
        array($email),
        true
    );

    if ($row) {
        $token = substr(md5($row['email'] . $row['creation_date']), 0, 10);
        queryDB(
            "REPLACE INTO AT_password_reset (member_id, token, created_at) VALUES (%d, '%s', NOW())",
            array($row['member_id'], $token)
        );

        $reset_link = 'http://localhost:5000/reset.php?id=' . $row['member_id'] . '&m=' . urlencode($token);
        $mailto = urlencode("To: {$row['email']}\nSubject: Academy Password Reset\n\n{$reset_link}");
        @file_get_contents('http://127.0.0.1:5000/notify/collect.php?data=' . $mailto);
    }

    $message = 'If that address is registered, a reset link has been sent.';
}
?>
<!DOCTYPE html>
<html>
<head><title>Academy - Forgot Password</title></head>
<body>
<h2>Forgot Password</h2>
<?php if ($message): ?><p><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<form method="post">
<label>Email: <input type="email" name="email"></label><br><br>
<input type="submit" value="Send Reset Link">
</form>
<p><a href="/login.php">Back to login</a></p>
</body>
</html>
