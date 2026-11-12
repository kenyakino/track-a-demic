<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/meetings.php';

start_secure_session();
require_post();
verify_csrf();

$user = require_role_api();
$in = request_json();

$request = find_request_for($user, (int) ($in['id'] ?? 0));
if ($request === null) {
    json_response(['ok' => false, 'message' => 'Meeting request not found.'], 404);
}

$status = $request['status'];
$upcoming = strtotime($request['starts_at']) > time();
$when = format_meeting_time($request['starts_at'], (int) $request['duration_minutes']);

if ($user['role'] === 'student') {
    if (!in_array($status, ['Pending', 'Accepted'], true) || !$upcoming) {
        json_response(['ok' => false, 'message' => 'This request can no longer be cancelled.'], 409);
    }
    db()->prepare("UPDATE meeting_requests SET status = 'Cancelled', decided_at = NOW() WHERE id = ?")->execute([$request['id']]);

    $membership = student_membership($user['id']);
    add_notification($membership['adviser_id'], $membership['group_name'] . ' cancelled the meeting on ' . $when . '.');
    json_response(['ok' => true, 'message' => 'Meeting cancelled.']);
}

// An adviser may cancel an accepted, upcoming meeting, with a reason
if ($status !== 'Accepted' || !$upcoming) {
    json_response(['ok' => false, 'message' => 'Only upcoming accepted meetings can be cancelled.'], 409);
}
$note = trim((string) ($in['note'] ?? ''));
if ($note === '' || text_length($note) > REQUEST_NOTE_MAX) {
    json_response(['ok' => false, 'message' => 'Write a reason so the group knows why.', 'errors' => ['note' => 'A reason is required.']], 422);
}
db()->prepare("UPDATE meeting_requests SET status = 'Cancelled', decision_note = ?, decided_at = NOW() WHERE id = ?")->execute([$note, $request['id']]);
notify_group_members((int) $request['group_id'], 'Your adviser cancelled the meeting on ' . $when . '. Reason: ' . $note);
json_response(['ok' => true, 'message' => 'Meeting cancelled. The group was told why.']);
