<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/include/session.php');

if (empty($_SESSION['valid_user'])) {
    header('Location: /login.php');
    exit;
}

$flag = trim(file_get_contents('/flag_local.txt'));
?>
<!DOCTYPE html>
<html>
<head><title>Academy - Dashboard</title></head>
<body>
<h2>Welcome, <?php echo htmlspecialchars($_SESSION['login']); ?></h2>
<p>Access level: Instructor</p>
<p><strong>Course completion token:</strong> <code><?php echo htmlspecialchars($flag); ?></code></p>
<hr>
<ul>
<li><a href="/mods/_standard/tests/index.php">Tests &amp; Surveys</a></li>
<li><a href="/logout.php">Log out</a></li>
</ul>
</body>
</html>
