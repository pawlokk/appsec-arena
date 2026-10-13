<?php
// Document search — results returned via the search_documents() wrapper defined in index.php

$q = $_GET['q'] ?? '';
$results = [];
$searched = false;

if ($q !== '') {
    $searched = true;
    // search_documents() is defined in index.php and uses PDO prepared statements
    $results = search_documents($q);
}

ob_start();
echo "<div class='card'>";
echo "<h2>Search Documents</h2>";
echo "<form method='get' action='/index.php'>
    <input type='hidden' name='page' value='search'>
    <input type='text' name='q' value='" . htmlspecialchars($q) . "' placeholder='Search by title or content...'>
    <button type='submit'>Search</button>
</form>";

if ($searched) {
    echo "<hr><h3>Results for: " . htmlspecialchars($q) . "</h3>";
    if (count($results) === 0) {
        echo "<p>No documents found.</p>";
    } else {
        foreach ($results as $row) {
            echo "<div style='border-bottom:1px solid #eee;padding:8px 0'>";
            echo "<a href='/index.php?page=doc&id=" . (int)$row['id'] . "'>" . htmlspecialchars($row['title']) . "</a>";
            echo "<br><small>" . htmlspecialchars(substr($row['content'], 0, 120)) . "...</small>";
            echo "</div>";
        }
    }
}
echo "</div>";

render_page(ob_get_clean(), 'Search - Prism');
