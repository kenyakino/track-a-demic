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
    json_response(['ok' => true, 'group' => null, 'references' => []]);
}

json_response([
    'ok' => true,
    'group' => ['id' => $membership['group_id'], 'name' => $membership['group_name']],
    'references' => group_reference_rows($membership['group_id']),
]);
