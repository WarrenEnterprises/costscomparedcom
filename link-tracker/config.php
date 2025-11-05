<?php
/**
 * Link Tracker Configuration File
 * 
 * IMPORTANT: Update these settings before installation
 * Make sure to keep this file secure and never commit credentials to version control
 */

// Prevent direct access
if (!defined('LT_INIT')) {
    die('Direct access not permitted');
}

// ═══════════════════════════════════════════════════════════
// DATABASE CONFIGURATION
// ⚠️ REQUIRED: Update these with your database credentials
// ═══════════════════════════════════════════════════════════
define('DB_HOST', 'localhost');              // Database host (usually 'localhost')
define('DB_NAME', 'u609212978_costscompared');     // costscompared.com database
define('DB_USER', 'u609212978_costscomparedu');     // costscompared.com database user
define('DB_PASS', '&erAFQRDJ&BRcBnR%w5M*L'); // costscompared.com database password
define('DB_CHARSET', 'utf8mb4');             // Character set (leave as is)

// ═══════════════════════════════════════════════════════════
// TABLE PREFIX
// ═══════════════════════════════════════════════════════════
define('DB_PREFIX', 'lt_');  // Prefix for all database tables

// ═══════════════════════════════════════════════════════════
// SECURITY SETTINGS
// ⚠️ RECOMMENDED: Change these to random strings for better security!
// Generate random strings here: https://www.random.org/strings/
// ═══════════════════════════════════════════════════════════
define('FINGERPRINT_SALT', 'mK8pL3nV9wX2jT7qR4bY6zC1dF5eG0hN');    // Secure random salt
define('SESSION_SECRET', 'zP5rW8kY1mN4qT7sV3xB9cL2eF6jH0gD'); // Secure session secret
define('CSRF_SECRET', 'wM9pK2qT5rY8sX1vZ4bC7eF3hG6jL0nD'); // Secure CSRF secret

// Session settings
define('SESSION_LIFETIME', 86400);        // 24 hours in seconds
define('SESSION_LIFETIME_REMEMBER', 2592000); // 30 days in seconds

// Brute force protection settings
define('MAX_LOGIN_ATTEMPTS_BEFORE_CAPTCHA', 3);
define('MAX_LOGIN_ATTEMPTS_BEFORE_BLOCK', 5);
define('LOGIN_BLOCK_DURATION', 900);      // 15 minutes in seconds
define('MAX_LOGIN_ATTEMPTS_EXTENDED_BLOCK', 10);
define('LOGIN_EXTENDED_BLOCK_DURATION', 3600); // 1 hour in seconds

// ═══════════════════════════════════════════════════════════
// SITE CONFIGURATION  
// ⚠️ REQUIRED: Update your domain URL
// ═══════════════════════════════════════════════════════════
define('SITE_URL', 'https://costscompared.com');  // costscompared.com domain
define('TRACKER_PATH', '/link-tracker');       // Path to tracker folder (leave as is unless renamed)

// ═══════════════════════════════════════════════════════════
// GEOIP CONFIGURATION
// ═══════════════════════════════════════════════════════════
define('GEOIP_ENABLED', true);
define('GEOIP_METHOD', 'ip-api');  // Options: 'ip-api' (free), 'maxmind' (requires setup)
define('GEOIP_CACHE_TIME', 86400); // Cache GeoIP results for 24 hours

// For MaxMind (if using)
define('MAXMIND_DATABASE_PATH', __DIR__ . '/geoip/GeoLite2-City.mmdb');

// ═══════════════════════════════════════════════════════════
// TIMEZONE
// ═══════════════════════════════════════════════════════════
date_default_timezone_set('UTC');  // Change to your timezone if needed

// ═══════════════════════════════════════════════════════════
// ERROR HANDLING
// ═══════════════════════════════════════════════════════════
define('DEBUG_MODE', false);  // Set to false in production!
define('LOG_ERRORS', true);
define('ERROR_LOG_FILE', __DIR__ . '/logs/error.log');

// Error reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ERROR_LOG_FILE);
}

// ═══════════════════════════════════════════════════════════
// PAGINATION & LIMITS
// ═══════════════════════════════════════════════════════════
define('LINKS_PER_PAGE', 20);
define('CLICKS_PER_PAGE', 50);
define('RECENT_ACTIVITY_LIMIT', 20);

// ═══════════════════════════════════════════════════════════
// CSV EXPORT SETTINGS
// ═══════════════════════════════════════════════════════════
define('CSV_DELIMITER', ',');
define('CSV_ENCLOSURE', '"');

// ═══════════════════════════════════════════════════════════
// CHARTS & ANALYTICS
// ═══════════════════════════════════════════════════════════
define('DEFAULT_ANALYTICS_DAYS', 30);
define('MAX_CHART_ITEMS', 10);  // Max items to show in pie/bar charts

// ═══════════════════════════════════════════════════════════
// USER AGENT BOT DETECTION
// ═══════════════════════════════════════════════════════════
// Common bot user agents for cloaking detection
define('BOT_USER_AGENTS', [
    'googlebot',
    'bingbot',
    'slurp',
    'duckduckbot',
    'baiduspider',
    'yandexbot',
    'sogou',
    'exabot',
    'facebot',
    'ia_archiver',
    'semrushbot',
    'ahrefsbot',
    'mj12bot',
    'dotbot',
]);

// ═══════════════════════════════════════════════════════════
// PATHS
// ═══════════════════════════════════════════════════════════
define('BASE_PATH', __DIR__);
define('LOGS_PATH', BASE_PATH . '/logs');
define('GEOIP_PATH', BASE_PATH . '/geoip');
define('CACHE_PATH', BASE_PATH . '/cache');

// Create necessary directories if they don't exist
$directories = [LOGS_PATH, GEOIP_PATH, CACHE_PATH];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// ═══════════════════════════════════════════════════════════
// VERSION
// ═══════════════════════════════════════════════════════════
define('LT_VERSION', '1.0.0');
