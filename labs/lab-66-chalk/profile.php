<?php
session_start();
require_once __DIR__ . '/config.php';
require_login();

$c = get_db();
$m = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $n = $_POST['display_name'] ?? '';
    $b = $_POST['bio'] ?? '';

    $s = $c->prepare("UPDATE users SET display_name=?, bio=? WHERE id=?");
    $s->execute([$n, $b, $_SESSION['user_id']]);
    $_SESSION['display_name'] = $n;
    $m = 'Profile updated successfully.';
}

$s = $c->prepare("SELECT * FROM users WHERE id = ?");
$s->execute([$_SESSION['user_id']]);
$u = $s->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="csrf-protection" content="enabled">
<title>Profile — Chalk Wiki</title>
<style>
body { font-family: sans-serif; background: #f4f6f8; margin: 0; }
nav { background: #2c3e50; color: white; padding: 0.8rem 2rem; display: flex; align-items: center; gap: 1.5rem; }
nav a { color: #ecf0f1; text-decoration: none; }
nav a:hover { text-decoration: underline; }
nav .logo { font-weight: bold; font-size: 1.2rem; margin-right: auto; }
main { max-width: 600px; margin: 2rem auto; padding: 0 1rem; }
h1 { color: #2c3e50; }
.box { background: white; padding: 1.5rem 2rem; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
label { display: block; margin-bottom: 0.3rem; font-weight: bold; color: #555; }
input[type=text], textarea { width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; margin-bottom: 1rem; font-family: sans-serif; }
textarea { height: 100px; resize: vertical; }
button { padding: 0.6rem 1.5rem; background: #2980b9; color: white; border: none; border-radius: 4px; cursor: pointer; }
button:hover { background: #2471a3; }
.success { color: #27ae60; margin-bottom: 1rem; }
.hint { font-size: 0.8rem; color: #999; margin-top: -0.8rem; margin-bottom: 1rem; }
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
    <h1>Edit Profile</h1>
    <div class="box">
        <?php if ($m): ?>
        <div class="success"><?= htmlspecialchars($m) ?></div>
        <?php endif; ?>
        <form method="POST">
            <label>Display Name</label>
            <input type="text" name="display_name" maxlength="50" value="<?= htmlspecialchars($u['display_name']) ?>">
            <p class="hint">This name appears on your articles and in the admin user report.</p>
            <label>Bio</label>
            <textarea name="bio"><?= htmlspecialchars($u['bio']) ?></textarea>
            <button type="submit">Save Changes</button>
        </form>
    </div>
</main>
</body>
</html>
