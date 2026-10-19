<?php
declare(strict_types=1);

/**
 * Loads config/config.php once and returns it as an array.
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
            http_response_code(500);

            $isDev = ($config['app']['env'] ?? 'production') === 'development';
            exit($isDev
                ? 'Database connection failed: ' . $e->getMessage()
                : 'Database connection failed.');
        }
    }

    return $pdo;
}
