<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/* ------------------------------------------------------------
 * Sessions
 * ---------------------------------------------------------- */

/** Starts the session with safe cookie settings (once per request). */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name('trackademic_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Marks a user as logged in. A new session id prevents session fixation. */
function login_user(int $userId): void
{
    start_secure_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    unset($_SESSION['csrf'], $_SESSION['login_fail']);
}

/** Ends the session and removes the session cookie. */
function logout_user(): void
{
    start_secure_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?: 'Lax',
        ]);
    }

    session_destroy();
}

/** Returns the logged-in user (id, full_name, email, role) or null. */
function current_user(): ?array
{
    start_secure_session();

    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id)) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, full_name, email, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user) {
        logout_user();
        return null;
    }

    $user['id'] = (int) $user['id'];
    return $user;
}

/* ------------------------------------------------------------
 * URLs and output
 * ---------------------------------------------------------- */

/** Builds a URL that respects app.base_url from config.php. */
function app_url(string $path = ''): string
{
    $base = rtrim((string) (app_config()['app']['base_url'] ?? ''), '/');
    return $base . '/' . ltrim($path, '/');
}

/** Escapes text for safe output in HTML. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Length of a text in characters (works even if the mbstring extension is missing). */
function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

/** Where each role lands after login. */
function dashboard_path(string $role): string
{
    return $role === 'adviser' ? 'pages/adviser/dashboard.php' : 'pages/student/dashboard.php';
}

/* ------------------------------------------------------------
 * Access control for pages
 * ---------------------------------------------------------- */

/**
 * Use at the top of a page that only one role may open.
 * Visitors who are not signed in go to the login page; the wrong role
 * is sent back to its own dashboard.
 */
function require_role_page(string $role): array
{
    $user = current_user();

    if ($user === null) {
        header('Location: ' . app_url('pages/login.php'));
        exit;
    }

    if ($user['role'] !== $role) {
        header('Location: ' . app_url(dashboard_path($user['role'])));
        exit;
    }

    return $user;
}

/* ------------------------------------------------------------
 * JSON API helpers
 * ---------------------------------------------------------- */

/** Sends a JSON response and stops the script. */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/** Reads the JSON body of a request as an array. */
function request_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw === false ? '' : $raw, true);

    if (!is_array($data)) {
        json_response(['ok' => false, 'message' => 'Invalid request.'], 400);
    }

    return $data;
}

/** Rejects any request that is not a POST. */
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }
}

/**
 * Use at the top of an API endpoint that needs a signed-in user.
 * Pass one or more allowed roles to restrict it further.
 */
function require_role_api(string ...$roles): array
{
    $user = current_user();

    if ($user === null) {
        json_response(['ok' => false, 'message' => 'Please sign in first.'], 401);
    }

    if ($roles !== [] && !in_array($user['role'], $roles, true)) {
        json_response(['ok' => false, 'message' => 'You do not have access to this.'], 403);
    }

    return $user;
}

/* ------------------------------------------------------------
 * CSRF protection
 * ---------------------------------------------------------- */

/** Returns the CSRF token for this session (created on first use). */
function csrf_token(): string
{
    start_secure_session();

    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

/** Stops the request unless the X-CSRF-Token header matches the session token. */
function verify_csrf(): void
{
    start_secure_session();

    $sent  = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $known = (string) ($_SESSION['csrf'] ?? '');

    if ($known === '' || !hash_equals($known, $sent)) {
        json_response(['ok' => false, 'message' => 'Your session expired. Reload the page and try again.'], 403);
    }
}
