<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

$adviser = require_role_api('adviser');

$milestone = find_adviser_milestone((int) (request_json()['id'] ?? 0), $adviser['id']);
if ($milestone === null) {
    json_response(['ok' => false, 'message' => 'Milestone not found.'], 404);
}
$milestoneId = (int) $milestone['id'];

// Progress is protected: only a milestone nobody has started can be deleted
$started = db()->prepare("SELECT COUNT(*) FROM group_milestones WHERE template_id = ? AND status <> 'Not Started'");
$started->execute([$milestoneId]);
if ((int) $started->fetchColumn() > 0) {
    json_response(['ok' => false, 'message' => 'Some groups already started this milestone, so it cannot be deleted.'], 409);
}

$groups = db()->prepare('SELECT group_id FROM group_milestones WHERE template_id = ?');
$groups->execute([$milestoneId]);
$groupIds = array_map('intval', $groups->fetchAll(PDO::FETCH_COLUMN));

$delete = db()->prepare('DELETE FROM milestone_templates WHERE id = ? AND adviser_id = ?');
$delete->execute([$milestoneId, $adviser['id']]);

foreach ($groupIds as $groupId) {
    notify_group_members($groupId, 'The milestone "' . $milestone['name'] . '" was removed.');
}

json_response(['ok' => true, 'message' => 'Milestone deleted.']);
