<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

[$user, $membership] = require_student_group();

// The form is sent as multipart (title, description, and the proposal file)
[$title, $description, $errors] = validate_topic_input($_POST);
$checked = check_uploaded_file($_FILES['file'] ?? null, true);

if (!$checked['ok']) {
    $errors['file'] = $checked['message'];
}

if ($errors !== []) {
    $onlyFile = count($errors) === 1 && isset($errors['file']) && !$checked['ok'];
    json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], $onlyFile ? $checked['status'] : 422);
}

// One active topic per group. A rejected topic does not count.
$count = db()->prepare("SELECT COUNT(*) FROM topics WHERE group_id = ? AND status <> 'Rejected'");
$count->execute([$membership['group_id']]);
if ((int) $count->fetchColumn() > 0) {
    json_response(['ok' => false, 'message' => 'Your group already has a topic. Edit it instead.'], 409);
}

$stored = store_checked_upload($checked);
if ($stored === null) {
    json_response(['ok' => false, 'message' => 'The server could not save the file. Try again.'], 500);
}

try {
    db()->beginTransaction();

    $insert = db()->prepare('INSERT INTO topics (group_id, title, description) VALUES (?, ?, ?)');
    $insert->execute([$membership['group_id'], $title, $description]);
    $topicId = (int) db()->lastInsertId();

    record_file($membership['group_id'], $user['id'], $checked['name'], $stored, $checked['size'], 'topic', $topicId);

    db()->commit();
} catch (PDOException $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    unlink_stored_files(['uploads/' . $stored]);
    error_log('Topic create failed: ' . $e->getMessage());
    json_response(['ok' => false, 'message' => 'Could not submit the topic. Try again.'], 500);
}

$similar = find_similar_topic($title, $description, (int) $membership['group_id']);

add_notification($membership['adviser_id'], $membership['group_name'] . ' submitted a new topic proposal.'
    . ($similar ? ' It looks similar to "' . $similar['title'] . '" (' . $similar['group_name'] . ').' : ''));

$stmt = db()->prepare('SELECT id, title, description, status, adviser_remarks, submitted_at, updated_at FROM topics WHERE id = ?');
$stmt->execute([$topicId]);

json_response([
    'ok' => true,
    'message' => 'Topic submitted for review.',
    'topic' => topic_view($stmt->fetch(), $membership['is_leader']),
    // Students only learn that a similar topic exists, never which one
    'similar' => $similar !== null,
], 201);
