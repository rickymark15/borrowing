<?php
const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'hulambee';

require_once __DIR__ . '/includes/setup.php';

set_exception_handler(function (Throwable $ex): void {
    http_response_code(500);
    echo '<!DOCTYPE html><meta charset="utf-8"><body style="font:16px/1.5 system-ui,sans-serif;max-width:42rem;margin:2rem auto;padding:0 1rem">'
       . '<h1>HulamBee could not start</h1><p>' . htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>'
       . '<p>Check that MySQL is running and that the database settings in <code>config.php</code> are correct.</p></body>';
    exit;
});

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('The PHP extension pdo_mysql is not enabled.');
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        if ((int)$e->getCode() === 1049 || str_contains($e->getMessage(), 'Unknown database')) {
            $rootPdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $rootPdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $rootPdo = null;

            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $options);
        } else {
            throw $e;
        }
    }

    setup_database($pdo);
    return $pdo;
}
