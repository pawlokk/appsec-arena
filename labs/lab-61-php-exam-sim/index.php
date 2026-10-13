<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$requestUri  = $_SERVER['REQUEST_URI'];
$path        = parse_url($requestUri, PHP_URL_PATH);
$method      = $_SERVER['REQUEST_METHOD'];

// Route: /reset/request
if ($path === '/reset/request') {
    require_once __DIR__ . '/includes/reset.php';
    handleResetRequest();
    exit;
}

// Route: /reset/verify
if ($path === '/reset/verify') {
    require_once __DIR__ . '/includes/reset.php';
    handleResetVerify();
    exit;
}

// Route: /logout
if ($path === '/logout') {
    session_start();
    session_destroy();
    header('Location: /');
    exit;
}

// Route: /upload/avatar
if ($path === '/upload/avatar') {
    require_once __DIR__ . '/includes/upload.php';
    handleAvatarUpload();
    exit;
}

// Route: /admin/panel
if ($path === '/admin/panel') {
    require_once __DIR__ . '/includes/auth.php';
    $user = requireAdmin();
    $pdo = getDB();
    $orders = $pdo->query('SELECT o.id, u.username, o.total, o.created_at FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
    $flag = getenv('FLAG_LOCAL') ?: 'FLAG_LOCAL_NOT_SET';
    $rows = '';
    foreach ($orders as $o) {
        $rows .= '<tr><td>' . $o['id'] . '</td><td>' . htmlspecialchars($o['username']) . '</td><td>$' . number_format($o['total'],2) . '</td><td>' . $o['created_at'] . '</td></tr>';
    }
    $body = '<div class="card"><h2>Admin Panel</h2>
        <div class="alert" style="background:#fff3cd;border:1px solid #ffc107;margin-bottom:1rem;padding:.75rem;border-radius:4px">
            <strong>FLAG:</strong> <code>' . htmlspecialchars($flag) . '</code>
        </div>
        <h3>Recent Orders</h3>
        <table><thead><tr><th>ID</th><th>User</th><th>Total</th><th>Date</th></tr></thead>
        <tbody>' . ($rows ?: '<tr><td colspan="4">No orders yet.</td></tr>') . '</tbody></table></div>';
    echo renderLayout('Admin Panel', $body, $user);
    exit;
}

// Route: /source/classes
if ($path === '/source/classes') {
    header('Content-Type: text/plain');
    $classes = ['Loader', 'Serializer', 'FileManager', 'Executor'];
    foreach ($classes as $cls) {
        $file = __DIR__ . '/classes/' . $cls . '.php';
        echo "// ===== $cls.php =====\n";
        echo file_get_contents($file);
        echo "\n\n";
    }
    exit;
}

// Route: /source/reset
if ($path === '/source/reset') {
    header('Content-Type: text/plain');
    echo file_get_contents(__DIR__ . '/includes/reset.php');
    exit;
}

// Page-based routing via ?page=
$page = $_GET['page'] ?? 'home';

if ($page === 'profile') {
    require_once __DIR__ . '/includes/profile.php';
    handleProfile();
    exit;
}

if ($page === 'login') {
    if ($method === 'POST') {
        session_start();
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            header('Location: /');
            exit;
        }
        $body = '<div class="card"><h2>Login</h2><p class="error">Invalid credentials.</p>' . loginForm() . '</div>';
        echo renderLayout('Login', $body);
        exit;
    }
    $body = '<div class="card"><h2>Login</h2>' . loginForm() . '</div>';
    echo renderLayout('Login', $body);
    exit;
}

if ($page === 'register') {
    if ($method === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$username || !$email || !$password) {
            $body = '<div class="card"><h2>Register</h2><p class="error">All fields required.</p>' . registerForm() . '</div>';
            echo renderLayout('Register', $body);
            exit;
        }
        $pdo = getDB();
        $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $body = '<div class="card"><h2>Register</h2><p class="error">Email already registered.</p>' . registerForm() . '</div>';
            echo renderLayout('Register', $body);
            exit;
        }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)')->execute([$username, $email, $hash]);
        session_start();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['user_id'] = $user['id'];
        header('Location: /');
        exit;
    }
    $body = '<div class="card"><h2>Register</h2>' . registerForm() . '</div>';
    echo renderLayout('Register', $body);
    exit;
}

