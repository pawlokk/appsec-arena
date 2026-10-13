<?php
require_once '../config.php';
require_once '../includes/request_helper.php';

session_start();
if (empty($_SESSION['uid']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('<p>Access denied.</p>');
}

$pdo = new PDO('mysql:host=127.0.0.1;dbname=' . DB_NAME, DB_USER, DB_PASS);

$stmt = $pdo->query(
    "SELECT id, filename, status, status_detail, created_at FROM import_queue ORDER BY created_at DESC LIMIT 50"
);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html><head><title>Import Status — Flint CRM</title>
<style>
body{font-family:monospace;background:#1a1a2e;color:#eee;padding:2rem}
h1{color:#e94560}table{width:100%;border-collapse:collapse}
th,td{border:1px solid #333;padding:6px 10px;text-align:left}
.err{color:#ff6b6b}.ok{color:#6bff6b}
a{color:#e94560}
</style>
</head>
<body>
<h1>Import Queue Status</h1>
<a href="/admin.php">← Admin</a>
<br><br>
<table>
<tr><th>ID</th><th>File</th><th>Status</th><th>Error</th><th>Created</th></tr>
<?php foreach ($jobs as $j): ?>
<tr>
  <td><?= htmlspecialchars($j['id']) ?></td>
  <td><?= htmlspecialchars($j['filename'] ?? '') ?></td>
  <td class="<?= $j['status'] === 'error' ? 'err' : 'ok' ?>"><?= htmlspecialchars($j['status']) ?></td>
  <td><?= htmlspecialchars($j['status_detail'] ?? '') ?></td>
  <td><?= htmlspecialchars($j['created_at']) ?></td>
</tr>
<?php endforeach; ?>
</table>
</body></html>
