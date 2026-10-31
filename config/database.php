<?php
// config/database.php

if (file_exists(__DIR__ . '/database.local.php')) {
    require_once __DIR__ . '/database.local.php';
} else {
    // Auto detect if running on live server (aaPanel / skillance.cloud)
    $isLiveServer = (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'skillance.cloud') !== false)
                 || (isset($_SERVER['DOCUMENT_ROOT']) && strpos($_SERVER['DOCUMENT_ROOT'], '/www/wwwroot') !== false)
                 || file_exists('/www/server/nginx');

    if ($isLiveServer) {
        if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
        if (!defined('DB_USER')) define('DB_USER', 'aquiris_user');
        if (!defined('DB_PASS')) define('DB_PASS', 'Aquiris#Print2026!');
        if (!defined('DB_NAME')) define('DB_NAME', 'db_aquiris_print');
        if (!defined('DB_PORT')) define('DB_PORT', 3306);
    } else {
        if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
        if (!defined('DB_USER')) define('DB_USER', 'root');
        if (!defined('DB_PASS')) define('DB_PASS', '');
        if (!defined('DB_NAME')) define('DB_NAME', 'db_aquiris_print');
        if (!defined('DB_PORT')) define('DB_PORT', 3306);
    }
}

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                // If database doesn't exist, try connecting without dbname and create it
                if ($e->getCode() === 1049) {
                    try {
                        $dsnNoDb = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
                        $pdoNoDb = new PDO($dsnNoDb, DB_USER, DB_PASS);
                        $pdoNoDb->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        
                        // Retry connecting
                        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                        self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_EMULATE_PREPARES => false,
                        ]);
                    } catch (PDOException $subEx) {
                        die("Koneksi Database Gagal: " . $subEx->getMessage());
                    }
                } else {
                    die("Koneksi Database Gagal: " . $e->getMessage());
                }
            }
        }
        return self::$instance;
    }
}
