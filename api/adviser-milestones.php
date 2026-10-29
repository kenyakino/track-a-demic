<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$adviser = require_role_api('adviser');

$groupStmt = db()->prepare('SELECT id, group_name FROM capstone_groups WHERE adviser_id = ? ORDER BY group_name');
$groupStmt->execute([$adviser['id']]);
$groups = array_map(static fn (array $g): array => ['id' => (int) $g['id'], 'name' => $g['group_name']], $groupStmt->fetchAll());

$tplStmt = db()->prepare('SELECT id, name, due_date, term FROM milestone_templates WHERE adviser_id = ? ORDER BY due_date, id');
$tplStmt->execute([$adviser['id']]);
$templates = $tplStmt->fetchAll();

$asgStmt = db()->prepare(
    'SELECT gm.id, gm.template_id, gm.group_id, g.group_name, gm.status, gm.completed_at, t.name, t.due_date, t.term
       FROM group_milestones gm
       JOIN capstone_groups g ON g.id = gm.group_id
       JOIN milestone_templates t ON t.id = gm.template_id
      WHERE g.adviser_id = ?
      ORDER BY g.group_name'
);
$asgStmt->execute([$adviser['id']]);
$assignmentRows = $asgStmt->fetchAll();

$comments = target_comments_map('milestone', array_map(static fn (array $r): int => (int) $r['id'], $assignmentRows));

$byTemplate = [];
foreach ($assignmentRows as $row) {
    $view = milestone_state_view($row, MILESTONE_ADVISER_TRANSITIONS, $comments[(int) $row['id']] ?? []);
    $view['group_id'] = (int) $row['group_id'];
    $view['group_name'] = $row['group_name'];
    $byTemplate[(int) $row['template_id']][] = $view;
}

$milestones = [];
foreach ($templates as $tpl) {
    $assignments = $byTemplate[(int) $tpl['id']] ?? [];
    $milestones[] = [
        'id'          => (int) $tpl['id'],
        'name'        => $tpl['name'],
        'due_date'    => $tpl['due_date'],
        'term'        => $tpl['term'],
        'assignments' => $assignments,
        'done'        => count(array_filter($assignments, static fn (array $a): bool => $a['status'] === 'Completed')),
        'total'       => count($assignments),
    ];
}

json_response(['ok' => true, 'user' => $adviser, 'groups' => $groups, 'milestones' => $milestones]);
