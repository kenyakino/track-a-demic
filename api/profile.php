<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/profile.php';

start_secure_session();
$user = require_role_api();

$stmt = db()->prepare('SELECT first_name, last_name, title, email, profile_updated_at FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$row = $stmt->fetch();

json_response([
    'ok' => true,
    'profile' => [
        'first_name' => $row['first_name'],
        'last_name' => $row['last_name'],
        'title' => $row['title'],
        'email' => $row['email'],
        'role' => $user['role'],
        'avatar_url' => $user['avatar_url'],
        'initials' => $user['initials'],
    ],
    'lock' => profile_lock_status($row['profile_updated_at']),
]);
