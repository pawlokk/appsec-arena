<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/include/session.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/include/mysql_connect.inc.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/mods/_core/file_manager/filemanager.inc.php');

if (empty($_SESSION['valid_user'])) {
    header('Location: /login.php');
    exit;
}

$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $tmp = $_FILES['file']['tmp_name'];
    $import_path = '/var/content/import/1/';

    if (!is_uploaded_file($tmp)) {
        $error_msg = 'No file uploaded.';
    } elseif ($_FILES['file']['size'] === 0) {
        $error_msg = 'Empty file.';
    } else {
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            $error_msg = 'Import failed: could not open archive.';
        } else {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry_name = $zip->getNameIndex($i);
                if (substr($entry_name, -1) === '/') {
                    continue;
                }
                if (!preImportCallBack($entry_name)) {
                    continue;
                }
                $target = $import_path . $entry_name;
                $dir = dirname($target);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                file_put_contents($target, $zip->getFromIndex($i));
            }
            $zip->close();

            $manifest_path = $import_path . 'imsmanifest.xml';
            $ims_manifest_xml = @file_get_contents($manifest_path);
            if ($ims_manifest_xml === false) {
                $error_msg = 'IMS manifest file is missing. This does not appear to be a valid IMS content package.';
            } else {
                $xml_parser = xml_parser_create();
                xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, false);
                if (!xml_parse($xml_parser, $ims_manifest_xml, true)) {
                    xml_parser_free($xml_parser);
                    die(sprintf("XML error: %s at line %d",
                        xml_error_string(xml_get_error_code($xml_parser)),
                        xml_get_current_line_number($xml_parser)));
                }
                xml_parser_free($xml_parser);
                $success_msg = 'Package imported successfully.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Academy - Import</title></head>
<body>
<h2>Import Test Package</h2>
<?php if ($error_msg): ?><p style="color:red"><?php echo htmlspecialchars($error_msg); ?></p><?php endif; ?>
<?php if ($success_msg): ?><p style="color:green"><?php echo htmlspecialchars($success_msg); ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<input type="file" name="file"><br><br>
<input type="submit" value="Import">
</form>
<p><a href="/dashboard.php">Back</a></p>
</body>
</html>
