<?php
/**
 * MTI Knowledge Assistant - PHP Backend Configuration
 * Compatible with LAMP Stack (Linux, Apache, MySQL, PHP 7.4+)
 */

// Enable error logging for debugging, hide display errors in production
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Database Configuration (MySQL / MariaDB)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'mti_assistant');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Security & JWT Configuration
define('JWT_SECRET_KEY', getenv('JWT_SECRET_KEY') ?: 'mti-rag-stateless-secret-key-2026');
define('JWT_ALGORITHM', 'HS256');
define('ACCESS_TOKEN_EXPIRE_MINUTES', (int)(getenv('ACCESS_TOKEN_EXPIRE_MINUTES') ?: 10080));

// LLM API Credentials & Settings
define('PRIMARY_LLM_PROVIDER', getenv('PRIMARY_LLM_PROVIDER') ?: 'groq'); // 'groq' or 'gemini'

define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '');
define('GROQ_MODEL', getenv('GROQ_MODEL') ?: 'llama-3.3-70b-versatile');

define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_MODEL', getenv('GEMINI_MODEL') ?: 'gemini-1.5-flash');

// Storage Paths
define('BASE_DIR', __DIR__);
define('DATA_DIR', BASE_DIR . '/data');
define('EXTRACTED_IMAGES_DIR', BASE_DIR . '/data/extracted_images');

// Ensure data directories exist
if (!file_exists(DATA_DIR)) {
    @mkdir(DATA_DIR, 0755, true);
}
if (!file_exists(EXTRACTED_IMAGES_DIR)) {
    @mkdir(EXTRACTED_IMAGES_DIR, 0755, true);
}
