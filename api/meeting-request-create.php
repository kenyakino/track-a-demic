<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/meetings.php';

start_secure_session();
require_post();
verify_csrf();

// Only students ask for meetings. Advisers cannot propose them.
[$user, $membership] = require_student_group();
[$startsAt, $duration, $purpose, $link, $errors] = validate_request_input(request_json());

if ($errors !== []) {
    json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], 422);
}

// Avoid piling up: at most 5 open requests per group
$open = db()->prepare("SELECT COUNT(*) FROM meeting_requests WHERE group_id = ? AND status = 'Pending'");
$open->execute([$membership['group_id']]);
if ((int) $open->fetchColumn() >= 5) {
    json_response(['ok' => false, 'message' => 'Your group already has 5 requests waiting. Wait for your adviser to answer first.'], 409);
}

db()->prepare('INSERT INTO meeting_requests (group_id, requested_by, starts_at, duration_minutes, purpose, link) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$membership['group_id'], $user['id'], $startsAt, $duration, $purpose, $link]);

add_notification($membership['adviser_id'], $membership['group_name'] . ' requested a meeting on ' . format_meeting_time($startsAt, $duration) . '.');

json_response(['ok' => true, 'message' => 'Meeting request sent to your adviser.'], 201);
