<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

[$user, $membership] = require_student_group();
$in = request_json();

$id     = (int) ($in['id'] ?? 0);
$action = (string) ($in['action'] ?? '');

// The milestone must belong to the student's own group
$stmt = db()->prepare(
    'SELECT gm.id, gm.status, t.name
       FROM group_milestones gm
       JOIN milestone_templates t ON t.id = gm.template_id
      WHERE gm.id = ? AND gm.group_id = ?
      LIMIT 1'
);
$stmt->execute([$id, $membership['group_id']]);
$milestone = $stmt->fetch();

if (!$milestone) {
    json_response(['ok' => false, 'message' => 'Milestone not found.'], 404);
}

$allowed = MILESTONE_STUDENT_TRANSITIONS[$milestone['status']] ?? [];
if (!isset($allowed[$action])) {
    json_response(['ok' => false, 'message' => 'This milestone is ' . $milestone['status'] . ', so that action is not available.'], 409);
}

$update = db()->prepare('UPDATE group_milestones SET status = ? WHERE id = ?');
$update->execute([$allowed[$action], $id]);

if ($action === 'submit') {
    add_notification($membership['adviser_id'], $membership['group_name'] . ' submitted "' . $milestone['name'] . '" for review.');
} elseif ($action === 'withdraw') {
    add_notification($membership['adviser_id'], $membership['group_name'] . ' withdrew "' . $milestone['name'] . '" from review.');
}

$messages = [
    'start'    => 'Milestone started.',
    'submit'   => 'Submitted for review.',
    'withdraw' => 'Submission withdrawn.',
];

json_response(['ok' => true, 'message' => $messages[$action]]);
