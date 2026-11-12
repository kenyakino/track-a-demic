<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/meetings.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = require_role_api();

if ($user['role'] === 'student') {
    $membership = student_membership($user['id']);
    if ($membership === null) {
        json_response(['ok' => true, 'role' => 'student', 'groups' => [], 'requests' => [], 'logs' => []]);
    }
    $groups = [['id' => $membership['group_id'], 'name' => $membership['group_name']]];
} else {
    $stmt = db()->prepare('SELECT id, group_name AS name FROM capstone_groups WHERE adviser_id = ? ORDER BY group_name');
    $stmt->execute([$user['id']]);
    $groups = array_map(static fn (array $g): array => ['id' => (int) $g['id'], 'name' => $g['name']], $stmt->fetchAll());
}

$ids = array_column($groups, 'id');
if ($ids === []) {
    json_response(['ok' => true, 'role' => $user['role'], 'groups' => [], 'requests' => [], 'logs' => []]);
}
$in = implode(',', array_fill(0, count($ids), '?'));

$requests = db()->prepare(REQUEST_SELECT . " WHERE r.group_id IN ($in) ORDER BY FIELD(r.status,'Pending','Accepted','Rejected','Cancelled'), r.starts_at DESC");
$requests->execute($ids);

$logs = db()->prepare(LOG_SELECT . " WHERE m.group_id IN ($in) ORDER BY m.meeting_date DESC, m.id DESC");
$logs->execute($ids);

json_response([
    'ok' => true,
    'role' => $user['role'],
    'groups' => $groups,
    'requests' => array_map('request_view', $requests->fetchAll()),
    'logs' => array_map(static fn (array $row): array => log_view($row, $user), $logs->fetchAll()),
]);
