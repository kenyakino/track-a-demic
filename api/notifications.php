<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
$user = require_role_api();

$count = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$count->execute([$user['id']]);

$stmt = db()->prepare('SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 20');
$stmt->execute([$user['id']]);

$items = array_map(static fn(array $r): array => [
    'id' => (int) $r['id'],
    'message' => $r['message'],
    'is_read' => (bool) $r['is_read'],
    'ago' => time_ago($r['created_at']),
], $stmt->fetchAll());

json_response(['ok' => true, 'unread' => (int) $count->fetchColumn(), 'items' => $items]);
