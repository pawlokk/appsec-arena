<?php
function preImportCallBack($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $blocked = explode('|', 'exe|asp|php|php3|bat|cgi|pl|com|vbs|reg|pcd|pif|scr|bas|inf|vb|vbe|wsc|wsf|wsh');
    return !in_array($ext, $blocked);
}
