<?php
require_once __DIR__ . '/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$r = [];
$e = '';

if (isset($_GET['q'])) {
    $q = $_GET['q'];
    if (preg_match('/union|select|insert|update|delete|drop|exec|--|\/\*/i', $q)) {
        $e = 'Invalid search term.';
        $q = '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $q   = $_POST['q'] ?? '';
    $uid = $_SESSION['user_id'];
    $c   = get_db();

    $sq = "SELECT * FROM customers WHERE name LIKE '%" . $q . "%' AND owner_id = " . $uid;
    try {
        $s = $c->query($sq);
        $r = $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $ex) {
        $e = 'Query error: ' . $ex->getMessage();
    }
} elseif (isset($_GET['q']) && !$e) {
    $q   = $_GET['q'] ?? '';
    $uid = $_SESSION['user_id'];
    $c   = get_db();
    $sq  = "SELECT * FROM customers WHERE name LIKE '%" . $q . "%' AND owner_id = " . $uid;
    try {
        $s = $c->query($sq);
        $r = $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $ex) {
        $e = 'Query error: ' . $ex->getMessage();
    }
} else {
    $c  = get_db();
    $s  = $c->prepare('SELECT * FROM customers WHERE owner_id = ?');
    $s->execute([$_SESSION['user_id']]);
    $r = $s->fetchAll(PDO::FETCH_ASSOC);
}

$ia = ($_SESSION['role'] === 'admin');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Flint CRM — Customer Search</title>
<style>
body { font-family: Arial, sans-serif; background: #f0f2f5; margin: 0; }
nav { background: #e85d04; color: #fff; padding: .8rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
nav a { color: #fff; text-decoration: none; margin-left: 1rem; }
.container { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
h1 { font-size: 1.4rem; color: #333; }
.search-bar { display: flex; gap: .5rem; margin-bottom: 1.5rem; }
.search-bar input { flex: 1; padding: .5rem; border: 1px solid #ccc; border-radius: 4px; }
.search-bar button { padding: .5rem 1rem; background: #e85d04; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
th { background: #333; color: #fff; padding: .6rem .8rem; text-align: left; font-size: .85rem; }
td { padding: .55rem .8rem; font-size: .85rem; border-bottom: 1px solid #eee; }
.error { color: #c00; margin-bottom: 1rem; }
</style>
</head>
<body>
<nav>
    <strong>🔥 Flint CRM</strong>
    <div>
        <?php if ($ia): ?><a href="/admin.php">Admin Panel</a><?php endif; ?>
        <a href="/import.php">Import</a>
        <a href="/logout.php">Sign out (<?= htmlspecialchars($_SESSION['username']) ?>)</a>
    </div>
</nav>
<div class="container">
    <h1>Customers</h1>
    <?php if ($e): ?><div class="error"><?= htmlspecialchars($e) ?></div><?php endif; ?>
    <form method="POST" class="search-bar">
        <input type="text" name="q" placeholder="Search by name…" value="<?= htmlspecialchars($_POST['q'] ?? $_GET['q'] ?? '') ?>">
        <button type="submit">Search</button>
    </form>
    <table>
        <tr><th>Name</th><th>Email</th><th>Phone</th><th>Company</th><th>Notes</th></tr>
        <?php foreach ($r as $v): ?>
        <tr>
            <td><?= htmlspecialchars($v['name'] ?? '') ?></td>
            <td><?= htmlspecialchars($v['email'] ?? '') ?></td>
            <td><?= htmlspecialchars($v['phone'] ?? '') ?></td>
            <td><?= htmlspecialchars($v['company'] ?? '') ?></td>
            <td><?= htmlspecialchars($v['notes'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$r): ?>
        <tr><td colspan="5" style="text-align:center;color:#888">No customers found.</td></tr>
        <?php endif; ?>
    </table>
</div>
</body>
</html>
