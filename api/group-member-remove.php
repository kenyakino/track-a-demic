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

$find = db()->prepare('SELECT id, is_leader FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1');
$find->execute([$groupId, $userId]);
$member = $find->fetch();
if (!$member) {
    json_response(['ok' => false, 'message' => 'That student is not in this group.'], 404);
}

db()->beginTransaction();
$delete = db()->prepare('DELETE FROM group_members WHERE id = ?');
$delete->execute([$member['id']]);

// If the leader leaves, the longest-standing member takes over
if ((int) $member['is_leader'] === 1) {
    $promote = db()->prepare(
        'UPDATE group_members SET is_leader = 1 WHERE id = (
            SELECT id FROM (SELECT id FROM group_members WHERE group_id = ? ORDER BY joined_at, id LIMIT 1) AS first_member
         )'
    );
    $promote->execute([$groupId]);
}
db()->commit();

add_notification($userId, 'You were removed from ' . $group['group_name'] . '.');

json_response(['ok' => true, 'message' => 'Student removed from the group.']);
