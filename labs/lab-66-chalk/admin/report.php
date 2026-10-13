<?php
session_start();
require_once __DIR__ . '/../config.php';
require_admin();

$db = get_db();
$users = $db->query("SELECT id, username, display_name FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$selected_user = null;
$articles = [];

if (isset($_GET['user_id'])) {
    $uid = (int)$_GET['user_id'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $selected_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($selected_user) {
        $search = $_GET['search'] ?? '';
        if ($search) {
            $stmt_title = $db->prepare("SELECT * FROM articles WHERE title LIKE ?");
            $stmt_title->execute(["%$search%"]);
            $title_results = $stmt_title->fetchAll(PDO::FETCH_ASSOC);
        }

        $display_name = $selected_user['display_name'];
        $sql = "SELECT * FROM articles WHERE author='" . $display_name . "' ORDER BY created_at DESC";
        try {
            $result = $db->query($sql);
            $articles = $result->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $articles = [];
            $query_failed = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="csrf-protection" content="enabled">
<title>User Report — Chalk Wiki Admin</title>
<style>
body { font-family: sans-serif; background: #f4f6f8; margin: 0; }
nav { background: #2c3e50; color: white; padding: 0.8rem 2rem; display: flex; align-items: center; gap: 1.5rem; }
nav a { color: #ecf0f1; text-decoration: none; }
nav a:hover { text-decoration: underline; }
nav .logo { font-weight: bold; font-size: 1.2rem; margin-right: auto; }
main { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
h1 { color: #2c3e50; }
.box { background: white; padding: 1.5rem 2rem; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 1.5rem; }
table { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 0.5rem 0.8rem; border-bottom: 1px solid #eee; }
th { background: #f8f9fa; }
.user-list { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
.user-list a { display: inline-block; padding: 0.4rem 1rem; background: #ecf0f1; color: #2c3e50; text-decoration: none; border-radius: 4px; border: 1px solid #ddd; }
.user-list a:hover, .user-list a.active { background: #2980b9; color: white; border-color: #2980b9; }
.profile-box { display: flex; gap: 2rem; margin-bottom: 1rem; }
.profile-box div { flex: 1; }
.label { font-size: 0.8rem; color: #999; text-transform: uppercase; margin-bottom: 0.2rem; }
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
    <h1>User Report</h1>
    <div class="box">
        <h2>Select User</h2>
        <div class="user-list">
            <?php foreach ($users as $u): ?>
            <a href="?user_id=<?= (int)$u['id'] ?>" <?= (isset($_GET['user_id']) && (int)$_GET['user_id'] === (int)$u['id']) ? 'class="active"' : '' ?>>
                <?= htmlspecialchars($u['username']) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($selected_user): ?>
    <div class="box">
        <h2>Profile: <?= htmlspecialchars($selected_user['username']) ?></h2>
        <div class="profile-box">
            <div>
                <div class="label">Display Name</div>
                <div><?= htmlspecialchars($selected_user['display_name']) ?></div>
            </div>
            <div>
                <div class="label">Role</div>
                <div><?= htmlspecialchars($selected_user['role']) ?></div>
            </div>
        </div>
        <div>
            <div class="label">Bio</div>
            <div><?= htmlspecialchars($selected_user['bio']) ?></div>
        </div>
    </div>

    <div class="box">
        <h2>Articles by <?= htmlspecialchars($selected_user['display_name']) ?></h2>
        <?php if (isset($query_failed)): ?>
        <p style="color:#c0392b">Query error: <?= htmlspecialchars($query_failed) ?></p>
        <?php elseif (empty($articles)): ?>
        <p>No articles found for this user.</p>
        <?php else: ?>
        <table>
            <tr><th>ID</th><th>Title</th><th>Author</th><th>Created</th></tr>
            <?php foreach ($articles as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['id'] ?? '') ?></td>
                <td><?= htmlspecialchars($a['title'] ?? '') ?></td>
                <td><?= htmlspecialchars($a['author'] ?? '') ?></td>
                <td><?= htmlspecialchars($a['created_at'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
