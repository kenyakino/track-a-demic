<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$adviser = require_role_api('adviser');

$stmt = db()->prepare(
    'SELECT m.id, m.group_id, g.group_name, m.meeting_date, m.notes, m.action_items, m.confirmed_by_adviser, m.created_at
       FROM meetings m
       JOIN capstone_groups g ON g.id = m.group_id
      WHERE g.adviser_id = ?
      ORDER BY m.confirmed_by_adviser, m.meeting_date DESC, m.id DESC'
);
$stmt->execute([$adviser['id']]);

$meetings = array_map(static function (array $row): array {
    $view = meeting_view($row);
    $view['group_name'] = $row['group_name'];
    return $view;
}, $stmt->fetchAll());

json_response(['ok' => true, 'user' => $adviser, 'meetings' => $meetings]);
