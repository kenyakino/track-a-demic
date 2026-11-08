<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

[$user, $membership] = require_student_group();

$checked = check_uploaded_file($_FILES['file'] ?? null, true);
if (!$checked['ok']) {
    json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => ['file' => $checked['message']]], $checked['status']);
}

// Stored under a random name, so the original name can never reach the file system
$stored = store_checked_upload($checked);
if ($stored === null) {
    json_response(['ok' => false, 'message' => 'The server could not save the file. Try again.'], 500);
}

try {
    record_file($membership['group_id'], $user['id'], $checked['name'], $stored, $checked['size'], 'direct');
} catch (PDOException $e) {
    unlink_stored_files(['uploads/' . $stored]);
    error_log('Saving the file record failed: ' . $e->getMessage());
    json_response(['ok' => false, 'message' => 'The server could not save the file. Try again.'], 500);
}

add_notification($membership['adviser_id'], $membership['group_name'] . ' uploaded ' . $checked['name'] . '.');

json_response(['ok' => true, 'message' => 'File uploaded.'], 201);
