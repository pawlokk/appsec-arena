<?php

const CORS_ALLOWED_ORIGINS = [
    'https://chalk.internal',
    'https://wiki.internal',
];

function apply_cors_headers(): void
{
    $z = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($z, CORS_ALLOWED_ORIGINS, true)) {
        header('Access-Control-Allow-Origin: ' . $z);
        header('Vary: Origin');
    }
}

function cors_preflight(): bool
{
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        apply_cors_headers();
        header('Access-Control-Allow-Methods: GET, POST');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
        http_response_code(204);
        return true;
    }
    return false;
}
