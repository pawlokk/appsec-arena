<?php

if (!defined('REPORT_WORKER_INTERNAL')) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../config.php';

function run_report(int $user_id, string $status_filter): array
{
    $db = get_db();

    $allowed_statuses = ['pending', 'approved', 'rejected', 'published'];
    if (!in_array($status_filter, $allowed_statuses, true)) {
        $status_filter = 'published';
    }

    $stmt = $db->prepare(
        "SELECT a.id, a.title, a.author, a.created_at FROM articles a
         WHERE a.author_id = ? AND a.status = ?
         ORDER BY a.created_at DESC LIMIT 100"
    );
    $stmt->execute([$user_id, $status_filter]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return ['count' => count($rows), 'rows' => $rows];
}
