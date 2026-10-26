<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

[$user, $membership] = require_student_group();

if (!$membership['is_leader']) {
    json_response(['ok' => false, 'message' => 'Only the group leader can delete the topic.'], 403);
}

$topicId = (int) (request_json()['id'] ?? 0);

$stmt = db()->prepare('SELECT id, status FROM topics WHERE id = ? AND group_id = ? LIMIT 1');
$stmt->execute([$topicId, $membership['group_id']]);
$topic = $stmt->fetch();

if (!$topic) {
    json_response(['ok' => false, 'message' => 'Topic not found.'], 404);
}

if (!in_array($topic['status'], TOPIC_EDITABLE_STATUSES, true)) {
    json_response([
        'ok' => false,
        'message' => 'This topic can no longer be deleted because it is ' . $topic['status'] . '.',
    ], 409);
}

$delete = db()->prepare('DELETE FROM topics WHERE id = ? AND group_id = ?');
$delete->execute([$topicId, $membership['group_id']]);

add_notification($membership['adviser_id'], $membership['group_name'] . ' withdrew their topic proposal.');

json_response(['ok' => true, 'message' => 'Topic deleted.']);
