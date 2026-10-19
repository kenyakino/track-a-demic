<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

// A valid bcrypt hash of a random string. It is checked when the email is
// unknown so the response time does not reveal which emails have accounts.
const DUMMY_HASH = '$2y$10$aC2Yf7DahgC8d4JjYkUqW.h9RZt1DXP6U.7DcV96sD.6n/a8sH52.';

const MAX_FAILED_ATTEMPTS = 5;
const LOCK_SECONDS = 60;

start_secure_session();
require_post();
verify_csrf();

$in       = request_json();
$email    = strtolower(trim((string) ($in['email'] ?? '')));
$password = (string) ($in['password'] ?? '');

if ($email === '' || $password === '') {
    json_response(['ok' => false, 'message' => 'Enter your email and password.'], 422);
}

// Slow down repeated wrong guesses
$fail = $_SESSION['login_fail'] ?? ['count' => 0, 'until' => 0];
if ($fail['until'] > time()) {
    $wait = $fail['until'] - time();
    json_response(['ok' => false, 'message' => "Too many attempts. Try again in {$wait} seconds."], 429);
}

$stmt = db()->prepare('SELECT id, password_hash, role FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

$passwordOk = password_verify($password, $user['password_hash'] ?? DUMMY_HASH);

if (!$user || !$passwordOk) {
    $fail['count']++;
    if ($fail['count'] >= MAX_FAILED_ATTEMPTS) {
        $fail = ['count' => 0, 'until' => time() + LOCK_SECONDS];
    }
    $_SESSION['login_fail'] = $fail;

    json_response(['ok' => false, 'message' => 'Incorrect email or password.'], 401);
}

// Upgrade the stored hash if PHP's default algorithm has become stronger
if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
    $rehash = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $rehash->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
}

login_user((int) $user['id']);

json_response([
    'ok' => true,
    'message' => 'Signed in.',
    'redirect' => app_url(dashboard_path($user['role'])),
]);
