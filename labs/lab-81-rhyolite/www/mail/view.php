<?php
require_once __DIR__ . '/../config.php';
$user = currentUser();
if (!$user) { header('Location: /login.php'); exit; }
$id = (int)($_GET['id'] ?? 0);
$db = db();
$s = $db->prepare('SELECT * FROM messages WHERE id=? AND recipient_email=?');
$s->bind_param('is', $id, $user['email']);
$s->execute();
$msg = $s->get_result()->fetch_assoc();
if (!$msg) { http_response_code(404); die('Not found'); }
$upd = $db->prepare('UPDATE messages SET read_at=NOW() WHERE id=?');
$upd->bind_param('i', $id); $upd->execute();
?><!DOCTYPE html>
<html><head><title><?= htmlspecialchars($msg['subject']) ?></title>
<style>body{font-family:Arial,sans-serif;margin:0;background:#f5f5f5}
.topbar{background:#1a73e8;color:#fff;padding:.7rem 1.5rem;display:flex;justify-content:space-between}
.topbar a{color:#fff;text-decoration:none;margin-left:1rem}
.container{max-width:900px;margin:2rem auto;background:#fff;border-radius:6px;box-shadow:0 1px 4px rgba(0,0,0,.1);padding:1.5rem}
.hdr{border-bottom:1px solid #eee;margin-bottom:1rem;padding-bottom:1rem}
.hdr table td{padding:.3rem .5rem;font-size:.9rem}
.hdr td:first-child{font-weight:600;color:#555;width:80px}
.body-frame{border:0;width:100%;min-height:200px}</style>
</head><body>
<div class="topbar">
  <span>Hexmail Webmail</span>
  <span><a href="/mail/">← Inbox</a><a href="/logout.php">Logout</a></span>
</div>
<div class="container">
<div class="hdr">
<table>
  <tr><td>From</td><td><?= htmlspecialchars($msg['sender_email']) ?></td></tr>
  <tr><td>To</td><td><?= htmlspecialchars($msg['recipient_email']) ?></td></tr>
  <tr><td>Subject</td><td><?= htmlspecialchars($msg['subject']) ?></td></tr>
  <tr><td>Date</td><td><?= $msg['date_header'] ?></td></tr>
</table>
</div>
<div class="body"><?= $msg['body'] ?></div>
</div>
</body></html>
