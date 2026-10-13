<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/include/mysql_connect.inc.php');

$message = '';

if (isset($_GET['e'], $_GET['m'], $_GET['id'])) {
    $id = intval($_GET['id']);
    $e  = $_GET['e'];
    $m  = $_GET['m'];

    $row = queryDB(
        "SELECT creation_date FROM AT_members WHERE member_id=%d",
        array($id),
        true
    );

    if ($row) {
        $code = substr(md5($e . $row['creation_date'] . $id), 0, 10);

        if ($code == $m) {
            queryDB(
                "UPDATE AT_members SET email='%s' WHERE member_id=%d",
                array($e, $id)
            );
            $message = 'Your email address has been confirmed and updated.';
        } else {
            $message = 'Invalid confirmation code.';
        }
    } else {
        $message = 'Member not found.';
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Academy - Email Confirmation</title></head>
<body>
<h2>Email Confirmation</h2>
<p><?php echo htmlspecialchars($message); ?></p>
<p><a href="/login.php">Back to login</a></p>
</body>
</html>
