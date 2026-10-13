<?php
session_start();

define('DB_PATH', __DIR__ . '/prism.db');

function get_pdo() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}

function search_documents($q) {
    $pdo = get_pdo();
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE title LIKE :q OR content LIKE :q");
    $like = '%' . $q . '%';
    $stmt->execute([':q' => $like]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function is_logged_in() {
    return isset($_SESSION['user']);
}

function is_admin() {
    return isset($_SESSION['user']) && $_SESSION['role'] === 'admin';
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: /index.php?page=login');
        exit;
    }
}

function require_admin() {
    if (!is_admin()) {
        http_response_code(403);
        render_page('Access denied. Admin only.', '403 Forbidden');
        exit;
    }
}

function render_page($body, $title = 'Prism DMS') {
    $flag_local = '';
    if (is_admin() && file_exists('/flag_local.txt')) {
        $flag_local = trim(file_get_contents('/flag_local.txt'));
    }
    $nav_user = is_logged_in() ? htmlspecialchars($_SESSION['user']) : '';
    $admin_link = is_admin() ? '<a href="/index.php?page=admin">Admin</a> | ' : '';
    echo <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{$title}</title>
<style>
body { font-family: sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; background: #f5f5f5; }
nav { background: #2c3e50; color: white; padding: 10px 20px; border-radius: 4px; margin-bottom: 20px; }
nav a { color: #ecf0f1; margin-right: 15px; text-decoration: none; }
.card { background: white; padding: 20px; border-radius: 4px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.flag-box { background: #27ae60; color: white; padding: 10px 15px; border-radius: 4px; font-family: monospace; margin-bottom: 15px; }
input, textarea { width: 100%; padding: 8px; margin: 5px 0 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 3px; }
button, input[type=submit] { background: #2980b9; color: white; border: none; padding: 8px 20px; border-radius: 3px; cursor: pointer; }
</style>
</head>
<body>
<nav>
  <a href="/index.php">Prism DMS</a>
  <a href="/index.php?page=search">Search</a>
  <a href="/index.php?page=docs">Documents</a>
  {$admin_link}
HTML;
    if ($nav_user) {
        echo "<a href='/index.php?page=logout'>Logout ({$nav_user})</a>";
    } else {
        echo "<a href='/index.php?page=login'>Login</a>";
    }
    echo "</nav>\n";
    if ($flag_local) {
        echo "<div class='flag-box'>FLAG (Local): {$flag_local}</div>\n";
    }
    echo $body;
    echo "</body></html>";
}

$page = $_GET['page'] ?? 'home';

switch ($page) {
    case 'home':
        ob_start();
        echo "<div class='card'><h2>Welcome to Prism</h2><p>Prism is a secure document management system for internal teams. Use the navigation above to browse and search documents.</p></div>";
        echo "<div class='card'><h3>Recent Documents</h3>";
        $pdo = get_pdo();
        $docs = $pdo->query("SELECT id, title, owner, created_at FROM documents ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($docs as $doc) {
            echo "<p><a href='/index.php?page=doc&id=" . (int)$doc['id'] . "'>" . htmlspecialchars($doc['title']) . "</a> — " . htmlspecialchars($doc['owner']) . "</p>";
        }
        echo "</div>";
        render_page(ob_get_clean());
        break;

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $pdo = get_pdo();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header('Location: /index.php');
                exit;
            }
            $error = "<p style='color:red'>Invalid credentials.</p>";
        }
        $demo_pass = getenv('ALICE_PASSWORD') ?: 'alice123';
        $body = "<div class='card'><h2>Login</h2>" . ($error ?? '') . "
        <form method='post'>
            <label>Username</label><input name='username' type='text' required>
            <label>Password</label><input name='password' type='password' required>
            <button type='submit'>Login</button>
        </form>
        <p style='margin-top:15px;color:#555;font-size:0.9em'>Demo account: <strong>alice</strong> / <strong>" . htmlspecialchars($demo_pass) . "</strong></p>
        </div>";
        render_page($body, 'Login - Prism');
        break;

    case 'logout':
        session_destroy();
        header('Location: /index.php?page=login');
        exit;

    case 'search':
        require('/var/www/html/routes/search.php');
        break;

    case 'doc':
        require_login();
        $id = (int)($_GET['id'] ?? 0);
        $pdo = get_pdo();
        $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doc) {
            render_page("<div class='card'><p>Document not found.</p></div>", 'Not Found');
        } else {
            $body = "<div class='card'><h2>" . htmlspecialchars($doc['title']) . "</h2><p>" . htmlspecialchars($doc['content']) . "</p><p><small>Owner: " . htmlspecialchars($doc['owner']) . " | Created: " . htmlspecialchars($doc['created_at']) . "</small></p></div>";
            render_page($body, htmlspecialchars($doc['title']) . ' - Prism');
        }
        break;

    case 'docs':
        require_login();
        require('/var/www/html/routes/api.php');
        break;

    case 'admin':
        require_login();
        require_admin();
        require('/var/www/html/routes/admin.php');
        break;

    case 'import':
        require_login();
        require_admin();
        require('/var/www/html/routes/import.php');
        break;

    case 'email':
        require_login();
        require_admin();
        require('/var/www/html/routes/email.php');
        break;

    default:
        render_page("<div class='card'><p>Page not found.</p></div>", '404 - Prism');
        break;
}
