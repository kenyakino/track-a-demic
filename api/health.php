<?php
declare(strict_types=1);

/**
 * Health check endpoint.
 * Open /api/health.php in the browser to confirm the database connection works.
 */
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    db()->query('SELECT 1');
    echo json_encode(['ok' => true, 'database' => 'connected']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'database' => 'unavailable']);
}
