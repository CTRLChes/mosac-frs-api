<?php
// Database configuration
// For Aiven MySQL (testing) — replace with z.com credentials for production

define('DB_HOST', getenv('DB_HOST') ?: 'your-aiven-host.aivencloud.com');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'defaultdb');
define('DB_USER', getenv('DB_USER') ?: 'avnadmin');
define('DB_PASS', getenv('DB_PASS') ?: 'your-aiven-password');
define('DB_SSL',  getenv('DB_SSL')  ?: true);

function getConnection() {
    $dsn = 'mysql:host=' . DB_HOST
         . ';port=' . DB_PORT
         . ';dbname=' . DB_NAME
         . ';charset=utf8mb4';

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Aiven requires SSL
    if (DB_SSL) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed',
            'error'   => $e->getMessage(),
        ]);
        exit;
    }
}
