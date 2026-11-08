<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

[$user, $membership] = require_student_group();
$fileId = (int) (request_json()['id'] ?? 0);

$stmt = db()->prepare('SELECT id, file_path, uploaded_by, source_type FROM files WHERE id = ? AND group_id = ? LIMIT 1');
$stmt->execute([$fileId, $membership['group_id']]);
$file = $stmt->fetch();

if (!$file) {
    json_response(['ok' => false, 'message' => 'File not found.'], 404);
}

// Proposal and milestone files are replaced when the group resubmits
if ($file['source_type'] !== 'direct') {
    json_response(['ok' => false, 'message' => 'This file belongs to a topic or a milestone submission. It is replaced when you resubmit.'], 409);
}

// The person who uploaded a file, or the group leader, can delete it
if (!$membership['is_leader'] && (int) $file['uploaded_by'] !== $user['id']) {
    json_response(['ok' => false, 'message' => 'Only the uploader or the group leader can delete this file.'], 403);
}

$delete = db()->prepare('DELETE FROM files WHERE id = ? AND group_id = ?');
$delete->execute([$fileId, $membership['group_id']]);

$path = upload_settings()['dir'] . DIRECTORY_SEPARATOR . basename((string) $file['file_path']);
if (is_file($path)) {
    @unlink($path);
}

json_response(['ok' => true, 'message' => 'File deleted.']);
