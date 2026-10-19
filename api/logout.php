<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

start_secure_session();
require_post();
verify_csrf();

logout_user();

json_response([
    'ok' => true,
    'message' => 'Signed out.',
    'redirect' => app_url('pages/login.php'),
]);
