<?php
function searchFriends($name) {
    global $addslashes;
    $result = array();

    $name = $addslashes($name);
    $sub_names = explode(' ', $name);
    $query = '';
    foreach ($sub_names as $piece) {
        if ($piece == '') {
            continue;
        }
        $match_piece = "LIKE '%%$piece%%' ";
        $query .= "(first_name $match_piece OR second_name $match_piece OR last_name $match_piece OR login $match_piece ) AND ";
    }

    if ($query == '') {
        return $result;
    }

    $query = rtrim($query, ' AND ');
    $sql = 'SELECT * FROM AT_members M WHERE ' . $query;
    $rows_members = queryDB($sql, array());

    if ($rows_members) {
        foreach ($rows_members as $row) {
            $result[$row['member_id']] = array(
                'login'      => $row['login'],
                'first_name' => $row['first_name'],
                'last_name'  => $row['last_name'],
            );
        }
    }
    return $result;
}

function printSocialName($member_id, $link = true) {
    $row = queryDB('SELECT login, first_name, last_name FROM AT_members WHERE member_id=%d', array((int)$member_id), true);
    if (!$row) return '';
    return htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
}
