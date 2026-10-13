<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/include/mysql_connect.inc.php');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$row = null;
if ($id > 0) {
    $row = queryDB(
        "SELECT member_id, first_name, last_name, creation_date FROM AT_members WHERE member_id=%d",
        array($id),
        true
    );
}
?>
<!DOCTYPE html>
<html>
<head><title>Academy - Profile</title></head>
<body>
<h2>Member Profile</h2>
<?php if ($row): ?>
<p><strong>Name:</strong> <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></p>
<p><strong>Member ID:</strong> <?php echo intval($row['member_id']); ?></p>
<p><strong>Registered:</strong> <?php echo htmlspecialchars($row['creation_date']); ?></p>
<?php else: ?>
<p>Member not found.</p>
<?php endif; ?>
<p><a href="/login.php">Back to login</a></p>
</body>
</html>
