<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/store.php';
requireAuth();

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $label = trim($_POST['label'] ?? '');
    $type = trim($_POST['type'] ?? 'varchar');
    $computed = trim($_POST['computed'] ?? '');

    if ($name === '') $errors[] = 'Name is required.';
    if ($label === '') $errors[] = 'Label is required.';

    if (empty($errors)) {
        $fields = loadFields();
        if ($action === 'create') {
            $fields[] = [
                'id' => time(),
                'name' => $name,
                'label' => $label,
                'type' => $type,
                'computed' => $computed,
            ];
            saveFields($fields);
            header('Location: /admin/extrafields.php?saved=1');
            exit;
        } elseif ($action === 'edit' && $id !== null) {
            foreach ($fields as &$f) {
                if ($f['id'] === $id) {
                    $f['name'] = $name;
                    $f['label'] = $label;
                    $f['type'] = $type;
                    $f['computed'] = $computed;
                    break;
                }
            }
            unset($f);
            saveFields($fields);
            header('Location: /admin/extrafields.php?saved=1');
            exit;
        }
    }
}

if ($action === 'delete' && $id !== null) {
    $fields = loadFields();
    $fields = array_filter($fields, fn($f) => $f['id'] !== $id);
    saveFields(array_values($fields));
    header('Location: /admin/extrafields.php');
    exit;
}

$fields = loadFields();
$editing = null;
if ($action === 'edit' && $id !== null) {
    foreach ($fields as $f) {
        if ($f['id'] === $id) { $editing = $f; break; }
    }
}
$saved = isset($_GET['saved']);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Extra Attributes — Delibr ERP</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0f0f0f;color:#d0d0d0;font-family:monospace;font-size:13px}
nav{background:#111;border-bottom:1px solid #222;padding:10px 24px;display:flex;align-items:center;gap:20px}
nav a{color:#888;text-decoration:none}nav a:hover{color:#fff}
nav .brand{color:#fff;font-size:14px;margin-right:12px}
nav .right{margin-left:auto}
main{padding:32px 24px;display:flex;gap:32px;flex-wrap:wrap}
h2{color:#fff;margin-bottom:16px;font-size:15px}
.panel{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:4px;padding:24px;min-width:320px}
label{display:block;margin-bottom:14px;color:#aaa}
label span{display:block;margin-bottom:4px}
input,select,textarea{width:100%;background:#111;border:1px solid #333;color:#d0d0d0;padding:7px 10px;border-radius:3px;font-family:inherit;font-size:13px}
textarea{resize:vertical;min-height:72px}
.hint{color:#555;font-size:11px;margin-top:3px}
button{background:#1a3a5c;color:#fff;border:none;padding:8px 16px;border-radius:3px;cursor:pointer;font-size:12px;font-family:monospace;margin-top:4px}
button:hover{background:#224a72}
table{border-collapse:collapse;width:100%}
th{background:#111;color:#888;padding:7px 10px;text-align:left;border-bottom:1px solid #222;font-weight:normal}
td{padding:7px 10px;border-bottom:1px solid #1a1a1a}
.act a{color:#5899d6;text-decoration:none;margin-right:8px}
.act a:hover{text-decoration:underline}
.act a.del{color:#993333}
.msg{color:#5a9;font-size:12px;margin-bottom:12px}
.err{color:#c66;font-size:12px;margin-bottom:12px}
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
<div>
<h2><?= $editing ? 'Edit Attribute' : 'New Attribute' ?></h2>
<div class="panel">
<?php if ($saved): ?><p class="msg">Saved successfully.</p><?php endif; ?>
<?php foreach ($errors as $e): ?><p class="err"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
<form method="post" action="/admin/extrafields.php?action=<?= $editing ? 'edit&id='.$editing['id'] : 'create' ?>">
<label>
<span>Attribute name (internal key)</span>
<input type="text" name="name" value="<?= htmlspecialchars($editing['name'] ?? '') ?>" placeholder="my_field">
</label>
<label>
<span>Label</span>
<input type="text" name="label" value="<?= htmlspecialchars($editing['label'] ?? '') ?>" placeholder="My Field">
</label>
<label>
<span>Type</span>
<select name="type">
<?php foreach (['varchar','integer','date','boolean'] as $t): ?>
<option value="<?= $t ?>"<?= ($editing['type'] ?? 'varchar') === $t ? ' selected' : '' ?>><?= $t ?></option>
<?php endforeach; ?>
</select>
</label>
<label>
<span>Computed field</span>
<textarea name="computed" placeholder="e.g. quantity * price"><?= htmlspecialchars($editing['computed'] ?? '') ?></textarea>
<span class="hint">Enter any PHP coding to get a dynamic computed value. Leave blank to disable.</span>
</label>
<button type="submit">Save</button>
<?php if ($editing): ?> <a href="/admin/extrafields.php" style="color:#888;text-decoration:none;margin-left:10px;font-size:12px">Cancel</a><?php endif; ?>
</form>
</div>
</div>
<div style="flex:1;min-width:300px">
<h2>Defined Attributes</h2>
<?php if (empty($fields)): ?>
<p style="color:#555">No attributes defined yet.</p>
<?php else: ?>
<table>
<tr><th>Name</th><th>Label</th><th>Type</th><th>Computed</th><th></th></tr>
<?php foreach ($fields as $f): ?>
<tr>
<td><?= htmlspecialchars($f['name']) ?></td>
<td><?= htmlspecialchars($f['label']) ?></td>
<td><?= htmlspecialchars($f['type']) ?></td>
<td><?= $f['computed'] ? '<span style="color:#5a9">yes</span>' : '<span style="color:#555">no</span>' ?></td>
<td class="act">
<a href="/admin/extrafields.php?action=edit&id=<?= $f['id'] ?>">edit</a>
<a href="/admin/extrafields.php?action=delete&id=<?= $f['id'] ?>" class="del" onclick="return confirm('Delete this attribute?')">delete</a>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
</main>
</body>
</html>
