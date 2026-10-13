<?php
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $valid_user = getenv('APP_USER');
    $valid_pass = getenv('APP_PASS');
    if (!$valid_user || !$valid_pass) { http_response_code(503); die('Service unavailable'); }

    if ($username === $valid_user && $password === $valid_pass) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        header('Location: /index.php');
        exit;
    } else {
        $error = 'Invalid credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Imprint – Login</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); width: 320px; }
        h2 { margin-top: 0; }
        input[type=text], input[type=password] { width: 100%; padding: 0.5rem; margin: 0.5rem 0 1rem; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; padding: 0.6rem; background: #4a90e2; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        button:hover { background: #357abd; }
        .error { color: red; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Imprint</h2>
        <p>Static Site Generator</p>
        <p style="font-size:0.85rem;color:#555;">Want a preview? <a href="/info.shtml">View site info</a> (no login required).</p>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post" action="/login.php">
            <label>Username</label>
            <input type="text" name="username">
            <label>Password</label>
            <input type="password" name="password">
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
