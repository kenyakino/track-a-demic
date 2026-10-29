<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

$adviser = require_role_api('adviser');
$in = request_json();

$milestone = find_adviser_milestone((int) ($in['id'] ?? 0), $adviser['id']);
if ($milestone === null) {
    json_response(['ok' => false, 'message' => 'Milestone not found.'], 404);
}
$milestoneId = (int) $milestone['id'];

[$name, $due, $term, $errors] = validate_milestone_input($in);

$current = [];
$stmt = db()->prepare('SELECT id, group_id, status FROM group_milestones WHERE template_id = ?');
$stmt->execute([$milestoneId]);
foreach ($stmt->fetchAll() as $row) {
    $current[(int) $row['group_id']] = $row;
}

// Changing the groups: add new ones, remove ones that have not started
$sync = array_key_exists('group_ids', $in);
$wanted = $current ? array_keys($current) : [];
if ($sync) {
    $wanted = array_values(array_unique(array_map('intval', (array) $in['group_ids'])));
    if (array_diff($wanted, adviser_group_ids($adviser['id'])) !== []) {
        $errors['group_ids'] = 'Choose only your own groups.';
    }
    foreach (array_diff(array_keys($current), $wanted) as $groupId) {
        if ($current[$groupId]['status'] !== 'Not Started') {
            $errors['group_ids'] = 'A group that already started this milestone cannot be removed from it.';
        }
    }
}

if ($errors !== []) {
    json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], 422);
}

$toAdd = array_diff($wanted, array_keys($current));
$toRemove = array_diff(array_keys($current), $wanted);

db()->beginTransaction();
$update = db()->prepare('UPDATE milestone_templates SET name = ?, due_date = ?, term = ? WHERE id = ? AND adviser_id = ?');
$update->execute([$name, $due, $term, $milestoneId, $adviser['id']]);

$add = db()->prepare('INSERT INTO group_milestones (group_id, template_id) VALUES (?, ?)');
foreach ($toAdd as $groupId) {
    $add->execute([$groupId, $milestoneId]);
}
$remove = db()->prepare('DELETE FROM group_milestones WHERE id = ?');
foreach ($toRemove as $groupId) {
    $remove->execute([$current[$groupId]['id']]);
}
db()->commit();

foreach ($toAdd as $groupId) {
    notify_group_members($groupId, 'New milestone: ' . $name . ', due ' . date('M j, Y', strtotime($due)) . '.');
}
if ($due !== $milestone['due_date']) {
    foreach (array_intersect(array_keys($current), $wanted) as $groupId) {
        notify_group_members($groupId, 'The deadline for "' . $name . '" is now ' . date('M j, Y', strtotime($due)) . '.');
    }
}

json_response(['ok' => true, 'message' => 'Milestone updated.']);
