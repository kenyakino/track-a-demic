<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

$adviser = require_role_api('adviser');
$groupId = (int) (request_json()['id'] ?? 0);

$group = find_adviser_group($groupId, $adviser['id']);
if ($group === null) {
    json_response(['ok' => false, 'message' => 'Group not found.'], 404);
}

// A group that already has work is protected from accidental deletion
$work = db()->prepare(
    'SELECT (SELECT COUNT(*) FROM topics WHERE group_id = ?)
          + (SELECT COUNT(*) FROM meetings WHERE group_id = ?)
          + (SELECT COUNT(*) FROM references_list WHERE group_id = ?)
          + (SELECT COUNT(*) FROM files WHERE group_id = ?)'
);
$work->execute([$groupId, $groupId, $groupId, $groupId]);
if ((int) $work->fetchColumn() > 0) {
    json_response(['ok' => false, 'message' => 'This group already has work (topics, meetings, references, or files), so it cannot be deleted.'], 409);
}

$members = group_members_list($groupId);

$delete = db()->prepare('DELETE FROM capstone_groups WHERE id = ? AND adviser_id = ?');
$delete->execute([$groupId, $adviser['id']]);

foreach ($members as $member) {
    add_notification($member['id'], $group['group_name'] . ' was deleted by your adviser.');
}

json_response(['ok' => true, 'message' => 'Group deleted.']);
