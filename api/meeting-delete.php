<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

[$user, $membership] = require_student_group();
$meetingId = (int) (request_json()['id'] ?? 0);

$stmt = db()->prepare('SELECT id, confirmed_by_adviser FROM meetings WHERE id = ? AND group_id = ? LIMIT 1');
$stmt->execute([$meetingId, $membership['group_id']]);
$meeting = $stmt->fetch();

if (!$meeting) {
    json_response(['ok' => false, 'message' => 'Meeting not found.'], 404);
}
if ((int) $meeting['confirmed_by_adviser'] === 1) {
    json_response(['ok' => false, 'message' => 'Your adviser already confirmed this meeting, so it can no longer be deleted.'], 409);
}

$delete = db()->prepare('DELETE FROM meetings WHERE id = ? AND group_id = ?');
$delete->execute([$meetingId, $membership['group_id']]);

json_response(['ok' => true, 'message' => 'Meeting deleted.']);
