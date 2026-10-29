<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

$adviser = require_role_api('adviser');
$in = request_json();

$groupId = (int) ($in['id'] ?? 0);
$group = find_adviser_group($groupId, $adviser['id']);
if ($group === null) {
    json_response(['ok' => false, 'message' => 'Group not found.'], 404);
}

[$name, $nameError] = validate_group_name((string) ($in['group_name'] ?? ''));
if ($nameError === null && adviser_has_group_named($adviser['id'], $name, $groupId)) {
    $nameError = 'You already have a group with this name.';
}
if ($nameError !== null) {
    json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => ['group_name' => $nameError]], 422);
}

$update = db()->prepare('UPDATE capstone_groups SET group_name = ? WHERE id = ? AND adviser_id = ?');
$update->execute([$name, $groupId, $adviser['id']]);

json_response(['ok' => true, 'message' => 'Group renamed.']);
