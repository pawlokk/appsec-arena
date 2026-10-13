<?php
session_start();

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: /login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = $_POST['title']   ?? '';
    $content = $_POST['content'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9\-]+$/', $title)) {
        $error = 'Title must be alphanumeric (hyphens allowed).';
    } elseif (strlen($title) < 1 || strlen($title) > 64) {
        $error = 'Title must be between 1 and 64 characters.';
    } elseif (empty($content)) {
        $error = 'Content cannot be empty.';
    } else {
        $filepath = '/var/www/html/pages/' . $title . '.shtml';

        $shtml = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        pre { background: #f4f4f4; padding: 1rem; border-radius: 4px; white-space: pre-wrap; }
        a { color: #4a90e2; }
    </style>
</head>
<body>
    <h1>' . htmlspecialchars($title) . '</h1>
    <div class="content">
' . $content . '
    </div>
    <p><a href="/index.php">&larr; Back to Dashboard</a></p>
</body>
</html>';

        file_put_contents($filepath, $shtml);
        $success = 'Page created: <a href="/pages/' . htmlspecialchars($title) . '.shtml">/pages/' . htmlspecialchars($title) . '.shtml</a>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Imprint – Create Page</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        header { background: #4a90e2; color: #fff; padding: 1rem 2rem; }
        header h1 { margin: 0; font-size: 1.4rem; }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 1.5rem; }
        h2 { margin-top: 0; }
        label { display: block; margin-top: 1rem; font-weight: bold; }
        input[type=text], textarea { width: 100%; padding: 0.5rem; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; margin-top: 0.3rem; }
        textarea { height: 180px; font-family: monospace; }
        button { margin-top: 1rem; padding: 0.6rem 1.4rem; background: #4a90e2; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        button:hover { background: #357abd; }
        .error { color: red; margin-top: 1rem; }
        .success { color: green; margin-top: 1rem; }
        .hint { font-size: 0.85rem; color: #666; margin-top: 0.3rem; }
    </style>
</head>
<body>
    <header><h1>Imprint – Create Page</h1></header>
    <div class="container">
        <div class="card">
            <h2>New Static Page</h2>
            <p>Fill in the form to publish a new page to your site.</p>
            <form method="post" action="/create.php">
                <label for="title">Page Title (slug)</label>
                <input type="text" id="title" name="title" placeholder="my-page" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                <p class="hint">Alphanumeric and hyphens only. This becomes the filename.</p>

                <label for="content">Page Content</label>
                <textarea id="content" name="content" placeholder="HTML content for your page"><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>

                <button type="submit">Create Page</button>
            </form>
            <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>
            <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>
        </div>
    </div>
</body>
</html>
