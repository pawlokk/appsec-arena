<?php
spl_autoload_register(function($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

class User {
    public $username;
    public $role;
    public function __construct($username, $role = 'user') {
        $this->username = $username;
        $this->role = $role;
    }
}

$flag_local  = trim(getenv('FLAG_LOCAL')  ?: file_get_contents('/flag_local.txt'));
$admin_user  = getenv('ADMIN_USER')  ?: 'admin';
$admin_pass  = getenv('ADMIN_PASS')  ?: '';

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/login' && $method === 'POST') {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';

    if ($admin_pass !== '' && $user === $admin_user && $pass === $admin_pass) {
        $userObj    = new User($user, 'admin');
        $serialized = base64_encode(serialize($userObj));
        setcookie('session', $serialized, 0, '/');
        header('Location: /profile');
        exit;
    } else {
        http_response_code(401);
        echo '<html><body><h2>Invalid credentials</h2><a href="/">Back</a></body></html>';
        exit;
    }
}

if ($uri === '/profile' && $method === 'GET') {
    if (!isset($_COOKIE['session'])) {
        header('Location: /');
        exit;
    }
    $cookie = $_COOKIE['session'];
    $obj = unserialize(base64_decode($cookie));
    echo '<html><body>';
    echo '<h2>Profile</h2>';
    if ($obj instanceof User) {
        echo '<p>Welcome, ' . htmlspecialchars($obj->username) . '!</p>';
        echo '<p>Role: ' . htmlspecialchars($obj->role) . '</p>';
        if ($obj->role === 'admin') {
            if (file_exists('/tmp/pwned')) {
                echo '<p>Flag: <code>' . htmlspecialchars($flag_local) . '</code></p>';
            } else {
                echo '<p>Access to sensitive data requires additional verification.</p>';
            }
        }
    }
    echo '<hr><a href="/source">View Source</a> | <a href="/">Home</a>';
    echo '</body></html>';
    exit;
}

if ($uri === '/source' && $method === 'GET') {
    $files = [
        'index.php'           => __DIR__ . '/index.php',
        'classes/Shuttle.php' => __DIR__ . '/classes/Shuttle.php',
        'classes/Relay.php'   => __DIR__ . '/classes/Relay.php',
        'classes/Terminal.php'=> __DIR__ . '/classes/Terminal.php',
    ];
    echo '<html><body><h2>Source Code</h2>';
    foreach ($files as $name => $path) {
        echo '<h3>' . htmlspecialchars($name) . '</h3>';
        echo '<pre>' . htmlspecialchars(file_get_contents($path)) . '</pre>';
    }
    echo '<a href="/">Home</a></body></html>';
    exit;
}

echo '<!DOCTYPE html>
<html>
<head><title>Cascade - AppSec Arena</title></head>
<body>
<h1>Cascade</h1>
<p>Login to access your profile.</p>
<form method="POST" action="/login">
  <label>Username: <input type="text" name="user"></label><br><br>
  <label>Password: <input type="password" name="pass"></label><br><br>
  <input type="submit" value="Login">
</form>
<hr>
<p><a href="/source">View Source</a></p>
</body>
</html>';
