<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/store.php';
requireAuth();
$fields = loadFields();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Dashboard — Delibr ERP</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0f0f0f;color:#d0d0d0;font-family:monospace;font-size:13px}
nav{background:#111;border-bottom:1px solid #222;padding:10px 24px;display:flex;align-items:center;gap:20px}
nav a{color:#888;text-decoration:none}nav a:hover{color:#fff}
nav .brand{color:#fff;font-size:14px;margin-right:12px}
nav .right{margin-left:auto}
main{padding:32px 24px}
h2{color:#fff;margin-bottom:16px;font-size:15px}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;max-width:700px}
.tile{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:4px;padding:20px}
.tile h3{color:#aaa;font-size:12px;margin-bottom:6px}
.tile a{color:#5899d6;text-decoration:none;font-size:13px}
.tile a:hover{text-decoration:underline}
</style>
</head>
<body>
<nav>
<span class="brand">Delibr ERP</span>
<a href="/dashboard.php">Home</a>
<a href="/users.php">Users</a>
<a href="/admin/extrafields.php">Attributes</a>
<span class="right"><a href="/logout.php">Sign out</a></span>
</nav>
<main>
<h2>Dashboard</h2>
<div class="grid">
<div class="tile">
<h3>USERS</h3>
<a href="/users.php">View all users</a>
</div>
<div class="tile">
<h3>EXTRA ATTRIBUTES</h3>
<a href="/admin/extrafields.php">Manage attributes (<?= count($fields) ?> defined)</a>
</div>
<div class="tile">
<h3>SESSION</h3>
<span style="color:#666">Signed in as <?= htmlspecialchars($_SESSION['username']) ?></span>
</div>
</div>
</main>
</body>
</html>
