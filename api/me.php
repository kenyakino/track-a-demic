<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$user = require_role_api();

json_response(['ok' => true, 'user' => $user]);
