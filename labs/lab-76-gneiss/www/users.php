<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/store.php';
require_once __DIR__ . '/lib/compute.php';
requireAuth();

$users = getUsers();
$fields = loadFields();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Users — Delibr ERP</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0f0f0f;color:#d0d0d0;font-family:monospace;font-size:13px}
nav{background:#111;border-bottom:1px solid #222;padding:10px 24px;display:flex;align-items:center;gap:20px}
nav a{color:#888;text-decoration:none}nav a:hover{color:#fff}
nav .brand{color:#fff;font-size:14px;margin-right:12px}
nav .right{margin-left:auto}
main{padding:32px 24px}
h2{color:#fff;margin-bottom:16px;font-size:15px}
table{border-collapse:collapse;width:100%;max-width:900px}
th{background:#1a1a1a;color:#888;padding:8px 12px;text-align:left;border-bottom:1px solid #2a2a2a;font-weight:normal}
td{padding:8px 12px;border-bottom:1px solid #1e1e1e;color:#ccc}
tr:hover td{background:#151515}
.actions{margin-bottom:16px}
.btn{background:#1a3a5c;color:#fff;border:none;padding:6px 14px;border-radius:3px;cursor:pointer;text-decoration:none;font-size:12px;font-family:monospace}
.btn:hover{background:#224a72}
</style>
</head>
<body>
<nav>
<span class="brand">Delibr ERP</span>
<a href="/dashboard.php">Home</a>
<a href="/users.php">Users</a>
<a href="/admin/extrafields.php">Attributes</a>
<span class="right"><a href="/logout.php">Sign out</a></span>
</nav>
<main>
<h2>Users &amp; Groups</h2>
<div class="actions"><a href="/admin/extrafields.php" class="btn">Manage Attributes</a></div>
<table>
<thead>
<tr>
<th>ID</th>
<th>Username</th>
<th>Email</th>
<th>Role</th>
<?php foreach ($fields as $f): ?>
<th><?= htmlspecialchars($f['label']) ?></th>
<?php endforeach; ?>
</tr>
</thead>
<tbody>
<?php foreach ($users as $row): ?>
<tr>
<td><?= (int)$row['id'] ?></td>
<td><?= htmlspecialchars($row['username']) ?></td>
<td><?= htmlspecialchars($row['email']) ?></td>
<td><?= htmlspecialchars($row['role']) ?></td>
<?php foreach ($fields as $f): ?>
<td><?php
    $computed = $f['computed'] ?? '';
    if ($computed !== '') {
        echo computeField($computed, 1, 0, '');
    }
?></td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</main>
</body>
</html>
