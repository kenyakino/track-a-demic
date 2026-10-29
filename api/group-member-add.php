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

if (!in_array($userId, array_column(available_students(), 'id'), true)) {
    json_response(['ok' => false, 'message' => 'That student is not available. Reload and try again.'], 409);
}

// The first student added to an empty group becomes its leader
$isLeader = group_members_list($groupId) === [] ? 1 : 0;

try {
    $insert = db()->prepare('INSERT INTO group_members (group_id, user_id, is_leader) VALUES (?, ?, ?)');
    $insert->execute([$groupId, $userId, $isLeader]);
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? 0) === 1062) {
        json_response(['ok' => false, 'message' => 'That student was just added to another group.'], 409);
    }
    error_log('Add member failed: ' . $e->getMessage());
    json_response(['ok' => false, 'message' => 'Could not add the student. Try again.'], 500);
}

add_notification($userId, 'You were added to ' . $group['group_name'] . ' by ' . $adviser['full_name'] . '.');

json_response(['ok' => true, 'message' => 'Student added to the group.']);
