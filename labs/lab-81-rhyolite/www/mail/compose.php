<?php
require_once __DIR__ . '/../config.php';
$user = currentUser();
if (!$user) { header('Location: /login.php'); exit; }
?><!DOCTYPE html>
<html><head><title>Compose</title>
<style>body{font-family:Arial,sans-serif;margin:0;background:#f5f5f5}
.topbar{background:#1a73e8;color:#fff;padding:.7rem 1.5rem;display:flex;justify-content:space-between}
.topbar a{color:#fff;text-decoration:none;margin-left:1rem}
.container{max-width:700px;margin:2rem auto;background:#fff;border-radius:6px;box-shadow:0 1px 4px rgba(0,0,0,.1);padding:1.5rem}
label{display:block;margin:.5rem 0 .2rem;font-size:.85rem;font-weight:600;color:#555}
input,textarea{width:100%;box-sizing:border-box;padding:.5rem;border:1px solid #ccc;border-radius:4px;margin-bottom:.8rem}
textarea{height:150px}
button{background:#1a73e8;color:#fff;padding:.6rem 1.5rem;border:0;border-radius:4px;cursor:pointer}
#attach-area{margin-bottom:.8rem}</style>
</head><body>
<div class="topbar">
  <span>Hexmail Webmail</span>
  <span><a href="/mail/">← Inbox</a><a href="/logout.php">Logout</a></span>
</div>
<div class="container">
<h3 style="margin-top:0">Compose</h3>
<form id="cf" method="POST" action="/mail/send.php" enctype="multipart/form-data">
<label>To</label><input type="text" name="emailTo" required>
<label>Subject</label><input type="text" name="emailSubject">
<label>Body</label><textarea name="emailBodyHtml"></textarea>
<label>Attachment</label>
<input type="file" name="newAttachment" id="attachFile">
<input type="hidden" name="composeID" value="uid<?= bin2hex(random_bytes(6)) ?>">
<button type="submit">Send</button>
</form>
</div></body></html>
