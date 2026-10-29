<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$adviser = require_role_api('adviser');

$stmt = db()->prepare(
    "SELECT g.id, g.group_name,
            (SELECT t.status FROM topics t WHERE t.group_id = g.id AND t.status <> 'Rejected' ORDER BY t.id DESC LIMIT 1) AS topic_status,
            ((SELECT COUNT(*) FROM topics t WHERE t.group_id = g.id)
           + (SELECT COUNT(*) FROM meetings m WHERE m.group_id = g.id)
           + (SELECT COUNT(*) FROM references_list r WHERE r.group_id = g.id)
           + (SELECT COUNT(*) FROM files f WHERE f.group_id = g.id)) AS work_count
       FROM capstone_groups g
      WHERE g.adviser_id = ?
      ORDER BY g.group_name"
);
$stmt->execute([$adviser['id']]);

$groups = array_map(static fn (array $row): array => [
    'id'           => (int) $row['id'],
    'name'         => $row['group_name'],
    'topic_status' => $row['topic_status'],
    'has_work'     => (int) $row['work_count'] > 0,
    'members'      => group_members_list((int) $row['id']),
], $stmt->fetchAll());

json_response(['ok' => true, 'user' => $adviser, 'groups' => $groups, 'available_students' => available_students()]);
