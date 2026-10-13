<?php
session_start();
require_once __DIR__ . '/../config.php';
require_admin();

$c = get_db();
$m = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['admin_csrf']) || !hash_equals($_SESSION['admin_csrf'], $t)) {
        http_response_code(403);
        exit('<p>CSRF validation failed.</p>');
    }
    $k = $_POST['setting_key'] ?? '';
    $v = $_POST['setting_value'] ?? '';
    if ($k && strlen($k) <= 64) {
        $s = $c->prepare("INSERT INTO site_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)");
        $s->execute([$k, $v, (int)$_SESSION['user_id']]);
        $m = 'Setting saved.';
    }
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
}

$s = $c->query("SELECT setting_key, setting_value, updated_at FROM site_settings ORDER BY updated_at DESC LIMIT 20");
$r = $s->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html><head><title>Admin Config</title></head>
<body>
<h2>Site Configuration</h2>
<?php if ($m): ?><p><?= htmlspecialchars($m) ?></p><?php endif; ?>
<form method="post">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf']) ?>">
  <input name="setting_key" placeholder="Key" required>
  <input name="setting_value" placeholder="Value">
  <button type="submit">Save</button>
</form>
<hr>
<table border="1"><tr><th>Key</th><th>Value</th><th>Updated</th></tr>
<?php foreach ($r as $v): ?>
<tr>
  <td><?= htmlspecialchars($v['setting_key']) ?></td>
  <td><?= htmlspecialchars($v['setting_value']) ?></td>
  <td><?= htmlspecialchars($v['updated_at']) ?></td>
</tr>
<?php endforeach; ?>
</table>
<p><a href="/admin.php">← Admin</a></p>
</body></html>
