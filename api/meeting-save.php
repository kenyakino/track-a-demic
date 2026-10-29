<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

[$user, $membership] = require_student_group();
$in = request_json();

[$date, $notes, $actions, $errors] = validate_meeting_input($in);
if ($errors !== []) {
    json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], 422);
}

$meetingId = (int) ($in['id'] ?? 0);
$when = date('M j, Y', strtotime($date));

if ($meetingId > 0) {
    // Edit: the meeting must belong to the group and must not be confirmed yet
    $stmt = db()->prepare('SELECT id, confirmed_by_adviser FROM meetings WHERE id = ? AND group_id = ? LIMIT 1');
    $stmt->execute([$meetingId, $membership['group_id']]);
    $meeting = $stmt->fetch();

    if (!$meeting) {
        json_response(['ok' => false, 'message' => 'Meeting not found.'], 404);
    }
    if ((int) $meeting['confirmed_by_adviser'] === 1) {
        json_response(['ok' => false, 'message' => 'Your adviser already confirmed this meeting, so it can no longer be edited.'], 409);
    }

    $update = db()->prepare('UPDATE meetings SET meeting_date = ?, notes = ?, action_items = ? WHERE id = ? AND group_id = ?');
    $update->execute([$date, $notes, $actions !== '' ? $actions : null, $meetingId, $membership['group_id']]);

    add_notification($membership['adviser_id'], $membership['group_name'] . ' updated the meeting log for ' . $when . '.');
    json_response(['ok' => true, 'message' => 'Meeting updated.']);
}

$insert = db()->prepare('INSERT INTO meetings (group_id, meeting_date, notes, action_items) VALUES (?, ?, ?, ?)');
$insert->execute([$membership['group_id'], $date, $notes, $actions !== '' ? $actions : null]);

add_notification($membership['adviser_id'], $membership['group_name'] . ' logged a meeting for ' . $when . '.');

json_response(['ok' => true, 'message' => 'Meeting logged.'], 201);
