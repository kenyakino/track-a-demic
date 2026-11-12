<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/meetings.php';

start_secure_session();
require_post();
verify_csrf();

$user = require_role_api();
$meetingId = (int) (request_json()['id'] ?? 0);

$stmt = db()->prepare(LOG_SELECT . ' WHERE m.id = ? LIMIT 1');
$stmt->execute([$meetingId]);
$row = $stmt->fetch();

// "Not mine" and "missing" look the same
$allowedGroup = false;
if ($row) {
    if ($user['role'] === 'adviser') {
        $allowedGroup = find_adviser_group((int) $row['group_id'], $user['id']) !== null;
    } else {
        $membership = student_membership($user['id']);
        $allowedGroup = $membership !== null && $membership['group_id'] === (int) $row['group_id'];
    }
}
if (!$row || !$allowedGroup) {
    json_response(['ok' => false, 'message' => 'Meeting not found.'], 404);
}
if (!log_view($row, $user)['can_edit']) {
    json_response(['ok' => false, 'message' => 'This meeting log can no longer be deleted by you.'], 409);
}

db()->prepare('DELETE FROM meetings WHERE id = ?')->execute([$meetingId]);
json_response(['ok' => true, 'message' => 'Meeting log deleted.']);
