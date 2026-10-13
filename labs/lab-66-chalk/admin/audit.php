<?php
session_start();
require_once __DIR__ . '/../config.php';
require_admin();

$c = get_db();
$s = $c->query("SELECT a.id, a.action, u.username, a.detail, a.created_at FROM audit_log a LEFT JOIN users u ON u.id = a.actor_id ORDER BY a.created_at DESC LIMIT 50");
$r = $s->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html><head><title>Audit Log</title></head>
<body>
<h2>Audit Log</h2>
<a href="/admin.php">← Admin</a>
<br><br>
<table border="1">
<tr><th>ID</th><th>Action</th><th>Actor</th><th>Detail</th><th>Time</th></tr>
<?php foreach ($r as $v): ?>
<tr>
  <td><?= htmlspecialchars($v['id']) ?></td>
  <td><?= htmlspecialchars($v['action']) ?></td>
  <td><?= htmlspecialchars($v['username'] ?? 'system') ?></td>
  <td><?= htmlspecialchars($v['detail']) ?></td>
  <td><?= htmlspecialchars($v['created_at']) ?></td>
</tr>
<?php endforeach; ?>
</table>
</body></html>
