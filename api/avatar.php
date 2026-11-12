<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/profile.php';

start_secure_session();
$viewer = require_role_api();
$ownerId = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT avatar_path FROM users WHERE id = ?');
$stmt->execute([$ownerId]);
$name = $stmt->fetchColumn();

$full = $name ? avatar_dir() . DIRECTORY_SEPARATOR . basename((string) $name) : '';

if (!$name || !can_view_avatar($viewer, $ownerId) || !is_file($full)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
header('Content-Type: ' . ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$ext]);
header('Content-Length: ' . filesize($full));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
readfile($full);
