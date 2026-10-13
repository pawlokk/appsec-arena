<?php

$CORS_ALLOWED_ORIGINS = [
    'https://wiki.internal',
    'https://admin.wiki.internal',
];

function apply_cors_headers(array $a): void {
    $z = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!$z) return;
    if (in_array($z, $a, true)) {
        header('Access-Control-Allow-Origin: ' . $z);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    }
}

function handle_cors_preflight(array $a): void {
    apply_cors_headers($a);
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
