<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = require_role_api();
$fileId = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT id, group_id, file_name, file_path FROM files WHERE id = ? LIMIT 1');
$stmt->execute([$fileId]);
$file = $stmt->fetch();

// Same answer for "missing" and "not yours", so file ids cannot be probed
$notFound = static fn () => json_response(['ok' => false, 'message' => 'File not found.'], 404);

if (!$file) {
    $notFound();
}

if ($user['role'] === 'student') {
    $membership = student_membership($user['id']);
    if ($membership === null || $membership['group_id'] !== (int) $file['group_id']) {
        $notFound();
    }
} elseif (find_adviser_group((int) $file['group_id'], $user['id']) === null) {
    $notFound();
}

$base = realpath(upload_settings()['dir']);
$full = $base ? realpath($base . DIRECTORY_SEPARATOR . basename((string) $file['file_path'])) : false;

if ($full === false || strpos($full, $base) !== 0 || !is_file($full)) {
    $notFound();
}

$extension = strtolower(pathinfo($full, PATHINFO_EXTENSION));
$name = clean_upload_name((string) $file['file_name']);
$asciiName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'file';

header('Content-Type: ' . (UPLOAD_TYPES[$extension] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($full));
header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($full);
exit;
