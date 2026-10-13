<?php
?>
<!DOCTYPE html>
<html>
<head>
    <title>DocViewer - AppSec Arena Lab 29</title>
    <style>
        body { font-family: monospace; background: #1a1a2e; color: #e0e0e0; padding: 20px; }
        h1 { color: #00d4aa; }
        .card { background: #16213e; border: 1px solid #0f3460; padding: 20px; border-radius: 4px; margin: 20px 0; }
        a { color: #00d4aa; }
        pre { background: #0f0f0f; padding: 15px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; }
        .hint { color: #888; font-size: 0.9em; }
    </style>
</head>
<body>
    <h1>DocViewer</h1>
    <div class="card">
        <p>Welcome to the internal documentation viewer.</p>
        <p>Available documents:</p>
        <ul>
            <li><a href="/view?file=docs/readme.txt">docs/readme.txt</a></li>
            <li><a href="/view?file=docs/changelog.txt">docs/changelog.txt</a></li>
        </ul>
        <p class="hint">Usage: /view?file=&lt;path&gt;</p>
    </div>
</body>
</html>
