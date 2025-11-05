<?php
/**
 * Link Tracker Configuration Template
 * 
 * This is a template file. The actual config.php has been configured with
 * viewedonline.com credentials and is NOT committed to version control.
 * 
 * DO NOT edit this template - it's for reference only.
 * Edit config.php directly on the server if changes are needed.
 */

// ═══════════════════════════════════════════════════════════
// ⚠️ IMPORTANT SECURITY NOTICE
// ═══════════════════════════════════════════════════════════
// 
// config.php is already configured with database credentials for:
// - Database: u609212978_viewedonline
// - Site URL: https://viewedonline.com
// - Security salts: Generated and configured
//
// The actual config.php file is excluded from Git (.gitignore)
// to prevent credentials from being exposed in the repository.
//
// ═══════════════════════════════════════════════════════════

// Prevent direct access
if (!defined('LT_INIT')) {
    die('Direct access not permitted');
}

// Database Configuration (configured in actual config.php)
define('DB_HOST', 'localhost');
define('DB_NAME', 'u609212978_viewedonline');
define('DB_USER', 'u609212978_viewedonlineun');
define('DB_PASS', '*** CONFIGURED ON SERVER ***');
define('DB_CHARSET', 'utf8mb4');

// Table Prefix
define('DB_PREFIX', 'lt_');

// Security Settings (configured with secure random strings in actual config.php)
define('FINGERPRINT_SALT', '*** CONFIGURED ON SERVER ***');
define('SESSION_SECRET', '*** CONFIGURED ON SERVER ***');
define('CSRF_SECRET', '*** CONFIGURED ON SERVER ***');

// Session settings
define('SESSION_LIFETIME', 86400);
define('SESSION_LIFETIME_REMEMBER', 2592000);

// Brute force protection
define('MAX_LOGIN_ATTEMPTS_BEFORE_CAPTCHA', 3);
define('MAX_LOGIN_ATTEMPTS_BEFORE_BLOCK', 5);
define('LOGIN_BLOCK_DURATION', 900);
define('MAX_LOGIN_ATTEMPTS_EXTENDED_BLOCK', 10);
define('LOGIN_EXTENDED_BLOCK_DURATION', 3600);

// Site Configuration (configured in actual config.php)
define('SITE_URL', 'https://viewedonline.com');
define('TRACKER_PATH', '/link-tracker');

// GeoIP Configuration
define('GEOIP_ENABLED', true);
define('GEOIP_METHOD', 'ip-api');
define('GEOIP_CACHE_TIME', 86400);
define('MAXMIND_DATABASE_PATH', __DIR__ . '/geoip/GeoLite2-City.mmdb');

// Timezone
date_default_timezone_set('UTC');

// Error Handling
define('DEBUG_MODE', false);
define('LOG_ERRORS', true);
define('ERROR_LOG_FILE', __DIR__ . '/logs/error.log');

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ERROR_LOG_FILE);
}

// Pagination & Limits
define('LINKS_PER_PAGE', 20);
define('CLICKS_PER_PAGE', 50);
define('RECENT_ACTIVITY_LIMIT', 20);

// CSV Export
define('CSV_DELIMITER', ',');
define('CSV_ENCLOSURE', '"');

// Charts & Analytics
define('DEFAULT_ANALYTICS_DAYS', 30);
define('MAX_CHART_ITEMS', 10);

// Bot User Agents
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

// Paths
define('BASE_PATH', __DIR__);
define('LOGS_PATH', BASE_PATH . '/logs');
define('GEOIP_PATH', BASE_PATH . '/geoip');
define('CACHE_PATH', BASE_PATH . '/cache');

// Create directories
$directories = [LOGS_PATH, GEOIP_PATH, CACHE_PATH];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Version
define('LT_VERSION', '1.0.0');

