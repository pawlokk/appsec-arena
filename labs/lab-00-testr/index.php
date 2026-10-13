<?php
session_start();

$flag_local = file_exists('/app/flag_local.txt') ? trim(file_get_contents('/app/flag_local.txt')) : 'ARENA{LOCAL_dev}';

$error = '';
$cmd_output = null;

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /');
    exit;
}

// Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    if ($u === 'admin' && $p === 'admin') {
        $_SESSION['user'] = 'admin';
        header('Location: /');
        exit;
    }
    $error = 'Invalid credentials';
}

// Command execution (authenticated only)
if ($_SESSION['user'] === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
    $cmd_output = shell_exec($cmd . ' 2>&1');
}

$logged_in = isset($_SESSION['user']) && $_SESSION['user'] === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Intro — AppSec Arena</title>
  <style>
    body { font-family: monospace; background: #0d1117; color: #c9d1d9; margin: 0; padding: 2rem; }
    h1   { color: #58a6ff; margin-bottom: .25rem; }
    .sub { color: #8b949e; margin-bottom: 2rem; font-size: .9rem; }
    .card { background: #161b22; border: 1px solid #30363d; border-radius: 6px; padding: 1.5rem; max-width: 480px; margin-bottom: 1.5rem; }
    label { display: block; margin-bottom: .25rem; color: #8b949e; font-size: .85rem; }
    input[type=text], input[type=password], textarea {
      width: 100%; box-sizing: border-box;
      background: #0d1117; border: 1px solid #30363d; color: #c9d1d9;
      padding: .5rem .75rem; border-radius: 4px; font-family: monospace; font-size: .95rem;
      margin-bottom: .75rem;
    }
    textarea { height: 2.5rem; resize: vertical; }
    button { background: #238636; color: #fff; border: none; padding: .5rem 1.25rem;
             border-radius: 4px; cursor: pointer; font-family: monospace; font-size: .95rem; }
    button:hover { background: #2ea043; }
    .btn-logout { background: #21262d; border: 1px solid #30363d; }
    .btn-logout:hover { background: #30363d; }
    .flag { background: #0d1117; border: 1px solid #388bfd; color: #58a6ff;
            padding: .5rem .75rem; border-radius: 4px; font-size: .9rem; margin-top: .5rem; word-break: break-all; }
    .error { color: #f85149; margin-bottom: .75rem; font-size: .9rem; }
    .output { background: #0d1117; border: 1px solid #30363d; padding: .75rem; border-radius: 4px;
              white-space: pre-wrap; font-size: .85rem; margin-top: .75rem; max-height: 300px; overflow-y: auto; }
    .tag { display: inline-block; background: #1f6feb; color: #cae8ff; font-size: .75rem;
           padding: .15rem .5rem; border-radius: 12px; margin-right: .35rem; }
  </style>
</head>
<body>

<h1>Intro</h1>
<p class="sub">L0 · PHP · Introductory lab — hardcoded credentials, direct command executor.</p>

<?php if (!$logged_in): ?>

<div class="card">
  <p style="margin-top:0;color:#8b949e;font-size:.9rem;">
    This is an introductory lab. Read the source, find the credentials, gain access.
  </p>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST">
    <label>Username</label>
    <input type="text" name="username" autocomplete="off" autofocus>
    <label>Password</label>
    <input type="password" name="password">
    <button type="submit">Login</button>
  </form>
</div>

<?php else: ?>

<div class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
    <span>Logged in as <strong>admin</strong></span>
    <a href="/?logout=1"><button class="btn-logout" type="button">Logout</button></a>
  </div>
  <p style="margin:0 0 .5rem;color:#8b949e;font-size:.85rem;">FLAG_LOCAL — authenticated read</p>
  <div class="flag"><?= htmlspecialchars($flag_local) ?></div>
</div>

<div class="card">
  <p style="margin:0 0 .75rem;color:#8b949e;font-size:.85rem;">
    Command Executor — server-side shell. Run <code>id</code>, <code>cat /flag_proof.txt</code>, etc.
    <br><span style="color:#f0883e;font-size:.8rem;">⚠ This is the vulnerability. In a real app this would be hidden.</span>
  </p>
  <form method="POST">
    <label>Command</label>
    <textarea name="cmd" autofocus><?= isset($_POST['cmd']) ? htmlspecialchars($_POST['cmd']) : '' ?></textarea>
    <button type="submit">Execute</button>
  </form>
  <?php if ($cmd_output !== null): ?>
    <div class="output"><?= htmlspecialchars($cmd_output) ?></div>
  <?php endif; ?>
</div>

<div style="color:#8b949e;font-size:.8rem;margin-top:1rem;">
  <span class="tag">Auth Bypass</span>
  <span class="tag">Command Injection</span>
  <span class="tag">RCE</span>
  &nbsp;Vulnerability chain: hardcoded creds → authenticated shell → RCE
</div>

<?php endif; ?>

</body>
</html>
