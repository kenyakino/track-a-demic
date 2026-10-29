<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = require_role_api('student');
$membership = student_membership($user['id']);

if ($membership === null) {
    json_response(['ok' => true, 'group' => null, 'meetings' => []]);
}

$stmt = db()->prepare(
    'SELECT id, meeting_date, notes, action_items, confirmed_by_adviser, created_at
       FROM meetings WHERE group_id = ? ORDER BY meeting_date DESC, id DESC'
);
$stmt->execute([$membership['group_id']]);

json_response([
    'ok' => true,
    'group' => ['id' => $membership['group_id'], 'name' => $membership['group_name']],
    'meetings' => array_map('meeting_view', $stmt->fetchAll()),
]);
