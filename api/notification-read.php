<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

$user = require_role_api();
$in = request_json();

if (!empty($in['all'])) {
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$user['id']]);
    json_response(['ok' => true]);
}

$stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
$stmt->execute([(int) ($in['id'] ?? 0), $user['id']]);
json_response(['ok' => true]);
