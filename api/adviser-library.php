<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$adviser = require_role_api('adviser');

$stmt = db()->prepare('SELECT id, group_name FROM capstone_groups WHERE adviser_id = ? ORDER BY group_name');
$stmt->execute([$adviser['id']]);

$groups = array_map(static function (array $group): array {
    $groupId = (int) $group['id'];

    return [
        'id'         => $groupId,
        'name'       => $group['group_name'],
        'references' => group_reference_rows($groupId),
        'files'      => array_map(
            static fn (array $row): array => file_view($row, 0, false),
            group_file_rows($groupId)
        ),
    ];
}, $stmt->fetchAll());

json_response(['ok' => true, 'user' => $adviser, 'groups' => $groups]);
