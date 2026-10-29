<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

$adviser = require_role_api('adviser');
$in = request_json();

[$name, $due, $term, $errors] = validate_milestone_input($in);

// Which groups get the milestone. Leaving it out means all of the adviser's groups.
$own = adviser_group_ids($adviser['id']);
$groupIds = array_key_exists('group_ids', $in)
    ? array_values(array_unique(array_map('intval', (array) $in['group_ids'])))
    : $own;

if (array_diff($groupIds, $own) !== []) {
    $errors['group_ids'] = 'Choose only your own groups.';
}

if ($errors !== []) {
    json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], 422);
}

db()->beginTransaction();
$insert = db()->prepare('INSERT INTO milestone_templates (adviser_id, name, due_date, term) VALUES (?, ?, ?, ?)');
$insert->execute([$adviser['id'], $name, $due, $term]);
$templateId = (int) db()->lastInsertId();

$assign = db()->prepare('INSERT INTO group_milestones (group_id, template_id) VALUES (?, ?)');
foreach ($groupIds as $groupId) {
    $assign->execute([$groupId, $templateId]);
}
db()->commit();

foreach ($groupIds as $groupId) {
    notify_group_members($groupId, 'New milestone: ' . $name . ', due ' . date('M j, Y', strtotime($due)) . '.');
}

json_response(['ok' => true, 'message' => 'Milestone added.', 'id' => $templateId], 201);
