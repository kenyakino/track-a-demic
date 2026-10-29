<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

$adviser = require_role_api('adviser');
$in = request_json();

$meetingId = (int) ($in['id'] ?? 0);
$confirmed = !empty($in['confirmed']);

$stmt = db()->prepare(
    'SELECT m.id, m.group_id, m.meeting_date
       FROM meetings m
       JOIN capstone_groups g ON g.id = m.group_id
      WHERE m.id = ? AND g.adviser_id = ?
      LIMIT 1'
);
$stmt->execute([$meetingId, $adviser['id']]);
$meeting = $stmt->fetch();

if (!$meeting) {
    json_response(['ok' => false, 'message' => 'Meeting not found.'], 404);
}

$update = db()->prepare('UPDATE meetings SET confirmed_by_adviser = ? WHERE id = ?');
$update->execute([$confirmed ? 1 : 0, $meetingId]);

$when = date('M j, Y', strtotime($meeting['meeting_date']));
notify_group_members(
    (int) $meeting['group_id'],
    $confirmed ? 'Your adviser confirmed the meeting log for ' . $when . '.' : 'Your adviser reopened the meeting log for ' . $when . ' for editing.'
);

json_response(['ok' => true, 'message' => $confirmed ? 'Meeting confirmed.' : 'Meeting reopened for editing.']);
