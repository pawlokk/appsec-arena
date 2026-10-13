<?php
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'mailuser');
define('DB_PASS', 'mailpass');
define('DB_NAME', 'maildb');
define('APP_ROOT', __DIR__ . '/');

function db(): mysqli {
    static $c = null;
    if ($c === null) {
        $c = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($c->connect_errno) { http_response_code(500); die('db error'); }
    }
    return $c;
}

function getConfig(string $section, string $key): string {
    $db = db();
    $s = $db->prepare('SELECT key_value FROM config WHERE section=? AND key_name=?');
    $s->bind_param('ss', $section, $key);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    return $r ? $r['key_value'] : '';
}

function currentUser(): ?array {
    $token = $_COOKIE['hexmail6'] ?? '';
    if (!$token) return null;
    $db = db();
    $s = $db->prepare('SELECT a.* FROM sessions ses JOIN accounts a ON a.id=ses.account_id WHERE ses.token=?');
    $s->bind_param('s', $token);
    $s->execute();
    return $s->get_result()->fetch_assoc() ?: null;
}

function currentAdmin(): ?array {
    $token = $_COOKIE['hexmail6_admin'] ?? '';
    if (!$token) return null;
    $db = db();
    $s = $db->prepare('SELECT a.* FROM admin_sessions ses JOIN accounts a ON a.id=ses.account_id WHERE ses.token=?');
    $s->bind_param('s', $token);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    return ($r && $r['is_admin']) ? $r : null;
}

function simplifyString(string $s): string {
    return preg_replace('/[^a-zA-Z0-9]/', '', $s);
}

function getTmpPath(string $userEmail): string {
    $base = getConfig('global', 'tmpFolderBaseName');
    $safe = simplifyString($userEmail);
    $a = $safe[0] ?? 'x';
    $b = $safe[1] ?? 'x';
    $dir = APP_ROOT . $base . $a . '/' . $b . '/' . $safe . '/';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    return $dir;
}
