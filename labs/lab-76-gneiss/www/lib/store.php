<?php

define('DATA_DIR', '/var/app/data');
define('FIELDS_FILE', DATA_DIR . '/extrafields.json');
define('COLLECT_FILE', DATA_DIR . '/collected.json');

function loadFields()
{
    if (!file_exists(FIELDS_FILE)) {
        return [];
    }
    $raw = file_get_contents(FIELDS_FILE);
    return json_decode($raw, true) ?: [];
}

function saveFields(array $fields)
{
    file_put_contents(FIELDS_FILE, json_encode(array_values($fields), JSON_PRETTY_PRINT));
}

function loadCollected()
{
    if (!file_exists(COLLECT_FILE)) {
        return [];
    }
    $raw = file_get_contents(COLLECT_FILE);
    return json_decode($raw, true) ?: [];
}

function appendCollected($data)
{
    $items = loadCollected();
    $items[] = $data;
    file_put_contents(COLLECT_FILE, json_encode($items, JSON_PRETTY_PRINT));
}

function getUsers()
{
    return [
        ['id' => 1, 'username' => 'admin', 'email' => 'admin@delibr.local', 'role' => 'superadmin'],
        ['id' => 2, 'username' => 'jsmith', 'email' => 'jsmith@delibr.local', 'role' => 'user'],
        ['id' => 3, 'username' => 'mwilson', 'email' => 'mwilson@delibr.local', 'role' => 'user'],
    ];
}
