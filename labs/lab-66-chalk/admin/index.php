<?php
session_start();
require_once __DIR__ . '/../config.php';
require_admin();

$f = trim(file_get_contents('/flag_local.txt'));

$c = get_db();
$u = $c->query("SELECT id, username, display_name, role, created_at FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$n = $c->query("SELECT COUNT(*) FROM articles")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="csrf-protection" content="enabled">
<title>Admin — Chalk Wiki</title>
<style>
body { font-family: sans-serif; background: #f4f6f8; margin: 0; }
nav { background: #2c3e50; color: white; padding: 0.8rem 2rem; display: flex; align-items: center; gap: 1.5rem; }
nav a { color: #ecf0f1; text-decoration: none; }
nav a:hover { text-decoration: underline; }
nav .logo { font-weight: bold; font-size: 1.2rem; margin-right: auto; }
main { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
h1 { color: #2c3e50; }
.flag-box { background: #2c3e50; color: #2ecc71; font-family: monospace; padding: 1rem 1.5rem; border-radius: 6px; margin-bottom: 2rem; font-size: 1.1rem; }
.card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 1.5rem; }
table { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 0.5rem 0.8rem; border-bottom: 1px solid #eee; }
th { background: #f8f9fa; }
.admin-links { display: flex; gap: 1rem; flex-wrap: wrap; }
.admin-links a { display: inline-block; padding: 0.6rem 1.2rem; background: #2980b9; color: white; text-decoration: none; border-radius: 4px; }
.admin-links a:hover { background: #2471a3; }
</style>
</head>
<body>
<nav>
    <span class="logo">📝 Chalk Wiki</span>
    <a href="/index.php">Home</a>
    <a href="/articles.php">Articles</a>
    <a href="/admin/index.php">Admin</a>
    <a href="/logout.php">Logout (<?= htmlspecialchars($_SESSION['username']) ?>)</a>
</nav>
<main>
    <h1>Admin Panel</h1>
    <div class="flag-box">&#128274; FLAG_LOCAL: <?= htmlspecialchars($f) ?></div>

    <div class="card">
        <h2>Quick Stats</h2>
        <p>Total users: <?= count($u) ?> &nbsp;|&nbsp; Total articles: <?= (int)$n ?></p>
    </div>

    <div class="card">
        <h2>Admin Tools</h2>
        <div class="admin-links">
            <a href="/admin/report.php">User Report</a>
            <a href="/admin/backup.php">Backup &amp; Restore</a>
            <a href="/admin/settings.php">Settings</a>
        </div>
    </div>

    <div class="card">
        <h2>Users</h2>
        <table>
            <tr><th>ID</th><th>Username</th><th>Display Name</th><th>Role</th><th>Joined</th></tr>
            <?php foreach ($u as $r): ?>
            <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= htmlspecialchars($r['username']) ?></td>
                <td><?= htmlspecialchars($r['display_name']) ?></td>
                <td><?= htmlspecialchars($r['role']) ?></td>
                <td><?= htmlspecialchars($r['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</main>
</body>
</html>
