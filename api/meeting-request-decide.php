<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/meetings.php';

start_secure_session();
require_post();
verify_csrf();

$adviser = require_role_api('adviser');
$in = request_json();

$request = find_request_for($adviser, (int) ($in['id'] ?? 0));
if ($request === null) {
    json_response(['ok' => false, 'message' => 'Meeting request not found.'], 404);
}
if ($request['status'] !== 'Pending') {
    json_response(['ok' => false, 'message' => 'This request was already answered.'], 409);
}

$decision = (string) ($in['decision'] ?? '');
$note = trim((string) ($in['note'] ?? ''));
if (text_length($note) > REQUEST_NOTE_MAX) {
    json_response(['ok' => false, 'message' => 'Use ' . REQUEST_NOTE_MAX . ' characters or fewer for the note.', 'errors' => ['note' => 'Too long.']], 422);
}

$groupId = (int) $request['group_id'];
$when = format_meeting_time($request['starts_at'], (int) $request['duration_minutes']);

if ($decision === 'accept') {
    if (strtotime($request['starts_at']) <= time()) {
        json_response(['ok' => false, 'message' => 'This meeting time has already passed. Reject it or ask the group to send a new request.'], 409);
    }

    // The adviser decides by hand, but gets a warning when two accepted meetings overlap
    $conflicts = overlapping_accepted($adviser['id'], $request['starts_at'], (int) $request['duration_minutes'], (int) $request['id']);
    if ($conflicts !== [] && empty($in['force'])) {
        json_response([
            'ok' => false,
            'warning' => true,
            'message' => 'This overlaps with a meeting you already accepted.',
            'conflicts' => $conflicts,
        ], 409);
    }

    db()->prepare("UPDATE meeting_requests SET status = 'Accepted', decision_note = ?, decided_at = NOW() WHERE id = ?")
        ->execute([$note !== '' ? $note : null, $request['id']]);
    notify_group_members($groupId, 'Your adviser accepted your meeting on ' . $when . '.');

    json_response(['ok' => true, 'message' => 'Meeting accepted.']);
}

if ($decision === 'reject') {
    if ($note === '') {
        json_response(['ok' => false, 'message' => 'Write a reason so the group knows why.', 'errors' => ['note' => 'A reason is required.']], 422);
    }

    $suggested = null;
    $sDate = trim((string) ($in['suggested_date'] ?? ''));
    $sTime = trim((string) ($in['suggested_time'] ?? ''));
    if ($sDate !== '' || $sTime !== '') {
        $suggested = parse_date_time($sDate, $sTime);
        if ($suggested === null || strtotime($suggested) <= time()) {
            json_response(['ok' => false, 'message' => 'Choose a valid future date and time to suggest.', 'errors' => ['suggested_date' => 'Choose a valid future date and time.']], 422);
        }
    }

    db()->prepare("UPDATE meeting_requests SET status = 'Rejected', decision_note = ?, suggested_at = ?, decided_at = NOW() WHERE id = ?")
        ->execute([$note, $suggested, $request['id']]);

    $text = 'Your adviser could not accept your meeting on ' . $when . '. Reason: ' . $note;
    if ($suggested !== null) {
        $text .= ' Suggested time: ' . date('M j, Y, g:i A', strtotime($suggested)) . '.';
    }
    notify_group_members($groupId, $text);

    json_response(['ok' => true, 'message' => 'Request rejected. The group was told why.']);
}

json_response(['ok' => false, 'message' => 'Choose accept or reject.'], 422);
