<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

$adviser = require_role_api('adviser');
$in = request_json();

$groupId = (int) ($in['group_id'] ?? 0);
$userId  = (int) ($in['user_id'] ?? 0);

$group = find_adviser_group($groupId, $adviser['id']);
if ($group === null) {
    json_response(['ok' => false, 'message' => 'Group not found.'], 404);
}

if (!in_array($userId, array_column(group_members_list($groupId), 'id'), true)) {
    json_response(['ok' => false, 'message' => 'That student is not in this group.'], 404);
}

db()->beginTransaction();
$clear = db()->prepare('UPDATE group_members SET is_leader = 0 WHERE group_id = ?');
$clear->execute([$groupId]);
$set = db()->prepare('UPDATE group_members SET is_leader = 1 WHERE group_id = ? AND user_id = ?');
$set->execute([$groupId, $userId]);
db()->commit();

add_notification($userId, 'You are now the leader of ' . $group['group_name'] . '.');

json_response(['ok' => true, 'message' => 'Group leader changed.']);
