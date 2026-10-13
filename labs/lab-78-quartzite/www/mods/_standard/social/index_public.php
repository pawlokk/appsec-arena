<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/include/mysql_connect.inc.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/mods/_standard/social/lib/friends.inc.php');

if (isset($_GET['p'])) {
    $page = intval($_GET['p']);
}
if (!isset($page)) {
    $page = 1;
}

if (isset($_POST['q'])) {
    $query = $addslashes($_POST['q']);
    $search_result = searchFriends($query);

    if (!empty($search_result)) {
        echo '<div class="suggestions">Suggestions:<br/>';
        $counter = 0;
        foreach ($search_result as $member_id => $member_array) {
            if ($counter > 10) {
                break;
            }
            echo '<a href="#">' . printSocialName($member_id, false) . '</a><br/>';
            $counter++;
        }
        echo '</div>';
    }
    exit;
}

if (isset($_GET['q'])) {
    $query = $addslashes($_GET['q']);
    $search_result = searchFriends($query);

    if (!empty($search_result)) {
        echo '<div class="suggestions">Suggestions:<br/>';
        $counter = 0;
        foreach ($search_result as $member_id => $member_array) {
            if ($counter > 10) {
                break;
            }
            echo '<a href="#">' . printSocialName($member_id, false) . '</a><br/>';
            $counter++;
        }
        echo '</div>';
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Academy - Member Search</title></head>
<body>
<h2>Member Search</h2>
<form method="get">
<input type="text" name="q" placeholder="Search members...">
<input type="submit" value="Search">
</form>
</body>
</html>
