<?php
session_start();
require_once __DIR__ . '/../config.php';
require_admin();

function pb($n) {
    return shell_exec("ls -la /tmp/backup_" . escapeshellarg($n) . ".zip 2>&1");
}

$m = '';
$e = '';
$o = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $x = $_POST['action'] ?? '';

    if ($x === 'backup') {
        $n = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['backup_name'] ?? 'backup');
        if (!$n) $n = 'backup';
        exec("zip -r /tmp/backup_" . $n . ".zip /var/www/html/ 2>&1", $o, $r);
        $r === 0 ? $m = "Backup created: /tmp/backup_{$n}.zip" : $e = "Backup failed.";

    } elseif ($x === 'restore') {
        if (isset($_FILES['archive']) && $_FILES['archive']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['archive']['name'];
            $t = $_FILES['archive']['tmp_name'];
            move_uploaded_file($t, '/tmp/' . $f);
            exec("unzip -o /tmp/" . $f . " -d /var/www/html/ 2>&1", $o, $r);
            $r === 0 ? $m = "Restore completed." : $e = "Restore failed (check archive format).";
        } else {
            $e = "No file uploaded or upload error.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="csrf-protection" content="enabled">
<title>Backup &amp; Restore — Chalk Wiki Admin</title>
<style>
body { font-family: sans-serif; background: #f4f6f8; margin: 0; }
nav { background: #2c3e50; color: white; padding: 0.8rem 2rem; display: flex; align-items: center; gap: 1.5rem; }
nav a { color: #ecf0f1; text-decoration: none; }
nav a:hover { text-decoration: underline; }
nav .logo { font-weight: bold; font-size: 1.2rem; margin-right: auto; }
main { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
h1 { color: #2c3e50; }
.box { background: white; padding: 1.5rem 2rem; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 1.5rem; }
label { display: block; margin-bottom: 0.3rem; font-weight: bold; color: #555; }
input[type=text], input[type=file] { width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; margin-bottom: 1rem; }
button { padding: 0.6rem 1.5rem; background: #2980b9; color: white; border: none; border-radius: 4px; cursor: pointer; }
button:hover { background: #2471a3; }
.success { color: #27ae60; margin-bottom: 1rem; }
.error { color: #c0392b; margin-bottom: 1rem; }
pre { background: #2c3e50; color: #ecf0f1; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 0.85rem; }
.warn { background: #ffeaa7; border-left: 4px solid #fdcb6e; padding: 0.8rem 1rem; margin-bottom: 1rem; border-radius: 4px; }
</style>
</head>
<body>
<nav>
    <span class="logo">📝 Chalk Wiki</span>
    <a href="/index.php">Home</a>
    <a href="/admin/index.php">Admin</a>
    <a href="/logout.php">Logout</a>
</nav>
<main>
    <h1>Backup &amp; Restore</h1>

    <?php if ($m): ?><div class="success"><?= htmlspecialchars($m) ?></div><?php endif; ?>
    <?php if ($e): ?><div class="error"><?= htmlspecialchars($e) ?></div><?php endif; ?>
    <?php if ($o): ?><pre><?= htmlspecialchars(implode("\n", $o)) ?></pre><?php endif; ?>

    <div class="box">
        <h2>Create Backup</h2>
        <p>Creates a zip archive of all wiki content stored in <code>/tmp/</code>.</p>
        <form method="POST">
            <input type="hidden" name="action" value="backup">
            <label>Backup Name (alphanumeric, _ and - only)</label>
            <input type="text" name="backup_name" value="wiki_backup" placeholder="wiki_backup">
            <button type="submit">Create Backup</button>
        </form>
    </div>

    <div class="box">
        <h2>Restore from Archive</h2>
        <div class="warn">&#9888; Restoring will overwrite existing wiki files. Use with caution.</div>
        <p>Upload a <code>.zip</code> archive previously created by the backup tool.</p>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="restore">
            <label>Archive File (.zip)</label>
            <input type="file" name="archive" accept=".zip">
            <button type="submit">Restore</button>
        </form>
    </div>
</main>
</body>
</html>
