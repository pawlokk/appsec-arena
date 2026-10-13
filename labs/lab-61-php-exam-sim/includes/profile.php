<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../classes/Loader.php';
require_once __DIR__ . '/../classes/Serializer.php';
require_once __DIR__ . '/../classes/FileManager.php';
require_once __DIR__ . '/../classes/Executor.php';

function handleProfile() {
    $user = requireLogin();

    if (isset($_COOKIE['avatar_meta'])) {
        $raw = base64_decode($_COOKIE['avatar_meta']);
        if ($raw !== false) {
            @unserialize($raw);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? $user['username']);
        $bio      = trim($_POST['bio'] ?? '');
        $pdo = getDB();
        $pdo->prepare('UPDATE users SET username = ?, bio = ? WHERE id = ?')->execute([$username, $bio, $user['id']]);
        $user['username'] = $username;
        $user['bio']      = $bio;
        $msg = '<p class="success">Profile updated.</p>';
    } else {
        $msg = '';
    }

    $avatarUrl = htmlspecialchars($user['avatar_url'] ?? '/static/default_avatar.png');
    $body = '<div class="card">
        <h2>My Profile</h2>' . $msg . '
        <div style="display:flex;gap:2rem;align-items:flex-start;margin-top:1rem">
        <img src="' . $avatarUrl . '" style="width:100px;height:100px;border-radius:50%;object-fit:cover;background:#ddd">
        <form method="post">
            <label>Username</label>
            <input name="username" value="' . htmlspecialchars($user['username']) . '">
            <label>Bio</label>
            <textarea name="bio" rows="3">' . htmlspecialchars($user['bio'] ?? '') . '</textarea>
            <br><button type="submit" class="btn btn-primary" style="margin-top:.5rem">Save</button>
        </form>
        </div>
        <hr style="margin:1.5rem 0">
        <h3>Avatar</h3>
        <form action="/upload/avatar" method="post" enctype="multipart/form-data">
            <label>Upload new avatar</label>
            <input type="file" name="avatar">
            <button type="submit" class="btn btn-secondary btn-sm" style="margin-top:.5rem">Upload</button>
        </form>
    </div>';

    echo renderLayout('Profile', $body, $user);
}
