<?php
require_once __DIR__ . '/../config.php';
$user = currentUser();
if (!$user) { header('Location: /login.php'); exit; }
$db = db();
$s = $db->prepare('SELECT id, sender_email, subject, date_header, received_at, read_at FROM messages WHERE recipient_email=? ORDER BY received_at DESC');
$s->bind_param('s', $user['email']);
$s->execute();
$msgs = $s->get_result()->fetch_all(MYSQLI_ASSOC);
?><!DOCTYPE html>
<html><head><title>Inbox — <?= htmlspecialchars($user['email']) ?></title>
<style>body{font-family:Arial,sans-serif;margin:0;background:#f5f5f5}
.topbar{background:#1a73e8;color:#fff;padding:.7rem 1.5rem;display:flex;justify-content:space-between;align-items:center}
.topbar a{color:#fff;text-decoration:none;margin-left:1rem;font-size:.9rem}
.container{max-width:900px;margin:2rem auto;background:#fff;border-radius:6px;box-shadow:0 1px 4px rgba(0,0,0,.1)}
table{width:100%;border-collapse:collapse}
th,td{padding:.75rem 1rem;text-align:left;border-bottom:1px solid #eee;font-size:.9rem}
th{background:#f8f8f8;font-weight:600}
tr:hover{background:#f0f8ff}
.unread{font-weight:bold}
a.compose{background:#1a73e8;color:#fff;padding:.5rem 1rem;border-radius:4px;text-decoration:none;font-size:.85rem}</style>
</head><body>
<div class="topbar">
  <span>Hexmail Webmail — <?= htmlspecialchars($user['email']) ?></span>
  <span>
    <a href="/mail/compose.php" class="compose">Compose</a>
    <?php if (currentAdmin()): ?><a href="/admin/">Admin Panel</a><?php endif; ?>
    <a href="/logout.php">Logout</a>
  </span>
</div>
<div class="container">
<table>
<tr><th>From</th><th>Subject</th><th>Date</th><th>Received</th></tr>
<?php foreach ($msgs as $m): ?>
<tr class="<?= $m['read_at'] ? '' : 'unread' ?>">
  <td><?= htmlspecialchars($m['sender_email']) ?></td>
  <td><a href="/mail/view.php?id=<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['subject'] ?: '(no subject)') ?></a></td>
  <td><?= htmlspecialchars($m['date_header']) ?></td>
  <td><?= htmlspecialchars($m['received_at']) ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$msgs): ?><tr><td colspan="4" style="text-align:center;color:#999">No messages</td></tr><?php endif; ?>
</table>
</div></body></html>
