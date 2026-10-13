<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/include/session.php');

if (empty($_SESSION['valid_user'])) {
    header('Location: /login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Academy - Tests</title></head>
<body>
<h2>Tests &amp; Surveys</h2>
<p>Import a test package:</p>
<form method="post" action="/mods/_standard/tests/import_test.php" enctype="multipart/form-data">
<input type="file" name="file"><br><br>
<input type="submit" value="Import Package">
</form>
<p><a href="/dashboard.php">Back to Dashboard</a></p>
</body>
</html>
