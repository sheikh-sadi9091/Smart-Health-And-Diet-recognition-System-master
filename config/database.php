<?php
/**
 * Database Configuration using PDO
 * Smart Health & Diet Recommendation System
 */

// Database credentials for XAMPP
define('DB_HOST', 'localhost');
define('DB_NAME', 'smart_health_diet');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a PDO database connection instance.
 *
 * @return PDO
 */
function getDBConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // For security, do not leak raw credentials in production
            die("Database Connection Failed: " . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}
