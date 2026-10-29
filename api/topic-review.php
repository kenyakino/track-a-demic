<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

$adviser = require_role_api('adviser');
$in = request_json();

$topicId = (int) ($in['id'] ?? 0);
$action  = (string) ($in['action'] ?? '');
$remarks = trim((string) ($in['remarks'] ?? ''));

// The topic must belong to one of this adviser's groups
$stmt = db()->prepare(
    'SELECT t.id, t.group_id, t.status, t.adviser_remarks, g.group_name
       FROM topics t
       JOIN capstone_groups g ON g.id = t.group_id
      WHERE t.id = ? AND g.adviser_id = ?
      LIMIT 1'
);
$stmt->execute([$topicId, $adviser['id']]);
$topic = $stmt->fetch();

if (!$topic) {
    json_response(['ok' => false, 'message' => 'Topic not found.'], 404);
}

$allowed = TOPIC_TRANSITIONS[$topic['status']] ?? [];
if (!isset($allowed[$action])) {
    json_response(['ok' => false, 'message' => 'This topic is ' . $topic['status'] . ', so that action is not available.'], 409);
}

if (in_array($action, REMARKS_REQUIRED_ACTIONS, true) && $remarks === '') {
    json_response([
        'ok' => false,
        'message' => 'Please fix the highlighted fields.',
        'errors' => ['remarks' => 'Write remarks so the group knows why.'],
    ], 422);
}

if (text_length($remarks) > REMARKS_MAX) {
    json_response([
        'ok' => false,
        'message' => 'Please fix the highlighted fields.',
        'errors' => ['remarks' => 'Use ' . REMARKS_MAX . ' characters or fewer.'],
    ], 422);
}

$newStatus  = $allowed[$action];
$newRemarks = $remarks !== '' ? $remarks : $topic['adviser_remarks'];

$update = db()->prepare('UPDATE topics SET status = ?, adviser_remarks = ? WHERE id = ?');
$update->execute([$newStatus, $newRemarks, $topicId]);

$messages = [
    'approve' => 'Your adviser approved your topic.',
    'revise'  => 'Your adviser asked for changes to your topic.',
    'reject'  => 'Your adviser did not accept your topic.',
    'start'   => 'Your topic is now marked In Progress.',
    'defend'  => 'Your topic is now marked Defended.',
];
notify_group_members((int) $topic['group_id'], $messages[$action]);

$successMessages = [
    'approve' => 'Topic approved.',
    'revise'  => 'Revision requested.',
    'reject'  => 'Topic rejected.',
    'start'   => 'Topic marked In Progress.',
    'defend'  => 'Topic marked Defended.',
];

json_response(['ok' => true, 'message' => $successMessages[$action]]);
