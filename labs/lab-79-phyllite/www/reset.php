<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/include/mysql_connect.inc.php');

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['m'], $_POST['new_password'])) {
    $id = intval($_POST['id']);
    $m  = $_POST['m'];

    $row = queryDB(
        "SELECT token FROM AT_password_reset WHERE member_id=%d",
        array($id),
        true
    );

    if ($row) {
        $stored = $row['token'];

        if ($stored == $m) {
            $new_pw = $_POST['new_password'];
            queryDB(
                "UPDATE AT_members SET password=SHA1('%s') WHERE member_id=%d",
                array($new_pw, $id)
            );
            queryDB(
                "DELETE FROM AT_password_reset WHERE member_id=%d",
                array($id)
            );
            $message = 'Password updated. You may now log in.';
        } else {
            $message = 'Invalid or expired reset token.';
        }
    } else {
        $message = 'No active reset request found.';
    }
}

$id  = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : '');
$m   = isset($_GET['m'])  ? htmlspecialchars($_GET['m'])  : (isset($_POST['m'])  ? htmlspecialchars($_POST['m'])  : '');
?>
<!DOCTYPE html>
<html>
<head><title>Academy - Reset Password</title></head>
<body>
<h2>Reset Password</h2>
<?php if ($message): ?><p><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if (!$message): ?>
<form method="post">
<input type="hidden" name="id" value="<?php echo intval($id); ?>">
<input type="hidden" name="m" value="<?php echo $m; ?>">
<label>New Password: <input type="password" name="new_password"></label><br><br>
<input type="submit" value="Reset Password">
</form>
<?php endif; ?>
<p><a href="/login.php">Back to login</a></p>
</body>
</html>
