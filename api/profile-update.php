<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/profile.php';

start_secure_session();
require_post();
verify_csrf();

$user = require_role_api();

$stmt = db()->prepare('SELECT first_name, last_name, title, email, password_hash, avatar_path, profile_updated_at FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$row = $stmt->fetch();

// The 30-day lock: nothing can be changed until it ends
$lock = profile_lock_status($row['profile_updated_at']);
if ($lock['locked']) {
    json_response([
        'ok' => false,
        'message' => 'Your profile is locked until ' . $lock['unlocks_on'] . ' (' . $lock['days_left'] . ' day(s) left).',
        'lock' => $lock,
    ], 423);
}

$first = clean_person_name((string) ($_POST['first_name'] ?? $row['first_name']));
$last  = clean_person_name((string) ($_POST['last_name'] ?? $row['last_name']));
$email = strtolower(trim((string) ($_POST['email'] ?? $row['email'])));
$newPassword = (string) ($_POST['new_password'] ?? '');
$confirm = (string) ($_POST['confirm_password'] ?? '');
$current = (string) ($_POST['current_password'] ?? '');
$removeAvatar = ($_POST['remove_avatar'] ?? '') === '1';

$title = $row['title'];
if ($user['role'] === 'adviser' && array_key_exists('title', $_POST)) {
    $t = clean_person_name((string) $_POST['title']);
    $title = $t === '' ? null : $t;
}

$errors = [];

if (($m = validate_person_name($first, 'first name')) !== null) $errors['first_name'] = $m;
if (($m = validate_person_name($last, 'last name')) !== null) $errors['last_name'] = $m;
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || text_length($email) > 150) $errors['email'] = 'Enter a valid email address.';
if ($title !== null && (text_length($title) > 30 || !preg_match('/^\p{L}[\p{L}\p{M}.\- ]*$/u', $title))) {
    $errors['title'] = 'Use letters only, up to 30 characters, for example Prof., Dr., or Engr.';
}

$emailChanged = $email !== $row['email'];
$passwordChanged = $newPassword !== '';

if ($passwordChanged) {
    if (($m = validate_new_password($newPassword)) !== null) $errors['new_password'] = $m;
    elseif ($newPassword !== $confirm) $errors['confirm_password'] = 'Passwords do not match.';
}

if ($emailChanged && !isset($errors['email'])) {
    $dup = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    $dup->execute([$email, $user['id']]);
    if ($dup->fetch()) $errors['email'] = 'An account with this email already exists.';
}

$avatarType = null;
$hasUpload = isset($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
if ($hasUpload) {
    [$avatarError, $avatarType] = check_avatar_upload($_FILES['avatar']);
    if ($avatarError !== null) $errors['avatar'] = $avatarError;
}

// Changing the email or password needs the current password (slowed down against guessing)
if (($emailChanged || $passwordChanged) && $errors === []) {
    $fail = $_SESSION['profile_fail'] ?? ['count' => 0, 'until' => 0];
    if ($fail['until'] > time()) {
        json_response(['ok' => false, 'message' => 'Too many wrong passwords. Try again in a few minutes.'], 429);
    }
    if ($current === '' || !password_verify($current, $row['password_hash'])) {
        $fail['count']++;
        if ($fail['count'] >= 5) {
            $fail = ['count' => 0, 'until' => time() + 300];
        }
        $_SESSION['profile_fail'] = $fail;
        $errors['current_password'] = 'Enter your current password to change your email or password.';
    } else {
        unset($_SESSION['profile_fail']);
    }
}

if ($errors !== []) {
    json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], 422);
}

$changed = $first !== $row['first_name'] || $last !== $row['last_name'] || $emailChanged
    || $passwordChanged || $title !== $row['title'] || $hasUpload || ($removeAvatar && $row['avatar_path']);

// Nothing changed: do not start the lock
if (!$changed) {
    json_response(['ok' => true, 'message' => 'No changes to save.', 'changed' => false, 'lock' => $lock]);
}

$avatarPath = $row['avatar_path'];
if ($hasUpload) {
    $avatarPath = store_avatar($_FILES['avatar']['tmp_name'], $avatarType);
} elseif ($removeAvatar) {
    $avatarPath = null;
}

$sql = 'UPDATE users SET first_name = ?, last_name = ?, full_name = ?, title = ?, email = ?, avatar_path = ?, profile_updated_at = NOW()';
$params = [$first, $last, trim($first . ' ' . $last), $title, $email, $avatarPath];
if ($passwordChanged) {
    $sql .= ', password_hash = ?';
    $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
}
$params[] = $user['id'];

db()->prepare($sql . ' WHERE id = ?')->execute($params);

if ($avatarPath !== $row['avatar_path']) {
    delete_avatar_file($row['avatar_path']);
}
if ($passwordChanged) {
    session_regenerate_id(true);
}

$read = db()->prepare('SELECT profile_updated_at FROM users WHERE id = ?');
$read->execute([$user['id']]);

json_response([
    'ok' => true,
    'message' => 'Profile saved. You can change it again in ' . PROFILE_LOCK_DAYS . ' days.',
    'changed' => true,
    'lock' => profile_lock_status((string) $read->fetchColumn()),
]);
