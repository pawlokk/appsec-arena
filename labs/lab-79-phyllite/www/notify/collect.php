<?php
header('Access-Control-Allow-Origin: *');
$data = isset($_GET['data']) ? $_GET['data'] : (isset($_POST['data']) ? $_POST['data'] : '');
if ($data !== '') {
    $store = '/tmp/notify_log.json';
    $items = file_exists($store) ? json_decode(file_get_contents($store), true) : array();
    if (!is_array($items)) $items = array();
    $items[] = $data;
    file_put_contents($store, json_encode($items));
}
echo json_encode(array('ok' => true));
