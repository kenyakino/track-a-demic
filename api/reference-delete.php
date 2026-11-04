<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

[$user, $membership] = require_student_group();
$referenceId = (int) (request_json()['id'] ?? 0);

$delete = db()->prepare('DELETE FROM references_list WHERE id = ? AND group_id = ?');
$delete->execute([$referenceId, $membership['group_id']]);

if ($delete->rowCount() === 0) {
    json_response(['ok' => false, 'message' => 'Reference not found.'], 404);
}

json_response(['ok' => true, 'message' => 'Reference deleted.']);
