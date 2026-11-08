<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

[$user, $membership] = require_student_group();

$milestoneId = (int) ($_POST['id'] ?? 0);

// The milestone must belong to the student's own group
$stmt = db()->prepare(
    'SELECT gm.id, gm.status, t.name
       FROM group_milestones gm
       JOIN milestone_templates t ON t.id = gm.template_id
      WHERE gm.id = ? AND gm.group_id = ?
      LIMIT 1'
);
$stmt->execute([$milestoneId, $membership['group_id']]);
$milestone = $stmt->fetch();

if (!$milestone) {
    json_response(['ok' => false, 'message' => 'Milestone not found.'], 404);
}

if (!isset((MILESTONE_STUDENT_TRANSITIONS[$milestone['status']] ?? [])['submit'])) {
    $hint = $milestone['status'] === 'Not Started' ? ' Start it first.' : '';
    json_response(['ok' => false, 'message' => 'This milestone is ' . $milestone['status'] . ', so it cannot be submitted.' . $hint], 409);
}

// A file is required every time a milestone is submitted
$checked = check_uploaded_file($_FILES['file'] ?? null, true);
if (!$checked['ok']) {
    json_response(['ok' => false, 'message' => 'Attach a file to submit this milestone.', 'errors' => ['file' => $checked['message']]], $checked['status']);
}

$stored = store_checked_upload($checked);
if ($stored === null) {
    json_response(['ok' => false, 'message' => 'The server could not save the file. Try again.'], 500);
}

$oldFiles = source_file_rows('milestone', $milestoneId);

try {
    db()->beginTransaction();

    $update = db()->prepare("UPDATE group_milestones SET status = 'Submitted', completed_at = NULL WHERE id = ?");
    $update->execute([$milestoneId]);

    record_file($membership['group_id'], $user['id'], $checked['name'], $stored, $checked['size'], 'milestone', null, $milestoneId);
    delete_file_rows($oldFiles);   // the file from the previous submission is replaced

    db()->commit();
} catch (PDOException $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    unlink_stored_files(['uploads/' . $stored]);
    error_log('Milestone submit failed: ' . $e->getMessage());
    json_response(['ok' => false, 'message' => 'Could not submit the milestone. Try again.'], 500);
}

unlink_stored_files(array_column($oldFiles, 'file_path'));

add_notification($membership['adviser_id'], $membership['group_name'] . ' submitted "' . $milestone['name'] . '" for review.');

json_response(['ok' => true, 'message' => 'Submitted for review.']);
