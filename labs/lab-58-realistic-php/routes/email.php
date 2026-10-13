<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Twig\Environment;
use Twig\Loader\ArrayLoader;

$message = '';
$preview_output = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template = $_POST['template'] ?? '';
    $key = $_POST['key'] ?? '';

    $config_response = @file_get_contents('http://127.0.0.1:8888/config');
    $config = $config_response ? json_decode($config_response, true) : [];
    $twig_secret = $config['twig_secret'] ?? null;

    if (!$twig_secret || $key !== $twig_secret) {
        $message = "<p style='color:red'>Invalid template key. Please check your configuration.</p>";
    } else {
        try {
            $loader = new ArrayLoader(['preview.html' => $template]);
            $twig = new Environment($loader, ['autoescape' => false]);
            $rendered = $twig->render('preview.html', [
                'app_name' => 'Prism DMS',
                'user' => $_SESSION['user'],
            ]);
            $preview_output = "<div style='border:1px solid #ccc;padding:15px;margin-top:10px;background:white'><h4>Preview:</h4>" . $rendered . "</div>";
        } catch (Exception $e) {
            $message = "<p style='color:red'>Template error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

ob_start();
echo "<div class='card'>";
echo "<h2>Email Template Preview</h2>";
echo "<p>Render a Twig email template for preview. Requires the template API key from system configuration.</p>";
echo $message;
echo "<form method='post'>";
echo "<label>Template Key:</label><input type='text' name='key' placeholder='Enter template API key'>";
echo "<label>Template (Twig syntax):</label>";
echo "<textarea name='template' rows='12' placeholder='Enter Twig template...'></textarea>";
echo "<button type='submit'>Preview</button>";
echo "</form>";
echo $preview_output;
echo "</div>";

render_page(ob_get_clean(), 'Email Preview - Prism');
