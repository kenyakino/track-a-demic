<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

$adviser = require_role_api('adviser');
$studentId = (int) (request_json()['user_id'] ?? 0);

// Only a student who is in one of this adviser's groups can be reset
$stmt = db()->prepare(
    "SELECT u.id, u.full_name
       FROM users u
       JOIN group_members gm ON gm.user_id = u.id
       JOIN capstone_groups g ON g.id = gm.group_id
      WHERE u.id = ? AND u.role = 'student' AND g.adviser_id = ?
      LIMIT 1"
);
$stmt->execute([$studentId, $adviser['id']]);
$student = $stmt->fetch();

if (!$student) {
    json_response(['ok' => false, 'message' => 'Student not found in your groups.'], 404);
}

// The default password is hashed before it is saved. The plain text is never stored.
$hash = password_hash(default_reset_password(), PASSWORD_DEFAULT);

$update = db()->prepare("UPDATE users SET password_hash = ?, profile_updated_at = NULL WHERE id = ? AND role = 'student'");
$update->execute([$hash, (int) $student['id']]);

add_notification((int) $student['id'], 'Your adviser reset your password. Ask them for the default password and sign in with it.');

json_response(['ok' => true, 'message' => 'Password reset for ' . $student['full_name'] . '.']);
