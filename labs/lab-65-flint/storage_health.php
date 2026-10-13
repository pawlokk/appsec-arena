<?php
require_once 'config.php';
session_start();

if (empty($_SESSION['uid']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('<p>Access denied.</p>');
}

$pdo = new PDO('mysql:host=127.0.0.1;dbname=' . DB_NAME, DB_USER, DB_PASS);

$vars = $pdo->query("SELECT @@version AS version, @@secure_file_priv AS secure_file_priv, @@datadir AS datadir")->fetch(PDO::FETCH_ASSOC);
$grants = $pdo->query("SHOW GRANTS FOR CURRENT_USER()")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html><head><title>Storage Health — Flint CRM</title>
<style>body{font-family:monospace;background:#1a1a2e;color:#eee;padding:2rem}h1{color:#e94560}
table{border-collapse:collapse}td,th{border:1px solid #333;padding:6px 12px}a{color:#e94560}</style>
</head>
<body>
<h1>Storage Health</h1>
<a href="/admin.php">← Admin</a>
<br><br>
<table>
<tr><th>Variable</th><th>Value</th></tr>
<tr><td>version</td><td><?= htmlspecialchars($vars['version']) ?></td></tr>
<tr><td>secure_file_priv</td><td><?= htmlspecialchars($vars['secure_file_priv'] ?? '(empty)') ?></td></tr>
<tr><td>datadir</td><td><?= htmlspecialchars($vars['datadir']) ?></td></tr>
</table>
<br>
<h2>Current User Grants</h2>
<table>
<?php foreach ($grants as $g): ?>
<tr><td><?= htmlspecialchars($g) ?></td></tr>
<?php endforeach; ?>
</table>
</body></html>
