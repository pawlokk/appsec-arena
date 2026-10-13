<?php
if (!isset($_GET['file'])) {
    http_response_code(400);
    echo "<h2>Error: missing 'file' parameter</h2>";
    echo "<p>Usage: /view?file=docs/readme.txt</p>";
    exit;
}

$base = '/var/www/html/';
$file = $_GET['file'];

$full_path = $base . $file;

if (!file_exists($full_path)) {
    http_response_code(404);
    echo "<!DOCTYPE html><html><head><title>DocViewer</title>";
    echo "<style>body{font-family:monospace;background:#1a1a2e;color:#e0e0e0;padding:20px;}h2{color:#e74c3c;}</style>";
    echo "</head><body><h2>File not found: " . htmlspecialchars($file) . "</h2>";
    echo "<p><a style='color:#00d4aa' href='/'>Back</a></p></body></html>";
    exit;
}

$content = file_get_contents($full_path);

?>
<!DOCTYPE html>
<html>
<head>
    <title>DocViewer - <?php echo htmlspecialchars(basename($file)); ?></title>
    <style>
        body { font-family: monospace; background: #1a1a2e; color: #e0e0e0; padding: 20px; }
        h1 { color: #00d4aa; }
        .card { background: #16213e; border: 1px solid #0f3460; padding: 20px; border-radius: 4px; margin: 20px 0; }
        a { color: #00d4aa; }
        pre { background: #0f0f0f; padding: 15px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; }
        .path { color: #888; font-size: 0.85em; margin-bottom: 10px; }
    </style>
</head>
<body>
    <h1>DocViewer</h1>
    <p class="path">Viewing: <?php echo htmlspecialchars($file); ?></p>
    <div class="card">
        <pre><?php echo $content; ?></pre>
    </div>
    <p><a href="/">Back</a></p>
</body>
</html>
