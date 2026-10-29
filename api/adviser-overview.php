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
            (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) AS member_count,
            (SELECT t.title  FROM topics t WHERE t.group_id = g.id AND t.status <> 'Rejected' ORDER BY t.id DESC LIMIT 1) AS topic_title,
            (SELECT t.status FROM topics t WHERE t.group_id = g.id AND t.status <> 'Rejected' ORDER BY t.id DESC LIMIT 1) AS topic_status,
            (SELECT COUNT(*) FROM group_milestones m WHERE m.group_id = g.id) AS ms_total,
            (SELECT COUNT(*) FROM group_milestones m WHERE m.group_id = g.id AND m.status = 'Completed') AS ms_done,
            (SELECT COUNT(*) FROM group_milestones m
               JOIN milestone_templates t ON t.id = m.template_id
              WHERE m.group_id = g.id AND m.status <> 'Completed' AND t.due_date < ?) AS ms_overdue
       FROM capstone_groups g
      WHERE g.adviser_id = ?
      ORDER BY g.group_name"
);
$stmt->execute([date('Y-m-d'), $adviser['id']]);

$groups = [];
$stats = ['groups' => 0, 'students' => 0, 'pending_review' => 0, 'overdue_milestones' => 0];
$attention = [];

foreach ($stmt->fetchAll() as $row) {
    $group = [
        'id'           => (int) $row['id'],
        'name'         => $row['group_name'],
        'member_count' => (int) $row['member_count'],
        'topic_title'  => $row['topic_title'],
        'topic_status' => $row['topic_status'],
        'ms_total'     => (int) $row['ms_total'],
        'ms_done'      => (int) $row['ms_done'],
        'ms_overdue'   => (int) $row['ms_overdue'],
    ];
    $groups[] = $group;

    $stats['groups']++;
    $stats['students'] += $group['member_count'];
    $stats['overdue_milestones'] += $group['ms_overdue'];

    if ($group['topic_status'] === 'Proposed') {
        $stats['pending_review']++;
        $attention[] = ['group' => $group['name'], 'type' => 'review', 'message' => 'Topic is waiting for your review.'];
    }
    if ($group['member_count'] === 0) {
        $attention[] = ['group' => $group['name'], 'type' => 'members', 'message' => 'No students have been added yet.'];
    } elseif ($group['topic_status'] === null) {
        $attention[] = ['group' => $group['name'], 'type' => 'no-topic', 'message' => 'No topic has been proposed yet.'];
    }
    if ($group['ms_overdue'] > 0) {
        $count = $group['ms_overdue'];
        $attention[] = ['group' => $group['name'], 'type' => 'overdue', 'message' => $count . ($count === 1 ? ' milestone is' : ' milestones are') . ' overdue.'];
    }
}

json_response(['ok' => true, 'user' => $adviser, 'stats' => $stats, 'groups' => $groups, 'attention' => $attention]);
