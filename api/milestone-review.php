<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

$adviser = require_role_api('adviser');
$in = request_json();

$id     = (int) ($in['id'] ?? 0);
$action = (string) ($in['action'] ?? '');
$note   = trim((string) ($in['note'] ?? ''));

// The milestone must belong to one of this adviser's groups
$stmt = db()->prepare(
    'SELECT gm.id, gm.group_id, gm.status, t.name
       FROM group_milestones gm
       JOIN milestone_templates t ON t.id = gm.template_id
       JOIN capstone_groups g ON g.id = gm.group_id
      WHERE gm.id = ? AND g.adviser_id = ?
      LIMIT 1'
);
$stmt->execute([$id, $adviser['id']]);
$milestone = $stmt->fetch();

if (!$milestone) {
    json_response(['ok' => false, 'message' => 'Milestone not found.'], 404);
}

$allowed = MILESTONE_ADVISER_TRANSITIONS[$milestone['status']] ?? [];
if (!isset($allowed[$action])) {
    json_response(['ok' => false, 'message' => 'This milestone is ' . $milestone['status'] . ', so that action is not available.'], 409);
}

if ($action === 'rework' && $note === '') {
    json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => ['note' => 'Write a note so the group knows what to fix.']], 422);
}
if (text_length($note) > NOTE_MAX) {
    json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => ['note' => 'Use ' . NOTE_MAX . ' characters or fewer.']], 422);
}

$newStatus = $allowed[$action];

db()->beginTransaction();
$update = db()->prepare('UPDATE group_milestones SET status = ?, completed_at = ? WHERE id = ?');
$update->execute([$newStatus, $newStatus === 'Completed' ? date('Y-m-d H:i:s') : null, $id]);

if ($note !== '') {
    $comment = db()->prepare("INSERT INTO comments (target_type, target_id, user_id, comment_text) VALUES ('milestone', ?, ?, ?)");
    $comment->execute([$id, $adviser['id'], $note]);
}
db()->commit();

$messages = [
    'complete' => 'Your adviser marked "' . $milestone['name'] . '" as completed.',
    'rework'   => 'Your adviser asked for rework on "' . $milestone['name'] . '".',
    'reopen'   => 'Your adviser reopened "' . $milestone['name'] . '".',
];
notify_group_members((int) $milestone['group_id'], $messages[$action]);

$results = ['complete' => 'Milestone marked as completed.', 'rework' => 'Sent back for rework.', 'reopen' => 'Milestone reopened.'];

json_response(['ok' => true, 'message' => $results[$action]]);
