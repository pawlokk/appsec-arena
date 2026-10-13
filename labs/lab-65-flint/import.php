<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $x = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($x, 'application/xml') === false && strpos($x, 'text/xml') === false) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Content-Type must be application/xml']);
        exit;
    }

    $o = file_get_contents('php://input');
    if (!$o) {
        http_response_code(400);
        echo json_encode(['error' => 'Empty body']);
        exit;
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($o, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_DTDLOAD);

    if ($xml === false) {
        http_response_code(422);
        header('Content-Type: application/json');
        $e = array_map(fn($v) => $v->message, libxml_get_errors());
        echo json_encode(['error' => 'XML parse error', 'details' => $e]);
        exit;
    }

    $r = [];
    foreach ($xml->contact as $ct) {
        $r[] = [
            'name'    => (string)($ct->name    ?? ''),
            'email'   => (string)($ct->email   ?? ''),
            'phone'   => (string)($ct->phone   ?? ''),
            'company' => (string)($ct->company ?? ''),
        ];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'status'   => 'ok',
        'imported' => count($r),
        'contacts' => $r,
    ]);
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Flint CRM — XML Import API</title>
<style>
body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 2rem; }
h1 { color: #e85d04; font-family: Arial, sans-serif; }
pre { background: #252526; padding: 1rem; border-radius: 4px; overflow-x: auto; }
.tag { color: #569cd6; }
.val { color: #ce9178; }
a { color: #e85d04; }
</style>
</head>
<body>
<h1>Flint CRM — Bulk Contact Import API</h1>
<p>POST XML to this endpoint to import contacts in bulk. No authentication required — designed for system integrations.</p>
<p>Required header: <code>Content-Type: application/xml</code></p>
<h2>Example payload:</h2>
<pre>
<span class="tag">&lt;?xml version="1.0" encoding="UTF-8"?&gt;</span>
<span class="tag">&lt;contacts&gt;</span>
  <span class="tag">&lt;contact&gt;</span>
    <span class="tag">&lt;name&gt;</span><span class="val">Jane Doe</span><span class="tag">&lt;/name&gt;</span>
    <span class="tag">&lt;email&gt;</span><span class="val">jane@example.com</span><span class="tag">&lt;/email&gt;</span>
    <span class="tag">&lt;phone&gt;</span><span class="val">555-1234</span><span class="tag">&lt;/phone&gt;</span>
    <span class="tag">&lt;company&gt;</span><span class="val">Acme</span><span class="tag">&lt;/company&gt;</span>
  <span class="tag">&lt;/contact&gt;</span>
<span class="tag">&lt;/contacts&gt;</span>
</pre>
<h2>cURL example:</h2>
<pre>curl -s -X POST http://TARGET/import.php \
  -H 'Content-Type: application/xml' \
  --data-binary @contacts.xml</pre>
<p><a href="/login.php">← Back to login</a></p>
</body>
</html>
