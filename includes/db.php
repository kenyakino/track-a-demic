<?php
declare(strict_types=1);

/**
 * Loads config/config.php once and returns it as an array.
 * Also applies the configured timezone.
 */
function app_config(): array
{
    static $config = null;

    if ($config === null) {
        $file = __DIR__ . '/../config/config.php';

        if (!is_file($file)) {
            http_response_code(500);
            exit('Missing config/config.php. Copy config/config.example.php to config/config.php and fill in your database details.');
        }

        $config = require $file;
        date_default_timezone_set((string) ($config['app']['timezone'] ?? 'Asia/Manila'));
    }

    return $config;
}

/**
 * Returns one shared PDO connection to the MySQL database.
 * Always use prepared statements with this connection.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $config = app_config();
        $db = $config['db'];

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $db['host'],
            $db['name'],
            $db['charset']
        );

        try {
            $pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            show_database_unavailable($e->getMessage(), ($config['app']['env'] ?? 'production') === 'development');
        }
    }

    return $pdo;
}

/** Sends a clear "try again" response (JSON for the API, a small page otherwise) and stops. */
function show_database_unavailable(string $detail, bool $showDetail): void
{
    $message = 'The database is unavailable right now. Please try again in a moment.';
    $isApi = str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/api/');

    http_response_code(503);

    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        $body = ['ok' => false, 'message' => $message];
        if ($showDetail) {
            $body['detail'] = $detail;
        }
        echo json_encode($body);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Temporarily unavailable | Track-a-Demic</title></head>'
       . '<body style="font-family:system-ui,sans-serif;max-width:32rem;margin:4rem auto;padding:0 1.25rem;line-height:1.5">'
       . '<h1>Temporarily unavailable</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($showDetail) {
        echo '<pre style="white-space:pre-wrap">' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</pre>';
    }
    echo '</body></html>';
    exit;
}
