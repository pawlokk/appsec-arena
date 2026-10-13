<?php

if (!defined('EXPORT_WORKER_INTERNAL')) {
    http_response_code(403);
    echo 'Direct access not permitted.';
    exit;
}

require_once __DIR__ . '/../config.php';

function run_export(array $schedule): array
{
    $output_dir = '/tmp/chalk_exports';
    if (!is_dir($output_dir)) {
        mkdir($output_dir, 0755, true);
    }

    $name   = preg_replace('/[^a-zA-Z0-9_-]/', '', $schedule['name']);
    $format = $schedule['format'] === 'tar' ? 'tar' : 'zip';

    $raw_filter = $schedule['export_filter'] ?? '';
    $filter     = urldecode($raw_filter);

    $source_dir = '/var/www/html/';
    $output     = "$output_dir/{$name}_export";

    if ($format === 'zip') {
        $cmd = "cd $source_dir && zip -r $output.zip . -i '$filter' 2>&1";
    } else {
        $cmd = "cd $source_dir && tar czf $output.tar.gz --include='$filter' . 2>&1";
    }

    exec($cmd, $out, $ret);

    return [
        'exit_code' => $ret,
        'output'    => implode("\n", array_slice($out, 0, 20)),
        'archive'   => "$output.$format",
    ];
}
