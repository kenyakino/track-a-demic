<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

[$user, $membership] = require_student_group();

$topicId = (int) ($_POST['id'] ?? 0);

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

[$title, $description, $errors] = validate_topic_input($_POST);

// After a revision request a new file is required. While the topic still waits for review it is optional.
$fileRequired = $topic['status'] === 'For Revision';
$checked = check_uploaded_file($_FILES['file'] ?? null, $fileRequired);

if (!$checked['ok']) {
    $errors['file'] = $checked['message'];
}

if ($errors !== []) {
    $onlyFile = count($errors) === 1 && isset($errors['file']) && !$checked['ok'];
    json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], $onlyFile ? $checked['status'] : 422);
}

$newStored = null;
$oldFiles = [];

if (!$checked['skipped']) {
    $newStored = store_checked_upload($checked);
    if ($newStored === null) {
        json_response(['ok' => false, 'message' => 'The server could not save the file. Try again.'], 500);
    }
    $oldFiles = source_file_rows('topic', $topicId);
}

try {
    db()->beginTransaction();

    // Saving a topic sends it back to the adviser for review
    $update = db()->prepare("UPDATE topics SET title = ?, description = ?, status = 'Proposed' WHERE id = ? AND group_id = ?");
    $update->execute([$title, $description, $topicId, $membership['group_id']]);

    if ($newStored !== null) {
        record_file($membership['group_id'], $user['id'], $checked['name'], $newStored, $checked['size'], 'topic', $topicId);
        delete_file_rows($oldFiles);   // the previous proposal file is replaced
    }

    db()->commit();
} catch (PDOException $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    if ($newStored !== null) {
        unlink_stored_files(['uploads/' . $newStored]);
    }
    error_log('Topic update failed: ' . $e->getMessage());
    json_response(['ok' => false, 'message' => 'Could not save the topic. Try again.'], 500);
}

unlink_stored_files(array_column($oldFiles, 'file_path'));

$verb = $topic['status'] === 'For Revision' ? 'resubmitted their topic after revision.' : 'updated their topic proposal.';
add_notification($membership['adviser_id'], $membership['group_name'] . ' ' . $verb);

$stmt->execute([$topicId, $membership['group_id']]);

json_response([
    'ok' => true,
    'message' => 'Topic saved and sent for review.',
    'topic' => topic_view($stmt->fetch(), $membership['is_leader']),
]);
