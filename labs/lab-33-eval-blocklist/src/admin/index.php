<?php
session_start();
if (empty($_SESSION['authenticated'])) {
    header('Location: /login.php');
    exit;
}

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    $expr = $data['expr'] ?? '';

    $blocked = ['system','exec','shell_exec','passthru','popen','proc_open','pcntl_exec','__import__','os.','subprocess'];
    $blocked_hit = false;
    foreach ($blocked as $b) {
        if (stripos($expr, $b) !== false) {
            $blocked_hit = true;
            $error = 'That function is not permitted.';
            break;
        }
    }

    if (!$blocked_hit) {
        ob_start();
        try {
            eval($expr);
        } catch (Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
        $result = ob_get_clean();
    }

    header('Content-Type: application/json');
    echo json_encode(['result' => $result, 'error' => $error]);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Admin - Expression Evaluator</title>
<style>
body { font-family: monospace; background: #1a1a2e; color: #eee; margin:0; padding:2rem; }
h1 { color:#e94560; }
.eval-box { background:#16213e; border:1px solid #0f3460; padding:1.5rem; }
textarea { width:100%; height:80px; background:#0f3460; border:1px solid #e94560; color:#eee; padding:8px; box-sizing:border-box; font-family:monospace; }
button { background:#e94560; color:#fff; border:none; padding:10px 24px; cursor:pointer; margin-top:8px; }
pre { background:#0f3460; padding:1rem; min-height:40px; white-space:pre-wrap; word-break:break-all; color:#0f9; }
.error { color:#e94560; }
a { color:#888; text-decoration:none; font-size:0.85em; }
</style>
</head>
<body>
<h1>Admin Expression Evaluator</h1>
<div class="eval-box">
  <label>PHP Expression (eval'd server-side):</label><br>
  <textarea id="expr" placeholder="echo phpversion();"></textarea><br>
  <button onclick="runEval()">Evaluate</button>
  <h3>Output:</h3>
  <pre id="output">—</pre>
  <div id="err" class="error"></div>
</div>
<br><a href="/logout.php">Logout</a>
<script>
async function runEval() {
  const expr = document.getElementById('expr').value;
  document.getElementById('output').textContent = '...';
  document.getElementById('err').textContent = '';
  const resp = await fetch('/admin/', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({expr})
  });
  const data = await resp.json();
  document.getElementById('output').textContent = data.result ?? '';
  if (data.error) document.getElementById('err').textContent = data.error;
}
</script>
</body>
</html>
