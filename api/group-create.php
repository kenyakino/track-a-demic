<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

$adviser = require_role_api('adviser');
$in = request_json();

[$name, $nameError] = validate_group_name((string) ($in['group_name'] ?? ''));
$errors = [];

if ($nameError !== null) {
    $errors['group_name'] = $nameError;
} elseif (adviser_has_group_named($adviser['id'], $name)) {
    $errors['group_name'] = 'You already have a group with this name.';
}

// Optional list of students to add right away; the first one becomes the leader
$memberIds = array_values(array_unique(array_map('intval', (array) ($in['member_ids'] ?? []))));
$memberIds = array_values(array_filter($memberIds, static fn (int $id): bool => $id > 0));

if ($memberIds !== []) {
    $free = array_column(available_students(), 'id');
    if (array_diff($memberIds, $free) !== []) {
        $errors['member_ids'] = 'One or more students are no longer available. Reload and try again.';
    }
}

if ($errors !== []) {
    json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], 422);
}

try {
    db()->beginTransaction();

    $insert = db()->prepare('INSERT INTO capstone_groups (group_name, adviser_id) VALUES (?, ?)');
    $insert->execute([$name, $adviser['id']]);
    $groupId = (int) db()->lastInsertId();

    $member = db()->prepare('INSERT INTO group_members (group_id, user_id, is_leader) VALUES (?, ?, ?)');
    foreach ($memberIds as $index => $userId) {
        $member->execute([$groupId, $userId, $index === 0 ? 1 : 0]);
    }

    db()->commit();
} catch (PDOException $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    if (($e->errorInfo[1] ?? 0) === 1062) {
        json_response(['ok' => false, 'message' => 'A student was added to another group at the same moment. Reload and try again.'], 409);
    }
    error_log('Group create failed: ' . $e->getMessage());
    json_response(['ok' => false, 'message' => 'Could not create the group. Try again.'], 500);
}

foreach ($memberIds as $userId) {
    add_notification($userId, 'You were added to ' . $name . ' by ' . $adviser['full_name'] . '.');
}

json_response(['ok' => true, 'message' => 'Group created.', 'group_id' => $groupId], 201);
