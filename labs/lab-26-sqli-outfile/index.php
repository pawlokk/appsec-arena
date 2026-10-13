<?php
session_start();

$host = '127.0.0.1';
$dbname = 'shopdb';
$dbuser = 'fileuser';
$dbpass = 'filepass123';

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $dbuser, $dbpass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>
<!DOCTYPE html>
<html>
<head>
<title>ShopSearch - Product Catalog</title>
<style>
body { font-family: Arial, sans-serif; margin: 0; background: #f5f5f5; }
.header { background: #2c3e50; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
.header a { color: #ecf0f1; text-decoration: none; margin-left: 15px; }
.container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
.search-box { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 25px; }
.search-box input[type=text] { width: 70%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; }
.search-box button { padding: 10px 20px; background: #2980b9; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; margin-left: 8px; }
.results { background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden; }
.result-row { padding: 15px 20px; border-bottom: 1px solid #eee; }
.result-row:last-child { border-bottom: none; }
.result-name { font-weight: bold; color: #2c3e50; font-size: 18px; }
.result-desc { color: #666; margin-top: 4px; }
.result-price { color: #27ae60; font-size: 16px; font-weight: bold; margin-top: 4px; }
.error { background: #e74c3c; color: white; padding: 12px; border-radius: 4px; margin-top: 15px; }
.info { background: #3498db; color: white; padding: 12px; border-radius: 4px; margin-bottom: 15px; }
.no-results { padding: 20px; color: #666; text-align: center; }
.query-display { font-family: monospace; background: #ecf0f1; padding: 8px; border-radius: 4px; font-size: 13px; color: #555; margin-bottom: 10px; }
</style>
</head>
<body>
<div class="header">
  <div><strong>ShopSearch</strong> — Product Catalog</div>
  <div>
    <a href="/">Home</a>
    <a href="/search.php">Search</a>
    <a href="/admin.php">Admin</a>
  </div>
</div>
<div class="container">
  <div class="search-box">
    <h2>Product Search</h2>
    <p>Search our catalog of products.</p>
    <form method="GET" action="/search.php">
      <input type="text" name="q" placeholder="Search products..." value="">
      <button type="submit">Search</button>
    </form>
  </div>
  <div class="results">
    <?php
    $stmt = $pdo->query("SELECT * FROM products LIMIT 10");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($products as $p) {
        echo '<div class="result-row">';
        echo '<div class="result-name">' . htmlspecialchars($p['name']) . '</div>';
        echo '<div class="result-desc">' . htmlspecialchars($p['description']) . '</div>';
        echo '<div class="result-price">$' . htmlspecialchars($p['price']) . '</div>';
        echo '</div>';
    }
    ?>
  </div>
</div>
</body>
</html>
