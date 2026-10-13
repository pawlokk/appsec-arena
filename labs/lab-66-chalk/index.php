<?php
session_start();
require_once __DIR__ . '/config.php';
require_login();

$c = get_db();
$r = $c->query("SELECT * FROM articles ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="csrf-protection" content="enabled">
<title>Chalk Wiki</title>
<style>
body { font-family: sans-serif; background: #f4f6f8; margin: 0; }
nav { background: #2c3e50; color: white; padding: 0.8rem 2rem; display: flex; align-items: center; gap: 1.5rem; }
nav a { color: #ecf0f1; text-decoration: none; font-size: 0.95rem; }
nav a:hover { text-decoration: underline; }
nav .logo { font-weight: bold; font-size: 1.2rem; margin-right: auto; }
main { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
h1 { color: #2c3e50; }
.article-card { background: white; border-radius: 8px; padding: 1.2rem 1.5rem; margin-bottom: 1rem; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
.article-card h2 { margin: 0 0 0.4rem; font-size: 1.1rem; }
.article-card h2 a { color: #2980b9; text-decoration: none; }
.article-card h2 a:hover { text-decoration: underline; }
.meta { font-size: 0.85rem; color: #7f8c8d; }
</style>
</head>
<body>
<nav>
    <span class="logo">📝 Chalk Wiki</span>
    <a href="/index.php">Home</a>
    <a href="/articles.php">Articles</a>
    <a href="/profile.php">Profile</a>
    <?php if (is_admin()): ?>
    <a href="/admin/index.php">Admin</a>
    <?php endif; ?>
    <a href="/logout.php">Logout (<?= htmlspecialchars($_SESSION['username']) ?>)</a>
</nav>
<main>
    <h1>Knowledge Base</h1>
    <p>Welcome to the internal company wiki. Browse articles below or update your profile.</p>
    <?php foreach ($r as $v): ?>
    <div class="article-card">
        <h2><a href="/articles.php?id=<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['title']) ?></a></h2>
        <div class="meta">By <?= htmlspecialchars($v['author']) ?> &mdash; <?= htmlspecialchars($v['created_at']) ?></div>
    </div>
    <?php endforeach; ?>
</main>
</body>
</html>
