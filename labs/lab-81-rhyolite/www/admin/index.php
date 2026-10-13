<?php
require_once __DIR__ . '/../config.php';
$admin = currentAdmin();
if (!$admin) { http_response_code(403); die('Forbidden — admin session required'); }
$db = db();
$s = $db->prepare('SELECT * FROM config WHERE section=? ORDER BY key_name');
$s->bind_param('s', $section='global');
$s->execute();
$cfgRows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
?><!DOCTYPE html>
<html><head><title>Hexmail Admin</title>
<style>body{font-family:Arial,sans-serif;margin:0;background:#f5f5f5}
.topbar{background:#333;color:#fff;padding:.7rem 1.5rem;display:flex;justify-content:space-between}
.topbar a{color:#ccc;text-decoration:none;margin-left:1rem}
.container{max-width:800px;margin:2rem auto;background:#fff;border-radius:6px;box-shadow:0 1px 4px rgba(0,0,0,.1);padding:1.5rem}
table{width:100%;border-collapse:collapse}
th,td{padding:.6rem 1rem;border-bottom:1px solid #eee;font-size:.9rem}
th{text-align:left;font-weight:600;background:#f8f8f8}</style>
</head><body>
<div class="topbar">
  <span>Hexmail Admin Panel</span>
  <span><a href="/mail/">Webmail</a><a href="/logout.php">Logout</a></span>
</div>
<div class="container">
<h3>Global Settings</h3>
<form method="POST" action="/admin/globalsave.php">
<table>
<?php foreach ($cfgRows as $row): ?>
<tr><th><?= htmlspecialchars($row['key_name']) ?></th>
<td><input type="text" name="fields[<?= htmlspecialchars($row['key_name']) ?>]"
   value="<?= htmlspecialchars($row['key_value']) ?>" style="width:100%;padding:.3rem"></td></tr>
<?php endforeach; ?>
</table>
<input type="hidden" name="save" value="1">
<br><button type="submit" style="background:#333;color:#fff;padding:.6rem 1.5rem;border:0;border-radius:4px;cursor:pointer">Save Settings</button>
</form>
<hr>
<h3>Plugin Management</h3>
<form method="POST" action="/admin/plugin/install.php" enctype="multipart/form-data">
<input type="file" name="pluginZip" accept=".zip">
<button type="submit" style="background:#333;color:#fff;padding:.5rem 1rem;border:0;border-radius:4px;cursor:pointer">Install Plugin</button>
</form>
</div></body></html>
