<?php
session_start();
require_once __DIR__ . '/../config.php';
require_admin();

$c = get_db();
$m = $e = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $x = $_POST['action'] ?? '';
    $i = (int)($_POST['article_id'] ?? 0);
    if ($x === 'approve' && $i) {
        $c->prepare("UPDATE articles SET status = 'approved' WHERE id = ?")->execute([$i]);
        $m = "Article #$i approved.";
    } elseif ($x === 'reject' && $i) {
        $c->prepare("UPDATE articles SET status = 'rejected' WHERE id = ?")->execute([$i]);
        $m = "Article #$i rejected.";
    }
}

$r = $c->query(
    "SELECT a.*, u.username FROM articles a"
    . " LEFT JOIN users u ON u.id = a.author_id"
    . " WHERE a.status = 'pending' ORDER BY a.created_at ASC"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Review Queue — Chalk</title>
<style>
body{font-family:sans-serif;background:#f4f6f8;margin:0}
nav{background:#2c3e50;color:white;padding:.8rem 2rem;display:flex;align-items:center;gap:1.5rem}
nav a{color:#ecf0f1;text-decoration:none}nav .logo{font-weight:bold;font-size:1.2rem;margin-right:auto}
main{max-width:900px;margin:2rem auto;padding:0 1rem}
.box{background:white;padding:1.5rem 2rem;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-bottom:1.5rem}
.success{color:#27ae60}.error{color:#c0392b}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:.5rem .8rem;border-bottom:1px solid #eee}
button{padding:.4rem .8rem;border:none;border-radius:4px;cursor:pointer}
.btn-approve{background:#27ae60;color:white}
.btn-reject{background:#c0392b;color:white}
</style>
</head>
<body>
<nav>
    <span class="logo">📝 Chalk</span>
    <a href="/index.php">Home</a>
    <a href="/admin/index.php">Admin</a>
    <a href="/logout.php">Logout</a>
</nav>
<main>
    <h1>Review Queue</h1>
    <?php if ($m): ?><p class="success"><?= htmlspecialchars($m) ?></p><?php endif; ?>
    <?php if ($e): ?><p class="error"><?= htmlspecialchars($e) ?></p><?php endif; ?>

    <div class="box">
        <h2>Pending Articles (<?= count($r) ?>)</h2>
        <?php if (!$r): ?>
        <p>No articles pending review.</p>
        <?php else: ?>
        <table>
            <tr><th>ID</th><th>Title</th><th>Author</th><th>Submitted</th><th>Actions</th></tr>
            <?php foreach ($r as $a): ?>
            <tr>
                <td><?= (int)$a['id'] ?></td>
                <td><?= htmlspecialchars($a['title']) ?></td>
                <td><?= htmlspecialchars($a['username'] ?? '?') ?></td>
                <td><?= htmlspecialchars($a['created_at']) ?></td>
                <td>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="article_id" value="<?= (int)$a['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button class="btn-approve" type="submit">Approve</button>
                    </form>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="article_id" value="<?= (int)$a['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <button class="btn-reject" type="submit">Reject</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
