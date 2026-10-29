<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

$adviser = require_role_api('adviser');
$in = request_json();

$topicId = (int) ($in['topic_id'] ?? 0);
$text = trim((string) ($in['comment_text'] ?? ''));

$stmt = db()->prepare(
    'SELECT t.id, t.group_id
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

$length = text_length($text);
if ($length < 1 || $length > COMMENT_MAX) {
    json_response([
        'ok' => false,
        'message' => 'Please fix the highlighted fields.',
        'errors' => ['comment_text' => 'Write a comment of 1 to ' . COMMENT_MAX . ' characters.'],
    ], 422);
}

$insert = db()->prepare("INSERT INTO comments (target_type, target_id, user_id, comment_text) VALUES ('topic', ?, ?, ?)");
$insert->execute([$topicId, $adviser['id'], $text]);

notify_group_members((int) $topic['group_id'], 'Your adviser commented on your topic.');

json_response(['ok' => true, 'message' => 'Comment posted.'], 201);
