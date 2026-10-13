<?php
if ($_SERVER['REQUEST_URI'] === '/config') {
    header('Content-Type: application/json');
    echo json_encode([
        'twig_secret' => 'Pr1sm_Tw1g_K3y_2024',
        'db_pass' => 'sqlite_internal_9x2k',
    ]);
} else {
    http_response_code(404);
    echo 'Not found';
}
