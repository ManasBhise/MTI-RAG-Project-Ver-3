-- ====================================================================
-- MTI Knowledge Assistant - MySQL Database Schema for LAMP Deployment
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `mti_assistant` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `mti_assistant`;

-- 1. Users Table
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

-- 2. Threads Table
CREATE TABLE IF NOT EXISTS `threads` (
    `id` VARCHAR(100) PRIMARY KEY,
    `user_id` INT NOT NULL DEFAULT 1,
    `title` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Messages Table
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

-- 4. Documents Table
CREATE TABLE IF NOT EXISTS `documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `filename` VARCHAR(255) NOT NULL UNIQUE,
    `file_size` BIGINT DEFAULT 0,
    `page_count` INT DEFAULT 0,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Document Chunks Table for RAG Context Search
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

-- Default Admin / Initial User
INSERT IGNORE INTO `users` (`id`, `name`, `email`, `role`, `organization`)
VALUES (1, 'Meteorologist', 'meteorologist@imd.gov.in', 'Operational Meteorologist', 'India Meteorological Department (IMD)');
