<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sfchrms');
define('DB_PORT', 3306);

// Paths
define('BASE_PATH', dirname(dirname(__FILE__))); 
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('CORE_PATH', BASE_PATH . '/core');

// App Settings
define('APP_NAME', 'SF College HRMS');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/sfchrms');
define('APP_ENV', 'development');

// Security
define('ENCRYPTION_KEY', 'your-secret-key-change-in-production');
define('SESSION_TIMEOUT', 1800); // 30 minutes

// Pagination
define('ITEMS_PER_PAGE', 10);

// File Upload
define('MAX_UPLOAD_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'xlsx']);
define('UPLOAD_PATH', STORAGE_PATH . '/uploads/');