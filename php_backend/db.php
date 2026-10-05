<?php
/**
 * Database Connection & Migration Helper for MySQL
 */

require_once __DIR__ . '/config.php';

function get_db_connection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // If database does not exist, attempt to create it
        if ($e->getCode() == 1049) {
            try {
                $rootDsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
                $rootPdo = new PDO($rootDsn, DB_USER, DB_PASS, $options);
                $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (Exception $ex) {
                error_log("Failed creating database: " . $ex->getMessage());
                throw $ex;
            }
        } else {
            throw $e;
        }
    }

    // Auto-migrate tables if needed
    init_db_schema($pdo);

    return $pdo;
}

function init_db_schema(PDO $pdo) {
    // 1. Users Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(150) NOT NULL DEFAULT 'Meteorologist',
            `email` VARCHAR(191) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NULL,
            `role` VARCHAR(150) DEFAULT 'Operational Meteorologist',
            `organization` VARCHAR(255) DEFAULT 'India Meteorological Department (IMD)',
            `response_tone` VARCHAR(50) DEFAULT 'moderate',
            `custom_instructions` TEXT NULL,
            `use_emojis` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Threads Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `threads` (
            `id` VARCHAR(100) PRIMARY KEY,
            `user_id` INT NOT NULL DEFAULT 1,
            `title` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 3. Messages Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `messages` (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `thread_id` VARCHAR(100) NOT NULL,
            `question` TEXT NOT NULL,
            `answer` LONGTEXT NOT NULL,
            `sources_json` LONGTEXT NULL,
            `images_json` LONGTEXT NULL,
            `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`thread_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 4. Documents Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `documents` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `filename` VARCHAR(255) NOT NULL UNIQUE,
            `file_size` BIGINT DEFAULT 0,
            `page_count` INT DEFAULT 0,
            `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 5. Document Chunks Table for RAG Context Search
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `document_chunks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `document_id` INT NOT NULL,
            `filename` VARCHAR(255) NOT NULL,
            `page_number` INT NOT NULL,
            `chunk_index` INT NOT NULL,
            `content` LONGTEXT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FULLTEXT KEY `ft_content` (`content`),
            INDEX (`filename`),
            INDEX (`document_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}
