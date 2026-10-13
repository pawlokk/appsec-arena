<?php
require_once __DIR__ . '/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$o  = '';
$e  = '';
$rx = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rx = $_POST['xml'] ?? '';
    if ($rx) {
        libxml_disable_entity_loader(true);
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($rx, 'SimpleXMLElement', LIBXML_NOENT);
        if ($xml === false) {
            $er = libxml_get_errors();
            $e = 'XML parse error: ' . ($er ? $er[0]->message : 'unknown');
        } else {
            $o = print_r(json_decode(json_encode($xml), true), true);
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Flint CRM — XML Preview</title>
<style>
body { font-family: Arial, sans-serif; background: #f0f2f5; margin: 0; }
nav { background: #e85d04; color: #fff; padding: .8rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
nav a { color: #fff; text-decoration: none; margin-left: 1rem; }
.container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
h1 { font-size: 1.4rem; color: #333; }
textarea { width: 100%; height: 180px; padding: .5rem; border: 1px solid #ccc; border-radius: 4px; font-family: monospace; font-size: .85rem; box-sizing: border-box; }
button { padding: .5rem 1.2rem; background: #e85d04; color: #fff; border: none; border-radius: 4px; cursor: pointer; margin-top: .5rem; }
pre { background: #fff; border: 1px solid #ddd; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: .85rem; }
.error { color: #c00; margin-bottom: .8rem; }
.note { background: #e8f5e9; border-left: 4px solid #27ae60; padding: .6rem 1rem; margin-bottom: 1rem; font-size: .85rem; }
</style>
</head>
<body>
<nav>
    <strong>🔥 Flint CRM</strong>
    <div>
        <a href="/search.php">Customers</a>
        <a href="/import.php">Bulk Import</a>
        <a href="/logout.php">Sign out</a>
    </div>
</nav>
<div class="container">
    <h1>XML Preview</h1>
    <div class="note">&#10003; XML processing</div>
    <?php if ($e): ?><div class="error"><?= htmlspecialchars($e) ?></div><?php endif; ?>
    <form method="POST">
        <textarea name="xml" placeholder="Paste XML here…"><?= htmlspecialchars($rx) ?></textarea>
        <button type="submit">Parse</button>
    </form>
    <?php if ($o): ?>
    <h2>Parsed Structure</h2>
    <pre><?= htmlspecialchars($o) ?></pre>
    <?php endif; ?>
    <p><small>For bulk contact import, use <a href="/import.php">the import API</a>.</small></p>
</div>
</body>
</html>
