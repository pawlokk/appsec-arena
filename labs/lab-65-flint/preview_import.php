<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$o = file_get_contents('php://input');
if (!$o) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty body']);
    exit;
}

libxml_disable_entity_loader(true);
libxml_use_internal_errors(true);

$d = new DOMDocument();
$ld = $d->loadXML($o, LIBXML_NONET);

if (!$ld) {
    $e = array_map(fn($v) => $v->message, libxml_get_errors());
    libxml_clear_errors();
    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'XML parse error', 'details' => $e]);
    exit;
}

libxml_clear_errors();

$r = [];
foreach ($d->getElementsByTagName('contact') as $nd) {
    $r[] = [
        'name'    => $nd->getElementsByTagName('name')->item(0)?->textContent ?? '',
        'email'   => $nd->getElementsByTagName('email')->item(0)?->textContent ?? '',
        'company' => $nd->getElementsByTagName('company')->item(0)?->textContent ?? '',
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'preview'  => true,
    'count'    => count($r),
    'contacts' => array_slice($r, 0, 5),
    'note'     => 'This is a preview only. POST to /admin/process_queue.php to commit.',
]);
