<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

[$user, $membership] = require_student_group();
$in = request_json();

[$title, $author, $year, $link, $errors] = validate_reference_input($in);
if ($errors !== []) {
    json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], 422);
}

$referenceId = (int) ($in['id'] ?? 0);

if ($referenceId > 0) {
    // The reference must belong to the student's own group
    $find = db()->prepare('SELECT id FROM references_list WHERE id = ? AND group_id = ? LIMIT 1');
    $find->execute([$referenceId, $membership['group_id']]);
    if (!$find->fetch()) {
        json_response(['ok' => false, 'message' => 'Reference not found.'], 404);
    }

    $update = db()->prepare('UPDATE references_list SET title = ?, author = ?, pub_year = ?, link = ? WHERE id = ? AND group_id = ?');
    $update->execute([$title, $author, $year, $link, $referenceId, $membership['group_id']]);

    json_response(['ok' => true, 'message' => 'Reference updated.']);
}

$insert = db()->prepare('INSERT INTO references_list (group_id, title, author, pub_year, link) VALUES (?, ?, ?, ?, ?)');
$insert->execute([$membership['group_id'], $title, $author, $year, $link]);

json_response(['ok' => true, 'message' => 'Reference added.'], 201);
