<?php
require_once __DIR__ . '/lib/store.php';
header('Content-Type: application/json');
echo json_encode(loadCollected());
