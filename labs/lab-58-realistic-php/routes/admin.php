<?php
$pdo = get_pdo();
$users = $pdo->query("SELECT id, username, role FROM users")->fetchAll(PDO::FETCH_ASSOC);
$docs = $pdo->query("SELECT id, title, owner, created_at FROM documents ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
echo "<div class='card'><h2>Admin Panel</h2>";
echo "<p>Welcome, administrator. Use the tools below to manage the Prism system.</p>";
echo "<p><a href='/index.php?page=import'>XML Document Import</a> | <a href='/index.php?page=email'>Email Template Preview</a></p>";
echo "</div>";

echo "<div class='card'><h3>Users</h3><table width='100%' border='1' cellpadding='5' style='border-collapse:collapse'>";
echo "<tr><th>ID</th><th>Username</th><th>Role</th></tr>";
foreach ($users as $u) {
    echo "<tr><td>" . (int)$u['id'] . "</td><td>" . htmlspecialchars($u['username']) . "</td><td>" . htmlspecialchars($u['role']) . "</td></tr>";
}
echo "</table></div>";

echo "<div class='card'><h3>All Documents</h3><table width='100%' border='1' cellpadding='5' style='border-collapse:collapse'>";
echo "<tr><th>ID</th><th>Title</th><th>Owner</th><th>Created</th></tr>";
foreach ($docs as $d) {
    echo "<tr><td>" . (int)$d['id'] . "</td><td><a href='/index.php?page=doc&id=" . (int)$d['id'] . "'>" . htmlspecialchars($d['title']) . "</a></td><td>" . htmlspecialchars($d['owner']) . "</td><td>" . htmlspecialchars($d['created_at']) . "</td></tr>";
}
echo "</table></div>";

render_page(ob_get_clean(), 'Admin - Prism');
