<?php
$pdo = get_pdo();
$user = $_SESSION['user'];

$stmt = $pdo->prepare("SELECT id, title, owner, created_at FROM documents WHERE owner = ? ORDER BY created_at DESC");
$stmt->execute([$user]);
$my_docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
echo "<div class='card'>";
echo "<h2>My Documents</h2>";

if (count($my_docs) === 0) {
    echo "<p>You have no documents yet.</p>";
} else {
    echo "<table width='100%' border='1' cellpadding='5' style='border-collapse:collapse'>";
    echo "<tr><th>ID</th><th>Title</th><th>Created</th></tr>";
    foreach ($my_docs as $doc) {
        echo "<tr>";
        echo "<td>" . (int)$doc['id'] . "</td>";
        echo "<td><a href='/index.php?page=doc&id=" . (int)$doc['id'] . "'>" . htmlspecialchars($doc['title']) . "</a></td>";
        echo "<td>" . htmlspecialchars($doc['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr><h3>Create Document</h3>";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_title'])) {
    $title = trim($_POST['new_title']);
    $content = trim($_POST['new_content']);
    if ($title && $content) {
        $stmt = $pdo->prepare("INSERT INTO documents (title, content, owner) VALUES (?, ?, ?)");
        $stmt->execute([$title, $content, $user]);
        echo "<p style='color:green'>Document created.</p>";
    }
}

echo "<form method='post' action='/index.php?page=docs'>";
echo "<label>Title:</label><input name='new_title' type='text' required>";
echo "<label>Content:</label><textarea name='new_content' rows='5' required></textarea>";
echo "<button type='submit'>Create</button>";
echo "</form>";
echo "</div>";

render_page(ob_get_clean(), 'My Documents - Prism');
