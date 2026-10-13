<?php
require_once __DIR__ . '/../config.php';
$user = currentUser();
if (!$user) { http_response_code(401); die('Unauthorized'); }
$to      = trim($_POST['emailTo'] ?? '');
$subject = trim($_POST['emailSubject'] ?? '');
$body    = $_POST['emailBodyHtml'] ?? '';
if (!$to) { http_response_code(400); die('Missing recipient'); }
$date = date('r');
$db = db();
$s = $db->prepare('INSERT INTO messages (recipient_email, sender_email, subject, date_header, body) VALUES (?,?,?,?,?)');
$s->bind_param('sssss', $to, $user['email'], $subject, $date, $body);
$s->execute();
header('Location: /mail/');
