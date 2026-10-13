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
<ul>
<li><a href="/mods/_standard/tests/import_test.php">Import Test Package</a></li>
</ul>
<p><a href="/dashboard.php">Back</a></p>
</body>
</html>
