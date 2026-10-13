<?php
require_once __DIR__ . '/db.php';

session_start();

function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) return null;
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function requireLogin() {
    $user = getCurrentUser();
    if (!$user) {
        header('Location: /?page=login');
        exit;
    }
    return $user;
}

function requireAdmin() {
    $user = requireLogin();
    if (!$user['is_admin']) {
        http_response_code(403);
        echo renderLayout('Access Denied', '<p class="error">Administrators only.</p>');
        exit;
    }
    return $user;
}

function renderLayout($title, $body, $user = null) {
    if ($user === null) $user = getCurrentUser();
    $nav = '<nav class="navbar">
        <a href="/" class="brand">Solvent Shop</a>
        <div class="nav-links">';
    if ($user) {
        $nav .= '<a href="/?page=profile">Profile</a>';
        if ($user['is_admin']) $nav .= '<a href="/admin/panel">Admin</a>';
        $nav .= '<a href="/logout">Logout</a>';
    } else {
        $nav .= '<a href="/?page=login">Login</a><a href="/?page=register">Register</a>';
    }
    $nav .= '</div></nav>';
    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . htmlspecialchars($title) . ' — Solvent Shop</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#f5f5f5;color:#222}
.navbar{background:#1a1a2e;color:#fff;padding:1rem 2rem;display:flex;justify-content:space-between;align-items:center}
.navbar a{color:#eee;text-decoration:none;margin-left:1rem}
.brand{font-size:1.3rem;font-weight:700;color:#e94560!important}
.container{max-width:1100px;margin:2rem auto;padding:0 1rem}
.card{background:#fff;border-radius:8px;padding:1.5rem;margin-bottom:1rem;box-shadow:0 1px 4px rgba(0,0,0,.1)}
.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem}
.product-card{background:#fff;border-radius:8px;padding:1rem;box-shadow:0 1px 4px rgba(0,0,0,.1);text-align:center}
.product-card h3{margin:.5rem 0}
.product-card .price{color:#e94560;font-weight:700;font-size:1.1rem}
.btn{display:inline-block;padding:.5rem 1.2rem;border-radius:4px;border:none;cursor:pointer;font-size:1rem;text-decoration:none}
.btn-primary{background:#e94560;color:#fff}
.btn-secondary{background:#1a1a2e;color:#fff}
.btn-sm{padding:.3rem .8rem;font-size:.9rem}
form label{display:block;margin:.5rem 0 .2rem}
form input,form textarea,form select{width:100%;padding:.5rem;border:1px solid #ccc;border-radius:4px;font-size:1rem}
form input[type=submit],form button{width:auto}
.error{color:#c0392b;background:#fdecea;padding:.5rem;border-radius:4px;margin:.5rem 0}
.success{color:#27ae60;background:#eafaf1;padding:.5rem;border-radius:4px;margin:.5rem 0}
.alert{padding:.75rem;border-radius:4px;margin:.5rem 0}
table{width:100%;border-collapse:collapse}
th,td{padding:.6rem;border:1px solid #ddd;text-align:left}
th{background:#f0f0f0}
</style>
</head><body>' . $nav . '<div class="container">' . $body . '</div></body></html>';
}
