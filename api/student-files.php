<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = require_role_api('student');
$membership = student_membership($user['id']);
$settings = upload_settings();

if ($membership === null) {
    json_response(['ok' => true, 'group' => null, 'files' => [], 'limits' => ['max_size' => $settings['max_size'], 'allowed_ext' => $settings['allowed_ext']]]);
}

$files = array_map(
    static fn (array $row): array => file_view($row, $user['id'], $membership['is_leader']),
    group_file_rows($membership['group_id'])
);

json_response([
    'ok' => true,
    'group' => ['id' => $membership['group_id'], 'name' => $membership['group_name']],
    'files' => $files,
    'limits' => ['max_size' => $settings['max_size'], 'allowed_ext' => $settings['allowed_ext']],
]);
