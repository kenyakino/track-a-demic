<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/meetings.php';

start_secure_session();
require_post();
verify_csrf();

// Meeting logs are shared: students (for their group) and advisers (for their groups) can write them
$user = require_role_api();
$in = request_json();

if ($user['role'] === 'student') {
    $membership = student_membership($user['id']);
    if ($membership === null) {
        json_response(['ok' => false, 'message' => 'You are not in a group yet. Ask your adviser to add you.'], 403);
    }
    $groupId = $membership['group_id'];
    $groupName = $membership['group_name'];
} else {
    $group = find_adviser_group((int) ($in['group_id'] ?? 0), $user['id']);
    if ($group === null) {
        json_response(['ok' => false, 'message' => 'Choose one of your groups.', 'errors' => ['group_id' => 'Choose a group.']], 422);
    }
    $groupId = (int) $group['id'];
    $groupName = $group['group_name'];
}

[$date, $notes, $actions, $errors] = validate_meeting_input($in);
if ($errors !== []) {
    json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], 422);
}

// Optional link to an accepted meeting of the same group
$requestId = (int) ($in['request_id'] ?? 0);
if ($requestId > 0) {
    $check = db()->prepare("SELECT id FROM meeting_requests WHERE id = ? AND group_id = ? AND status = 'Accepted'");
    $check->execute([$requestId, $groupId]);
    if (!$check->fetch()) {
        json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => ['request_id' => 'Choose one of the accepted meetings.']], 422);
    }
}
$requestId = $requestId > 0 ? $requestId : null;

$when = date('M j, Y', strtotime($date));
$meetingId = (int) ($in['id'] ?? 0);

if ($meetingId > 0) {
    $stmt = db()->prepare(LOG_SELECT . ' WHERE m.id = ? AND m.group_id = ? LIMIT 1');
    $stmt->execute([$meetingId, $groupId]);
    $row = $stmt->fetch();

    if (!$row) {
        json_response(['ok' => false, 'message' => 'Meeting not found.'], 404);
    }
    if (!log_view($row, $user)['can_edit']) {
        $why = $user['role'] === 'adviser'
            ? 'You can only edit the logs you wrote.'
            : 'This log was written by your adviser or is already confirmed, so it can no longer be edited.';
        json_response(['ok' => false, 'message' => $why], 409);
    }

    db()->prepare('UPDATE meetings SET meeting_date = ?, notes = ?, action_items = ?, request_id = ? WHERE id = ? AND group_id = ?')
        ->execute([$date, $notes, $actions !== '' ? $actions : null, $requestId, $meetingId, $groupId]);

    if ($user['role'] === 'student') {
        add_notification($membership['adviser_id'], $groupName . ' updated the meeting log for ' . $when . '.');
    } else {
        notify_group_members($groupId, 'Your adviser updated the meeting log for ' . $when . '.');
    }
    json_response(['ok' => true, 'message' => 'Meeting log updated.']);
}

// An adviser's own log needs no confirmation
$confirmed = $user['role'] === 'adviser' ? 1 : 0;

db()->prepare('INSERT INTO meetings (group_id, author_id, request_id, meeting_date, notes, action_items, confirmed_by_adviser) VALUES (?, ?, ?, ?, ?, ?, ?)')
    ->execute([$groupId, $user['id'], $requestId, $date, $notes, $actions !== '' ? $actions : null, $confirmed]);

if ($user['role'] === 'student') {
    add_notification($membership['adviser_id'], $groupName . ' logged a meeting for ' . $when . '.');
} else {
    notify_group_members($groupId, 'Your adviser logged a meeting for ' . $when . '.');
}

json_response(['ok' => true, 'message' => 'Meeting logged.'], 201);
