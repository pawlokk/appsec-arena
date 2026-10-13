<?php
/**
 * Meridian Commerce - Product Catalog & Admin Portal
 */

$db_host = 'localhost';
$db_user = 'appuser';
$db_pass = 'apppass';
$db_name = 'meridian';
$db_sock = '/run/mysqld/mysqld.sock';

function get_db() {
    global $db_host, $db_user, $db_pass, $db_name, $db_sock;
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, 3306, $db_sock);
    if ($conn->connect_error) die("Connection failed");
    return $conn;
}

session_start();

$action = $_GET['action'] ?? 'catalog';

function html_head($title) { ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($title) ?> — Meridian Commerce</title>
<style>
* { box-sizing: border-box; }
body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; color: #333; }
.nav { background: #1a1a2e; padding: 12px 24px; display: flex; gap: 20px; align-items: center; }
.nav a { color: #ccc; text-decoration: none; font-size: 14px; }
.nav .brand { color: #fff; font-weight: bold; font-size: 16px; margin-right: 20px; }
.container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
.card { background: #fff; border-radius: 6px; padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
.btn { display: inline-block; padding: 8px 18px; background: #1a1a2e; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; text-decoration: none; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
th { background: #f0f0f0; }
input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
.alert-error { padding: 10px; background: #fdecea; color: #b71c1c; border-radius: 4px; margin-bottom: 16px; }
.alert-success { padding: 10px; background: #e8f5e9; color: #1b5e20; border-radius: 4px; margin-bottom: 16px; }
pre { background: #1e1e2e; color: #cdd6f4; padding: 16px; border-radius: 6px; font-size: .9rem; }
</style>
</head>
<body>
<div class="nav">
  <span class="brand">&#9670; Meridian</span>
  <a href="?action=catalog">Catalog</a>
  <a href="?action=search">Search</a>
  <?php if (!empty($_SESSION['user'])): ?>
    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
    <a href="?action=admin">Admin</a>
    <?php endif; ?>
    <a href="?action=logout">Logout</a>
  <?php else: ?>
    <a href="?action=login">Login</a>
  <?php endif; ?>
</div>
<div class="container">
<?php }

function html_foot() { ?>
</div></body></html>
<?php }

// ── Catalog ──────────────────────────────────────────────────────────────────
if ($action === 'catalog') {
    html_head('Catalog');
    $db = get_db();
    $res = $db->query("SELECT id, name, price, category FROM products ORDER BY id LIMIT 20");
    echo '<div class="card"><h2>Product Catalog</h2><table>';
    echo '<tr><th>ID</th><th>Product</th><th>Price</th><th>Category</th></tr>';
    while ($row = $res->fetch_assoc()) {
        echo "<tr><td>{$row['id']}</td><td>" . htmlspecialchars($row['name']) .
             "</td><td>\${$row['price']}</td><td>" . htmlspecialchars($row['category']) . "</td></tr>";
    }
    echo '</table></div>';
    html_foot();
    exit;
}

// ── Search ────────────────────────────────────────────────────────────────────
if ($action === 'search') {
    html_head('Search');
    $db = get_db();
    $q = $_GET['q'] ?? '';
    $results = [];
    if ($q !== '') {
        // Search on product name
        $sql = "SELECT id, name, price, category FROM products WHERE name LIKE '%" . $q . "%' ORDER BY id";
        $res = $db->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) $results[] = $row;
        }
    }
    echo '<div class="card"><h2>Product Search</h2>';
    echo '<form method="GET"><input type="hidden" name="action" value="search">';
    echo '<input name="q" placeholder="Search products..." value="' . htmlspecialchars($q) . '" style="width:300px"> ';
    echo '<button class="btn" type="submit">Search</button></form>';
    if ($q !== '') {
        echo '<br><table><tr><th>ID</th><th>Product</th><th>Price</th><th>Category</th></tr>';
        foreach ($results as $row) {
            echo "<tr><td>{$row['id']}</td><td>" . htmlspecialchars($row['name']) .
                 "</td><td>\${$row['price']}</td><td>" . htmlspecialchars($row['category']) . "</td></tr>";
        }
        if (empty($results)) echo '<tr><td colspan="4">No results.</td></tr>';
        echo '</table>';
    }
    echo '</div>';
    html_foot();
    exit;
}

// ── Login ─────────────────────────────────────────────────────────────────────
if ($action === 'login') {
    html_head('Login');
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db = get_db();
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        // Authenticate user
        $stmt = $db->prepare("SELECT id, username, role FROM users WHERE username = ? AND password = SHA2(?, 256)");
        $stmt->bind_param('ss', $username, $password);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($user = $res->fetch_assoc()) {
            $_SESSION['user'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header('Location: ?action=admin');
            exit;
        }
        $error = 'Invalid credentials.';
    }
    echo '<div class="card" style="max-width:420px;margin:auto;">';
    echo '<h2>Admin Login</h2>';
    if ($error) echo '<div class="alert-error">' . htmlspecialchars($error) . '</div>';
    echo '<form method="POST">';
    echo '<label>Username</label><br><input name="username" style="width:100%;margin:6px 0 14px"><br>';
    echo '<label>Password</label><br><input name="password" type="password" style="width:100%;margin:6px 0 14px"><br>';
    echo '<button class="btn" type="submit">Login</button></form></div>';
    html_foot();
    exit;
}

// ── Logout ────────────────────────────────────────────────────────────────────
if ($action === 'logout') {
    session_destroy();
    header('Location: ?action=login');
    exit;
}

// ── Admin ─────────────────────────────────────────────────────────────────────
if ($action === 'admin') {
    if (empty($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ?action=login');
        exit;
    }
    html_head('Admin');
    $flag = file_exists('/flag_local.txt') ? trim(file_get_contents('/flag_local.txt')) : 'FLAG_LOCAL not set';
    echo '<div class="card">';
    echo '<h2>Admin Panel</h2>';
    echo '<p>Logged in as <strong>' . htmlspecialchars($_SESSION['user']) . '</strong></p>';
    echo '<div class="alert-success">Access granted.</div>';
    echo '<pre>' . htmlspecialchars($flag) . '</pre>';
    echo '</div>';

    // Template upload for report generation
    echo '<div class="card">';
    echo '<h2>Report Generator</h2>';
    echo '<p>Upload a PHP report template to generate a product report.</p>';
    echo '<form method="POST" action="?action=upload" enctype="multipart/form-data">';
    echo '<input type="file" name="template"><br><br>';
    echo '<button class="btn" type="submit">Upload & Run</button></form>';
    echo '</div>';
    html_foot();
    exit;
}

// ── Upload / RCE ──────────────────────────────────────────────────────────────
if ($action === 'upload') {
    if (empty($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ?action=login');
        exit;
    }
    $upload_dir = '/var/www/html/reports/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    if (!empty($_FILES['template']['tmp_name'])) {
        $filename = basename($_FILES['template']['name']);
        $dest = $upload_dir . $filename;
        move_uploaded_file($_FILES['template']['tmp_name'], $dest);

        html_head('Report Output');
        echo '<div class="card"><h2>Report Output</h2><pre>';
        ob_start();
        include $dest;
        $out = ob_get_clean();
        echo htmlspecialchars($out);
        echo '</pre></div>';
        html_foot();
    } else {
        header('Location: ?action=admin');
    }
    exit;
}

html_head('Home');
echo '<div class="card"><h2>Welcome to Meridian Commerce</h2>';
echo '<p>Browse our product catalog or <a href="?action=login">log in</a> to access the admin panel.</p>';
echo '</div>';
html_foot();
