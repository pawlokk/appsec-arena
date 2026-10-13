<?php
$message = '';
$import_result = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $xml_input = '';

    if (isset($_FILES['xmlfile']) && $_FILES['xmlfile']['error'] === UPLOAD_ERR_OK) {
        $xml_input = file_get_contents($_FILES['xmlfile']['tmp_name']);
    } elseif (!empty($_POST['xmldata'])) {
        $xml_input = $_POST['xmldata'];
    }

    if ($xml_input !== '') {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_input, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_DTDLOAD);

        if ($xml === false) {
            $message = "<p style='color:red'>Failed to parse XML. Please check format.</p>";
        } else {
            $pdo = get_pdo();
            $imported = 0;

            foreach ($xml->document as $doc) {
                $title = (string)$doc->title;
                $content = (string)$doc->content;
                if ($title && $content) {
                    $stmt = $pdo->prepare("INSERT INTO documents (title, content, owner) VALUES (?, ?, ?)");
                    $stmt->execute([$title, $content, $_SESSION['user']]);
                    $imported++;
                }
            }

            $message = "<p style='color:green'>Successfully imported {$imported} document(s).</p>";

            if ($imported === 0) {
                $import_result = "<pre style='background:#f8f8f8;padding:10px;overflow:auto'>" . htmlspecialchars(print_r($xml, true)) . "</pre>";
            }
        }
    }
}

ob_start();
echo "<div class='card'>";
echo "<h2>XML Document Import</h2>";
echo "<p>Upload a batch XML file to import multiple documents at once. Accepted format:</p>";
echo "<pre style='background:#f0f0f0;padding:10px'>";
echo htmlspecialchars("<?xml version=\"1.0\"?>
<documents>
  <document>
    <title>Doc Title</title>
    <content>Doc content here</content>
  </document>
</documents>");
echo "</pre>";
echo $message;
echo $import_result;
echo "<form method='post' enctype='multipart/form-data'>";
echo "<label>Upload XML File:</label><input type='file' name='xmlfile' accept='.xml'><br>";
echo "<label>Or paste XML directly:</label>";
echo "<textarea name='xmldata' rows='10' placeholder='Paste XML here...'></textarea>";
echo "<button type='submit'>Import</button>";
echo "</form>";
echo "</div>";

render_page(ob_get_clean(), 'XML Import - Prism');
