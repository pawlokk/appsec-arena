<?php
require_once __DIR__ . '/config.php';

if (!defined('PROCESS_IMPORT_INTERNAL')) {
    http_response_code(403);
    echo json_encode(['error' => 'Direct access not permitted. Use /admin/process_queue.php']);
    exit;
}

function process_import_job(int $j, string $xd): array
{
    $c = get_db();

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xd, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_DTDLOAD);

    if ($xml === false) {
        $e = array_map(fn($v) => $v->message, libxml_get_errors());
        libxml_clear_errors();
        $c->prepare('UPDATE import_queue SET status = ?, status_detail = ? WHERE id = ?')
            ->execute(['error', implode('; ', $e), $j]);
        return ['status' => 'error', 'errors' => $e];
    }

    libxml_clear_errors();

    $n = 0;
    foreach ($xml->contact as $ct) {
        $na = (string)($ct->name    ?? '');
        $em = (string)($ct->email   ?? '');
        $ph = (string)($ct->phone   ?? '');
        $co = (string)($ct->company ?? '');

        if ($na || $em) {
            $c->prepare(
                'INSERT INTO contacts(name, email, phone, company) VALUES(?,?,?,?)'
            )->execute([$na, $em, $ph, $co]);
            $n++;
        }
    }

    $c->prepare('UPDATE import_queue SET status = ?, result = ? WHERE id = ?')
        ->execute(['done', "Imported $n contacts", $j]);

    return ['status' => 'done', 'imported' => $n];
}