if ($page === 'reset') {
    $body = '<div class="card"><h2>Password Reset</h2>
        <h3>Step 1 — Request Token</h3>
        <form id="reqForm">
            <label>Email</label><input name="email" type="email" required>
            <button type="submit" class="btn btn-primary" style="margin-top:.5rem">Send Token</button>
        </form>
        <div id="reqResult" style="margin-top:.5rem"></div>
        <hr style="margin:1.5rem 0">
        <h3>Step 2 — Use Token</h3>
        <form id="verForm">
            <label>Email</label><input name="email" type="email" required>
            <label>Token</label><input name="token" required>
            <label>New Password</label><input name="password" type="password" required>
            <button type="submit" class="btn btn-secondary" style="margin-top:.5rem">Reset Password</button>
        </form>
        <div id="verResult" style="margin-top:.5rem"></div>
        <script>
        document.getElementById("reqForm").addEventListener("submit", async e => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const r = await fetch("/reset/request",{method:"POST",body:fd});
            const j = await r.json();
            document.getElementById("reqResult").innerHTML = "<p class=\\"success\\">" + JSON.stringify(j) + "</p>";
        });
        document.getElementById("verForm").addEventListener("submit", async e => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const r = await fetch("/reset/verify",{method:"POST",body:fd});
            const j = await r.json();
            const cls = j.error ? "error" : "success";
            document.getElementById("verResult").innerHTML = "<p class=\\""+cls+"\\">" + JSON.stringify(j) + "</p>";
        });
        </script>
    </div>';
    echo renderLayout('Password Reset', $body);
    exit;
}

if ($page === 'cart') {
    $user = getCurrentUser();
    $body = '<div class="card"><h2>Shopping Cart</h2><p>Your cart is empty.</p><a href="/" class="btn btn-primary">Continue Shopping</a></div>';
    echo renderLayout('Cart', $body, $user);
    exit;
}

// Home — product listing
$user = getCurrentUser();
$pdo  = getDB();
$products = $pdo->query('SELECT * FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$cards = '';
foreach ($products as $p) {
    $cards .= '<div class="product-card">
        <div style="height:140px;background:#e0e0e0;border-radius:4px;margin-bottom:.5rem;display:flex;align-items:center;justify-content:center;font-size:2rem">' . htmlspecialchars($p['emoji']) . '</div>
        <h3>' . htmlspecialchars($p['name']) . '</h3>
        <p style="font-size:.85rem;color:#666;margin:.3rem 0">' . htmlspecialchars($p['description']) . '</p>
        <p class="price">$' . number_format($p['price'],2) . '</p>
        <button class="btn btn-primary btn-sm" style="margin-top:.5rem">Add to Cart</button>
    </div>';
}
$hero = '<div class="card" style="background:linear-gradient(135deg,#1a1a2e,#e94560);color:#fff;margin-bottom:1.5rem">
    <h1 style="font-size:2rem">Welcome to Solvent Shop</h1>
    <p style="margin:.5rem 0">Premium laboratory supplies and chemical solvents.</p>
    ' . ($user ? '' : '<a href="/?page=login" class="btn" style="background:#fff;color:#e94560;margin-top:.5rem">Sign In</a> <a href="/?page=register" class="btn" style="background:transparent;border:2px solid #fff;color:#fff;margin-top:.5rem">Register</a>') . '
</div>';
$body = $hero . '<h2 style="margin-bottom:1rem">Featured Products</h2><div class="product-grid">' . $cards . '</div>';
echo renderLayout('Home', $body, $user);

function loginForm() {
    return '<form method="post">
        <label>Email</label><input name="email" type="email" required>
        <label>Password</label><input name="password" type="password" required>
        <button type="submit" class="btn btn-primary" style="margin-top:.5rem">Login</button>
        <p style="margin-top:.5rem"><a href="/?page=reset">Forgot password?</a> &nbsp; <a href="/?page=register">Create account</a></p>
    </form>';
}

function registerForm() {
    return '<form method="post">
        <label>Username</label><input name="username" required>
        <label>Email</label><input name="email" type="email" required>
        <label>Password</label><input name="password" type="password" required>
        <button type="submit" class="btn btn-primary" style="margin-top:.5rem">Register</button>
        <p style="margin-top:.5rem"><a href="/?page=login">Already have an account?</a></p>
    </form>';
}
