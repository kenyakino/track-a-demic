<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

[$user, $membership] = require_student_group();

$input = request_json();
$topicId = (int) ($input['id'] ?? 0);

// The topic must belong to the student's own group
$stmt = db()->prepare(
    'SELECT id, title, description, status, adviser_remarks, submitted_at, updated_at
       FROM topics WHERE id = ? AND group_id = ? LIMIT 1'
);
$stmt->execute([$topicId, $membership['group_id']]);
$topic = $stmt->fetch();

if (!$topic) {
    json_response(['ok' => false, 'message' => 'Topic not found.'], 404);
}

if (!in_array($topic['status'], TOPIC_EDITABLE_STATUSES, true)) {
    json_response([
        'ok' => false,
        'message' => 'This topic can no longer be edited because it is ' . $topic['status'] . '.',
    ], 409);
}

[$title, $description, $errors] = validate_topic_input($input);

if ($errors !== []) {
    json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], 422);
}

// Saving a topic sends it back to the adviser for review
$update = db()->prepare("UPDATE topics SET title = ?, description = ?, status = 'Proposed' WHERE id = ? AND group_id = ?");
$update->execute([$title, $description, $topicId, $membership['group_id']]);

$verb = $topic['status'] === 'For Revision' ? 'resubmitted their topic after revision.' : 'updated their topic proposal.';
add_notification($membership['adviser_id'], $membership['group_name'] . ' ' . $verb);

$stmt->execute([$topicId, $membership['group_id']]);

json_response([
    'ok' => true,
    'message' => 'Topic saved and sent for review.',
    'topic' => topic_view($stmt->fetch(), $membership['is_leader']),
]);
