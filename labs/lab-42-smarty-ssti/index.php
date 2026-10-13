<?php
require_once '/var/www/html/vendor/autoload.php';

session_start();

function render_page($title, $body) {
    echo "<!DOCTYPE html>
<html>
<head><title>{$title}</title>
<style>
body { font-family: monospace; background: #1a1a2e; color: #e0e0e0; margin: 0; padding: 20px; }
.container { max-width: 800px; margin: 40px auto; }
h1 { color: #e94560; }
input, textarea { background: #16213e; color: #e0e0e0; border: 1px solid #e94560; padding: 8px; width: 100%; box-sizing: border-box; margin: 5px 0; }
button { background: #e94560; color: white; border: none; padding: 10px 20px; cursor: pointer; }
.output { background: #0f3460; padding: 15px; margin: 20px 0; white-space: pre-wrap; font-family: monospace; }
a { color: #e94560; }
</style>
</head>
<body>
<div class='container'>
{$body}
</div>
</body>
</html>";
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $admin_user = getenv('ADMIN_USER') ?: 'admin';
        $admin_pass = getenv('ADMIN_PASS') ?: '';
        if ($admin_pass !== '' && $username === $admin_user && $password === $admin_pass) {
            $_SESSION['logged_in'] = true;
            header('Location: /');
            exit;
        } else {
            render_page('Login', '<h1>Mold</h1><p style="color:#e94560">Invalid credentials.</p>
<form method="POST" action="/login">
<input type="text" name="username" placeholder="Username"><br>
<input type="password" name="password" placeholder="Password"><br>
<button type="submit">Login</button>
</form>');
            exit;
        }
    }
    render_page('Login', '<h1>Mold</h1><p>Theming System Login</p>
<form method="POST" action="/login">
<input type="text" name="username" placeholder="Username"><br>
<input type="password" name="password" placeholder="Password"><br>
<button type="submit">Login</button>
</form>');
    exit;
}

if (empty($_SESSION['logged_in'])) {
    header('Location: /login');
    exit;
}

if ($path === '/theme/preview' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $template = $input['template'] ?? '';

    $smarty = new Smarty();
    $smarty->setTemplateDir('/tmp/smarty_templates');
    $smarty->setCompileDir('/tmp/smarty_compile');
    $smarty->setCacheDir('/tmp/smarty_cache');
    $smarty->setConfigDir('/tmp/smarty_config');
    $smarty->php_handling = Smarty::PHP_ALLOW;

    header('Content-Type: application/json');
    try {
        $output = $smarty->fetch('string:' . $template);
        echo json_encode(['output' => $output]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($path === '/logout') {
    session_destroy();
    header('Location: /login');
    exit;
}

render_page('Mold - Theming System', '<h1>Mold</h1>
<p>Welcome to the Mold theming engine. Preview and test custom templates using the Smarty rendering engine.</p>
<p><a href="/logout">Logout</a></p>
<h2>Template Preview</h2>
<p>Submit a Smarty template string to preview how it renders on your storefront.</p>
<textarea id="tmpl" rows="6" placeholder="Enter your template here"></textarea><br>
<button onclick="previewTemplate()">Preview</button>
<div class="output" id="out" style="display:none"></div>
<script>
function previewTemplate() {
    var tmpl = document.getElementById("tmpl").value;
    fetch("/theme/preview", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({template: tmpl})
    }).then(r => r.json()).then(d => {
        var out = document.getElementById("out");
        out.style.display = "block";
        out.textContent = d.output || d.error || "";
    });
}
</script>');
