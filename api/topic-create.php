<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

[$user, $membership] = require_student_group();
[$title, $description, $errors] = validate_topic_input(request_json());

if ($errors !== []) {
    json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], 422);
}

// One active topic per group. A rejected topic does not count.
$count = db()->prepare("SELECT COUNT(*) FROM topics WHERE group_id = ? AND status <> 'Rejected'");
$count->execute([$membership['group_id']]);
if ((int) $count->fetchColumn() > 0) {
    json_response(['ok' => false, 'message' => 'Your group already has a topic. Edit it instead.'], 409);
}

$insert = db()->prepare('INSERT INTO topics (group_id, title, description) VALUES (?, ?, ?)');
$insert->execute([$membership['group_id'], $title, $description]);
$topicId = (int) db()->lastInsertId();

add_notification($membership['adviser_id'], $membership['group_name'] . ' submitted a new topic proposal.');

$stmt = db()->prepare('SELECT id, title, description, status, adviser_remarks, submitted_at, updated_at FROM topics WHERE id = ?');
$stmt->execute([$topicId]);

json_response([
    'ok' => true,
    'message' => 'Topic submitted for review.',
    'topic' => topic_view($stmt->fetch(), $membership['is_leader']),
], 201);
