<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

start_secure_session();
require_post();
verify_csrf();

$in = request_json();

$fullName = trim((string) ($in['full_name'] ?? ''));
$email    = strtolower(trim((string) ($in['email'] ?? '')));
$password = (string) ($in['password'] ?? '');
$confirm  = (string) ($in['confirm_password'] ?? '');
$role     = (string) ($in['role'] ?? 'student');
$code     = trim((string) ($in['adviser_code'] ?? ''));

$errors = [];

if (text_length($fullName) < 2 || text_length($fullName) > 100) {
    $errors['full_name'] = 'Enter your full name (2 to 100 characters).';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || text_length($email) > 150) {
    $errors['email'] = 'Enter a valid email address.';
}

if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
    $errors['password'] = 'Use at least 8 characters with a letter and a number.';
} elseif (strlen($password) > 72) {
    $errors['password'] = 'Use 72 characters or fewer.';
}

if ($password !== $confirm) {
    $errors['confirm_password'] = 'Passwords do not match.';
}

if (!in_array($role, ['student', 'adviser'], true)) {
    $errors['role'] = 'Choose student or adviser.';
} elseif ($role === 'adviser') {
    $config   = app_config();
    $expected = (string) ($config['auth']['adviser_code'] ?? '');
    $isDefault = $expected === 'CHANGE-THIS-ADVISER-CODE'
        && ($config['app']['env'] ?? 'production') === 'production';

    if ($expected === '' || $isDefault) {
        $errors['adviser_code'] = 'Adviser sign-up is not available right now.';
    } elseif (!hash_equals($expected, $code)) {
        $errors['adviser_code'] = 'That adviser code is not valid.';
    }
}

if ($errors !== []) {
    json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], 422);
}

$exists = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$exists->execute([$email]);

if ($exists->fetch()) {
    json_response([
        'ok' => false,
        'message' => 'Please fix the highlighted fields.',
        'errors' => ['email' => 'An account with this email already exists.'],
    ], 409);
}

try {
    $insert = db()->prepare(
        'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)'
    );
    $insert->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
} catch (PDOException $e) {
    // 1062 = duplicate key: someone registered the same email at the same moment
    if (($e->errorInfo[1] ?? 0) === 1062) {
        json_response([
            'ok' => false,
            'message' => 'Please fix the highlighted fields.',
            'errors' => ['email' => 'An account with this email already exists.'],
        ], 409);
    }

    error_log('Registration failed: ' . $e->getMessage());
    json_response(['ok' => false, 'message' => 'Could not create the account. Try again.'], 500);
}

json_response([
    'ok' => true,
    'message' => 'Account created.',
    'redirect' => app_url('pages/login.php?registered=1'),
], 201);
