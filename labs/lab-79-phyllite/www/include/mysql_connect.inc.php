<?php
$db = new mysqli('127.0.0.1', 'appuser', 'apppass', 'lmsdb');
if ($db->connect_errno) {
    die('DB connection failed');
}

function my_add_null_slashes($string) {
    global $db;
    if (defined('MYSQLI_ENABLED')) {
        return $db->real_escape_string(stripslashes($string));
    }
    return mysql_real_escape_string(stripslashes($string));
}

function my_null_slashes($string) {
    return $string;
}

if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc() == 1) {
    $addslashes   = 'my_add_null_slashes';
    $stripslashes = 'stripslashes';
} else {
    if (function_exists('mysqli_connect')) {
        define('MYSQLI_ENABLED', 1);
        $addslashes = 'trim';
    } else {
        $addslashes = 'mysql_real_escape_string';
    }
    $stripslashes = 'my_null_slashes';
}

function create_sql($query, $params = array(), $sanitize = true) {
    global $addslashes, $db;
    if ($sanitize) {
        foreach ($params as $i => $value) {
            if (defined('MYSQLI_ENABLED')) {
                $value = $addslashes(htmlspecialchars_decode($value, ENT_QUOTES));
                $params[$i] = $db->real_escape_string($value);
            } else {
                $params[$i] = $addslashes($value);
            }
        }
    }
    $sql = vsprintf($query, $params);
    return $sql;
}

function queryDB($query, $params = array(), $oneRow = false, $sanitize = true) {
    global $db;
    $sql = create_sql($query, $params, $sanitize);
    $result = $db->query($sql);
    if (!$result) {
        return false;
    }
    if (is_bool($result)) {
        return $result;
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    if ($oneRow) {
        return count($rows) > 0 ? $rows[0] : false;
    }
    return $rows;
}
