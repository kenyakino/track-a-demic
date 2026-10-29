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
    json_response(['ok' => true, 'group' => null, 'milestones' => [], 'progress' => ['done' => 0, 'total' => 0]]);
}

$stmt = db()->prepare(
    'SELECT gm.id, t.name, t.due_date, t.term, gm.status, gm.completed_at
       FROM group_milestones gm
       JOIN milestone_templates t ON t.id = gm.template_id
      WHERE gm.group_id = ?
      ORDER BY t.due_date, gm.id'
);
$stmt->execute([$membership['group_id']]);
$rows = $stmt->fetchAll();

$comments = target_comments_map('milestone', array_map(static fn (array $r): int => (int) $r['id'], $rows));
$milestones = array_map(
    static fn (array $row): array => milestone_state_view($row, MILESTONE_STUDENT_TRANSITIONS, $comments[(int) $row['id']] ?? []),
    $rows
);
$done = count(array_filter($milestones, static fn (array $m): bool => $m['status'] === 'Completed'));

json_response([
    'ok' => true,
    'group' => ['id' => $membership['group_id'], 'name' => $membership['group_name']],
    'milestones' => $milestones,
    'progress' => ['done' => $done, 'total' => count($milestones)],
]);
