<?php
/**
 * Digital GYM - Configuration Example
 * 
 * Copy this file to config.php and update with your settings
 * This file is for reference only
 */

// Database configuration
define('DB_HOST', 'localhost');           // Database host (usually localhost)
define('DB_USER', 'your_db_username');    // Your MySQL username
define('DB_PASS', 'your_db_password');    // Your MySQL password
define('DB_NAME', 'digital_gym');         // Database name

// Application settings
define('SITE_NAME', 'Digital GYM');       // Your gym name
define('BASE_URL', 'http://localhost/DigitalGYM');  // Your application URL
define('UPLOAD_PATH', __DIR__ . '/../uploads/');     // Upload directory path

// Session configuration
ini_set('session.cookie_httponly', 1);    // Prevent JavaScript access to cookies
ini_set('session.use_only_cookies', 1);   // Only use cookies for sessions
ini_set('session.cookie_secure', 0);      // Set to 1 if using HTTPS

// Timezone (optional)
date_default_timezone_set('UTC');         // Set your timezone

// Error reporting (disable in production)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
?>
