<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/content_filter.php';

function generate_export_csrf(): string
{
    $token = bin2hex(random_bytes(16));
    file_put_contents('/tmp/chalk_csrf_' . $token, time());
    return $token;
}

function validate_export_csrf(string $token): bool
{
    $path = '/tmp/chalk_csrf_' . $token;
    if (!file_exists($path)) return false;
    $created = (int)file_get_contents($path);
    return (time() - $created) < 86400;
}

$msg = $error = '';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$is_admin = ($_SESSION['role'] ?? '') === 'admin';
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_export_csrf($csrf_token)) {
        $error = 'Invalid CSRF token.';
    } else {
        $export_name   = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['export_name'] ?? 'export');
        $export_filter = sanitise_input($_POST['export_filter'] ?? '');
        $format        = in_array($_POST['format'] ?? '', ['zip', 'tar']) ? $_POST['format'] : 'zip';

        $stmt = $db->prepare(
            "INSERT INTO export_schedules (name, export_filter, format, created_by)"
            . " VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$export_name, $export_filter, $format, $_SESSION['user_id']]);
        $msg = 'Export scheduled. Admin will trigger processing.';
    }
}

$schedules = $db->query(
    "SELECT es.*, u.username FROM export_schedules es"
    . " LEFT JOIN users u ON u.id = es.created_by ORDER BY es.created_at DESC LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC);

$csrf = generate_export_csrf();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Export Schedule — Chalk</title>
<style>
body{font-family:sans-serif;background:#f4f6f8;margin:0}
nav{background:#2c3e50;color:white;padding:.8rem 2rem;display:flex;align-items:center;gap:1.5rem}
nav a{color:#ecf0f1;text-decoration:none}nav .logo{font-weight:bold;font-size:1.2rem;margin-right:auto}
main{max-width:900px;margin:2rem auto;padding:0 1rem}
.box{background:white;padding:1.5rem 2rem;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-bottom:1.5rem}
label{display:block;margin-bottom:.3rem;font-weight:bold;color:#555}
input,select{width:100%;padding:.5rem;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;margin-bottom:1rem}
button{padding:.6rem 1.5rem;background:#27ae60;color:white;border:none;border-radius:4px;cursor:pointer}
.success{color:#27ae60}.error{color:#c0392b}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:.5rem .8rem;border-bottom:1px solid #eee}
</style>
</head>
<body>
<nav>
    <span class="logo">📝 Chalk</span>
    <a href="/index.php">Home</a>
    <?php if ($is_admin): ?><a href="/admin/index.php">Admin</a><?php endif; ?>
    <a href="/logout.php">Logout</a>
</nav>
<main>
    <h1>Export Schedules</h1>
    <?php if ($msg): ?><p class="success"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <div class="box">
        <h2>Schedule New Export</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <label>Export Name</label>
            <input type="text" name="export_name" value="wiki_export">
            <label>Filter <small>(file pattern to include, e.g. <code>*.md</code>)</small></label>
            <input type="text" name="export_filter" placeholder="*.md">
            <label>Format</label>
            <select name="format"><option value="zip">ZIP</option><option value="tar">TAR</option></select>
            <button type="submit">Schedule Export</button>
        </form>
    </div>

    <?php if ($is_admin): ?>
    <div class="box">
        <h2>Pending Schedules</h2>
        <table>
            <tr><th>ID</th><th>Name</th><th>Filter</th><th>By</th><th>Status</th><th>Action</th></tr>
            <?php foreach ($schedules as $s): ?>
            <tr>
                <td><?= (int)$s['id'] ?></td>
                <td><?= htmlspecialchars($s['name']) ?></td>
                <td><code><?= htmlspecialchars($s['export_filter']) ?></code></td>
                <td><?= htmlspecialchars($s['username'] ?? 'system') ?></td>
                <td><?= htmlspecialchars($s['status']) ?></td>
                <td>
                    <form method="POST" action="/api/export_trigger.php" style="display:inline">
                        <input type="hidden" name="schedule_id" value="<?= (int)$s['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <button type="submit" <?= $s['status'] === 'done' ? 'disabled' : '' ?>>Run</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
