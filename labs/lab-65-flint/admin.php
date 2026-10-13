<?php
require_once __DIR__ . '/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$flag_local = file_exists('/flag_local.txt') ? trim(file_get_contents('/flag_local.txt')) : 'FLAG_NOT_FOUND';

$pdo = get_db();

$results = [];
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['q'])) {
    $q   = $_POST['q'];
    $sql = "SELECT * FROM customers WHERE name LIKE '%" . $q . "%'";
    try {
        $stmt    = $pdo->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Query error: ' . $e->getMessage();
    }
} else {
    $stmt    = $pdo->query('SELECT * FROM customers');
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$users_stmt = $pdo->query('SELECT id, username, email, role FROM users');
$all_users  = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Flint CRM — Admin Panel</title>
<style>
body { font-family: Arial, sans-serif; background: #f0f2f5; margin: 0; }
nav { background: #1a1a1a; color: #fff; padding: .8rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
nav a { color: #e85d04; text-decoration: none; margin-left: 1rem; }
.container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
h1 { font-size: 1.4rem; color: #333; }
h2 { font-size: 1.1rem; color: #555; margin-top: 2rem; }
.flag-box { background: #fff3e0; border: 1px solid #e85d04; border-radius: 6px; padding: 1rem 1.5rem; margin-bottom: 1.5rem; font-family: monospace; font-size: .95rem; }
.flag-box strong { color: #e85d04; }
.search-bar { display: flex; gap: .5rem; margin-bottom: 1rem; }
.search-bar input { flex: 1; padding: .5rem; border: 1px solid #ccc; border-radius: 4px; }
.search-bar button { padding: .5rem 1rem; background: #1a1a1a; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.1); margin-bottom: 2rem; }
th { background: #1a1a1a; color: #fff; padding: .6rem .8rem; text-align: left; font-size: .82rem; }
td { padding: .5rem .8rem; font-size: .82rem; border-bottom: 1px solid #eee; }
.error { color: #c00; margin-bottom: .8rem; font-size: .9rem; }
</style>
</head>
<body>
<nav>
    <strong>🔥 Flint CRM — Admin</strong>
    <div>
        <a href="/search.php">Customers</a>
        <a href="/import.php">Import</a>
        <a href="/logout.php">Sign out</a>
    </div>
</nav>
<div class="container">
    <h1>Admin Panel</h1>

    <div class="flag-box">
        <strong>FLAG_LOCAL:</strong> <?= htmlspecialchars($flag_local) ?>
    </div>

    <h2>Customer Search (Admin)</h2>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST" class="search-bar">
        <input type="text" name="q" placeholder="Search customers…" value="<?= htmlspecialchars($_POST['q'] ?? '') ?>">
        <button type="submit">Search</button>
    </form>
    <table>
        <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Company</th><th>Owner ID</th></tr>
        <?php foreach ($results as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['id'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['name'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['phone'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['company'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['owner_id'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>All Users</h2>
    <table>
        <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>
        <?php foreach ($all_users as $u): ?>
        <tr>
            <td><?= htmlspecialchars($u['id']) ?></td>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['role']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
