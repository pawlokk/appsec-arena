<?php
$IllegalExtentions = array('exe','asp','php','php3','bat','cgi','pl','com','vbs','reg','pcd','pif','scr','bas','inf','vb','vbe','wsc','wsf','wsh');

function preImportCallBack($filename) {
    global $IllegalExtentions;
    $parts = pathinfo($filename);
    if (!isset($parts['extension'])) {
        return true;
    }
    $ext = strtolower($parts['extension']);
    if (in_array($ext, $IllegalExtentions)) {
        return false;
    }
    return true;
}
