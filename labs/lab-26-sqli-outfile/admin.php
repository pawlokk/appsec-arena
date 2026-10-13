<?php
session_start();

$host = '127.0.0.1';
$dbname = 'shopdb';
$dbuser = 'fileuser';
$dbpass = 'filepass123';

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $dbuser, $dbpass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ? AND role = 'admin'");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['admin'] = true;
        $_SESSION['username'] = $user['username'];
    } else {
        $error = 'Invalid credentials.';
    }
}

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: /admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>ShopSearch - Admin</title>
<style>
body { font-family: Arial, sans-serif; margin: 0; background: #f5f5f5; }
.header { background: #2c3e50; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
.header a { color: #ecf0f1; text-decoration: none; margin-left: 15px; }
.container { max-width: 600px; margin: 60px auto; padding: 0 20px; }
.card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.card h2 { margin-top: 0; color: #2c3e50; }
input[type=text], input[type=password] { width: 100%; padding: 10px; margin: 8px 0 16px; border: 1px solid #ddd; border-radius: 4px; font-size: 15px; box-sizing: border-box; }
button { padding: 10px 24px; background: #2980b9; color: white; border: none; border-radius: 4px; font-size: 15px; cursor: pointer; }
.error { background: #e74c3c; color: white; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
.success { background: #27ae60; color: white; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
.admin-info { color: #666; font-size: 14px; margin-top: 20px; }
</style>
</head>
<body>
<div class="header">
  <div><strong>ShopSearch</strong> — Admin Panel</div>
  <div>
    <a href="/">Home</a>
    <a href="/search.php">Search</a>
    <a href="/admin.php">Admin</a>
  </div>
</div>
<div class="container">
  <?php if (!isset($_SESSION['admin'])): ?>
  <div class="card">
    <h2>Admin Login</h2>
    <?php if ($error): ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST" action="/admin.php">
      <label>Username</label>
      <input type="text" name="username" placeholder="admin">
      <label>Password</label>
      <input type="password" name="password" placeholder="password">
      <button type="submit">Login</button>
    </form>
  </div>
  <?php else: ?>
  <div class="card">
    <h2>Admin Dashboard</h2>
    <div class="success">Logged in as <?php echo htmlspecialchars($_SESSION['username']); ?></div>
    <p>Welcome to the admin panel. You have authenticated successfully.</p>
    <p class="admin-info">Use this panel to manage store settings and review catalog data.</p>
    <form method="POST" style="margin-top:20px;">
      <button name="logout" value="1" style="background:#e74c3c;">Logout</button>
    </form>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
