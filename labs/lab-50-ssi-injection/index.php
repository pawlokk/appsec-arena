<?php
session_start();

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: /login.php');
    exit;
}

$pages = glob('/var/www/html/pages/*.shtml') ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Imprint – Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        header { background: #4a90e2; color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        header h1 { margin: 0; font-size: 1.4rem; }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 1.5rem; margin-bottom: 1.5rem; }
        h2 { margin-top: 0; }
        a.btn { display: inline-block; padding: 0.5rem 1rem; background: #4a90e2; color: #fff; text-decoration: none; border-radius: 4px; margin-top: 0.5rem; }
        a.btn:hover { background: #357abd; }
        ul { padding-left: 1.2rem; }
        li { margin: 0.4rem 0; }
        .logout { color: #fff; text-decoration: none; font-size: 0.9rem; }
    </style>
</head>
<body>
    <header>
        <h1>Imprint – Static Site Generator</h1>
        <a href="/logout.php" class="logout">Logout (<?= htmlspecialchars($_SESSION['username']) ?>)</a>
    </header>
    <div class="container">
        <div class="card">
            <h2>Create a New Page</h2>
            <p>Generate a static page from your content. Pages are served directly by Apache.</p>
            <a href="/create.php" class="btn">New Page</a>
        </div>
        <div class="card">
            <h2>Published Pages</h2>
            <?php if (empty($pages)): ?>
                <p>No pages yet. Create one above.</p>
            <?php else: ?>
                <ul>
                <?php foreach ($pages as $page):
                    $name = basename($page, '.shtml');
                ?>
                    <li><a href="/pages/<?= htmlspecialchars($name) ?>.shtml"><?= htmlspecialchars($name) ?></a></li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
