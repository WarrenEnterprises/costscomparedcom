<?php
/**
 * Helper Functions Library
 * All utility and helper functions for the link tracker
 */

// Prevent direct access
if (!defined('LT_INIT')) {
    die('Direct access not permitted');
}

// ═══════════════════════════════════════════════════════════
// LOGGING FUNCTIONS
// ═══════════════════════════════════════════════════════════

/**
 * Log an error to file
 * 
 * @param string $message Error message
 * @param string $level Error level (ERROR, WARNING, INFO)
 */
function log_error($message, $level = 'ERROR') {
    if (!LOG_ERRORS) {
        return;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [$level] $message" . PHP_EOL;
    
    @file_put_contents(ERROR_LOG_FILE, $log_message, FILE_APPEND);
}

// ═══════════════════════════════════════════════════════════
// SECURITY FUNCTIONS
// ═══════════════════════════════════════════════════════════

/**
 * Generate CSRF token
 * 
 * @return string CSRF token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * 
 * @param string $token Token to verify
 * @return bool True if valid
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input string
 * 
 * @param string $input Input string
 * @return string Sanitized string
 */
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 * 
 * @param string $email Email address
 * @return bool True if valid
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate URL
 * 
 * @param string $url URL to validate
 * @return bool True if valid
 */
function validate_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Generate secure random token
 * 
 * @param int $length Token length
 * @return string Random token
 */
function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Hash password
 * 
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password
 * 
 * @param string $password Plain text password
 * @param string $hash Hashed password
 * @return bool True if password matches
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate fingerprint for unique visitor tracking
 * 
 * @param string $ip IP address
 * @param string $user_agent User agent
 * @return string SHA256 fingerprint
 */
function generate_fingerprint($ip, $user_agent) {
    return hash('sha256', $ip . $user_agent . FINGERPRINT_SALT);
}

// ═══════════════════════════════════════════════════════════
// USER AGENT PARSING
// ═══════════════════════════════════════════════════════════

/**
 * Parse user agent to extract device, browser, and OS info
 * 
 * @param string $user_agent User agent string
 * @return array Parsed information
 */
function parse_user_agent($user_agent) {
    $ua = strtolower($user_agent);
    
    // Device detection
    $device = 'desktop';
    if (preg_match('/mobile|android|phone/i', $user_agent)) {
        $device = 'mobile';
    } elseif (preg_match('/tablet|ipad/i', $user_agent)) {
        $device = 'tablet';
    }
    
    // Browser detection (enhanced with versions)
    $browser = 'Unknown';
    $browser_version = null;
    
    // Edge (check first before Chrome)
    if (preg_match('/Edg[\/\s](\d+\.?\d*)/i', $user_agent, $matches)) {
        $browser = 'Edge';
        $browser_version = $matches[1];
    }
    // Chrome (check before Safari)
    elseif (preg_match('/Chrome[\/\s](\d+\.?\d*)/i', $user_agent, $matches)) {
        $browser_version = $matches[1];
        // Check if it's actually Brave, Vivaldi, etc.
        if (preg_match('/Brave/i', $user_agent)) {
            $browser = 'Brave';
        } elseif (preg_match('/Vivaldi/i', $user_agent)) {
            $browser = 'Vivaldi';
        } else {
            $browser = 'Chrome';
        }
    }
    // Safari (not Chrome-based)
    elseif (preg_match('/Safari/i', $user_agent) && !preg_match('/Chrome/i', $user_agent)) {
        $browser = 'Safari';
        if (preg_match('/Version[\/\s](\d+\.?\d*)/i', $user_agent, $matches)) {
            $browser_version = $matches[1];
        }
    }
    // Firefox
    elseif (preg_match('/Firefox[\/\s](\d+\.?\d*)/i', $user_agent, $matches)) {
        $browser = 'Firefox';
        $browser_version = $matches[1];
    }
    // Opera
    elseif (preg_match('/OPR[\/\s](\d+\.?\d*)|Opera[\/\s](\d+\.?\d*)/i', $user_agent, $matches)) {
        $browser = 'Opera';
        $browser_version = $matches[1] ?? ($matches[2] ?? null);
    }
    // Internet Explorer / Trident
    elseif (preg_match('/MSIE (\d+\.?\d*)|Trident\/.*rv:(\d+\.?\d*)/i', $user_agent, $matches)) {
        $browser = 'Internet Explorer';
        $browser_version = $matches[1] ?? ($matches[2] ?? null);
    }
    // Samsung Internet
    elseif (preg_match('/SamsungBrowser[\/\s](\d+\.?\d*)/i', $user_agent, $matches)) {
        $browser = 'Samsung Internet';
        $browser_version = $matches[1];
    }
    // UC Browser
    elseif (preg_match('/UCBrowser[\/\s](\d+\.?\d*)/i', $user_agent, $matches)) {
        $browser = 'UC Browser';
        $browser_version = $matches[1];
    }
    
    // OS detection (enhanced with versions)
    $os = 'Unknown';
    $os_version = null;
    
    // Windows
    if (preg_match('/Windows NT 10\.0/i', $user_agent)) {
        $os = 'Windows 10';
        $os_version = '10';
    } elseif (preg_match('/Windows NT 6\.3/i', $user_agent)) {
        $os = 'Windows 8.1';
        $os_version = '8.1';
    } elseif (preg_match('/Windows NT 6\.2/i', $user_agent)) {
        $os = 'Windows 8';
        $os_version = '8';
    } elseif (preg_match('/Windows NT 6\.1/i', $user_agent)) {
        $os = 'Windows 7';
        $os_version = '7';
    } elseif (preg_match('/Windows NT 6\.0/i', $user_agent)) {
        $os = 'Windows Vista';
        $os_version = 'Vista';
    } elseif (preg_match('/Windows NT 5\.1/i', $user_agent)) {
        $os = 'Windows XP';
        $os_version = 'XP';
    } elseif (preg_match('/Windows/i', $user_agent)) {
        $os = 'Windows';
        if (preg_match('/Windows (\d+\.?\d*)/i', $user_agent, $matches)) {
            $os_version = $matches[1];
        }
    }
    // macOS / Mac OS X
    elseif (preg_match('/Mac OS X (\d+)[._](\d+)(?:[._](\d+))?/i', $user_agent, $matches)) {
        $os = 'macOS';
        $os_version = $matches[1] . '.' . $matches[2] . (isset($matches[3]) ? '.' . $matches[3] : '');
    } elseif (preg_match('/Mac OS X|Macintosh/i', $user_agent)) {
        $os = 'macOS';
    }
    // Android
    elseif (preg_match('/Android (\d+(?:\.\d+)?)/i', $user_agent, $matches)) {
        $os = 'Android';
        $os_version = $matches[1];
    } elseif (preg_match('/Android/i', $user_agent)) {
        $os = 'Android';
    }
    // iOS
    elseif (preg_match('/iPhone OS (\d+)[._](\d+)(?:[._](\d+))?|iOS (\d+)[._](\d+)/i', $user_agent, $matches)) {
        $os = 'iOS';
        $os_version = isset($matches[4]) ? $matches[4] . '.' . $matches[5] : ($matches[1] . '.' . $matches[2]);
    } elseif (preg_match('/iPhone|iPad|iPod/i', $user_agent)) {
        $os = 'iOS';
    }
    // Linux
    elseif (preg_match('/Linux/i', $user_agent)) {
        $os = 'Linux';
        // Try to detect Linux distribution
        if (preg_match('/(Ubuntu|Debian|Fedora|CentOS|Red Hat)/i', $user_agent, $matches)) {
            $os = $matches[1];
        }
    }
    // Chrome OS
    elseif (preg_match('/CrOS/i', $user_agent)) {
        $os = 'Chrome OS';
    }
    
    return [
        'device' => $device,
        'browser' => $browser,
        'browser_version' => $browser_version,
        'os' => $os,
        'os_version' => $os_version
    ];
}

/**
 * Check if user agent is a bot/crawler (enhanced with IP detection)
 * 
 * @param string $user_agent User agent string
 * @param string|null $ip IP address
 * @return bool True if bot
 */
function is_bot($user_agent, $ip = null) {
    if (empty($user_agent) || $user_agent === 'Unknown') {
        return false;
    }
    
    $ua = strtolower($user_agent);
    
    // Check against defined bot patterns
    foreach (BOT_USER_AGENTS as $bot) {
        if (stripos($ua, $bot) !== false) {
            return true;
        }
    }
    
    // Additional bot patterns
    $additional_bots = [
        'bot', 'crawler', 'spider', 'scraper', 'slurp',
        'curl', 'wget', 'python-requests', 'java/', 'go-http-client',
        'postman', 'insomnia', 'httpie', 'okhttp', 'apache-httpclient',
        'facebookexternalhit', 'twitterbot', 'linkedinbot', 'whatsapp',
        'telegrambot', 'discordbot', 'slackbot', 'zoomindex',
        'applebot', 'bingpreview', 'duckduckbot', 'baiduspider',
        'yandexbot', 'sogou', 'exabot', 'mj12bot', 'ahrefsbot',
        'dotbot', 'semrushbot', 'moz.com', 'blexbot', 'petalbot',
        'headless', 'phantom', 'selenium', 'webdriver', 'puppeteer',
        'playwright', 'cypress', 'chromedriver', 'geckodriver'
    ];
    
    foreach ($additional_bots as $bot_pattern) {
        if (stripos($ua, $bot_pattern) !== false) {
            return true;
        }
    }
    
    // Datacenter and known bot IP range detection
    if (!empty($ip)) {
        $datacenter_ranges = [
            '205.169.39.',  // Microsoft datacenter
            '40.77.',       // Microsoft Bing
            '157.55.',      // Microsoft
            '207.46.',      // Microsoft
            '66.249.',      // Google
            '17.0.',        // Apple
        ];
        
        foreach ($datacenter_ranges as $range) {
            if (strpos($ip, $range) === 0) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Infer referrer source from query parameters when HTTP_REFERER is empty
 * 
 * @param string|null $referrer HTTP referrer (may be null)
 * @param string|null $query_string Query string from URL
 * @return array ['source' => string|null, 'confidence' => string]
 */
function infer_referrer_source($referrer, $query_string) {
    // If referrer exists, return it with high confidence
    if (!empty($referrer)) {
        return [
            'source' => parse_url($referrer, PHP_URL_HOST) ?: $referrer,
            'confidence' => 'high'
        ];
    }
    
    // If no query string, cannot infer
    if (empty($query_string)) {
        return [
            'source' => null,
            'confidence' => 'none'
        ];
    }
    
    // Parse query string
    parse_str($query_string, $params);
    
    // Parameter mappings for social media and ad platforms
    $source_mappings = [
        // Social media click IDs (high confidence)
        'fbclid' => ['source' => 'Facebook', 'confidence' => 'high'],
        'gclid' => ['source' => 'Google Ads', 'confidence' => 'high'],
        'msclkid' => ['source' => 'Bing Ads', 'confidence' => 'high'],
        'ttclid' => ['source' => 'TikTok', 'confidence' => 'high'],
        'twclid' => ['source' => 'Twitter', 'confidence' => 'high'],
        'li_fat_id' => ['source' => 'LinkedIn', 'confidence' => 'high'],
        'igshid' => ['source' => 'Instagram', 'confidence' => 'high'],
        'ref_share' => ['source' => 'Pinterest', 'confidence' => 'high'],
        
        // UTM parameters (medium confidence)
        'utm_source' => ['confidence' => 'medium'],
        'ref' => ['confidence' => 'medium'],
        'source' => ['confidence' => 'medium'],
        'utm_campaign' => ['confidence' => 'low'],
        'utm_medium' => ['confidence' => 'low'],
    ];
    
    // Check for direct click IDs first (highest confidence)
    foreach (['fbclid', 'gclid', 'msclkid', 'ttclid', 'twclid', 'li_fat_id', 'igshid', 'ref_share'] as $param) {
        if (isset($params[$param]) && !empty($params[$param])) {
            return $source_mappings[$param];
        }
    }
    
    // Check UTM source
    if (isset($params['utm_source']) && !empty($params['utm_source'])) {
        return [
            'source' => $params['utm_source'],
            'confidence' => 'medium'
        ];
    }
    
    // Check generic ref/source parameters
    if (isset($params['ref']) && !empty($params['ref'])) {
        return [
            'source' => $params['ref'],
            'confidence' => 'medium'
        ];
    }
    
    if (isset($params['source']) && !empty($params['source'])) {
        return [
            'source' => $params['source'],
            'confidence' => 'medium'
        ];
    }
    
    // Check utm_campaign as fallback
    if (isset($params['utm_campaign']) && !empty($params['utm_campaign'])) {
        return [
            'source' => $params['utm_campaign'],
            'confidence' => 'low'
        ];
    }
    
    // No inference possible
    return [
        'source' => null,
        'confidence' => 'none'
    ];
}

// ═══════════════════════════════════════════════════════════
// GEOIP FUNCTIONS
// ═══════════════════════════════════════════════════════════

/**
 * Get geolocation data from IP address
 * 
 * @param string $ip IP address
 * @return array Location data (country_code, country_name, city)
 */
function get_geolocation($ip) {
    // Check cache first
    $cache_key = 'geo_' . md5($ip);
    $cached = get_cache($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    
    // Default values
    $location = [
        'country_code' => null,
        'country_name' => null,
        'city' => null
    ];
    
    if (!GEOIP_ENABLED) {
        return $location;
    }
    
    // Skip local/private IPs
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return $location;
    }
    
    try {
        if (GEOIP_METHOD === 'ip-api') {
            $location = geoip_ipapi($ip);
        } elseif (GEOIP_METHOD === 'maxmind') {
            $location = geoip_maxmind($ip);
        }
        
        // Cache the result
        set_cache($cache_key, $location, GEOIP_CACHE_TIME);
        
    } catch (Exception $e) {
        log_error('GeoIP lookup failed: ' . $e->getMessage());
    }
    
    return $location;
}

/**
 * GeoIP lookup using IP-API.com (free service)
 * Limit: 45 requests per minute
 * 
 * @param string $ip IP address
 * @return array Location data
 */
function geoip_ipapi($ip) {
    $url = "http://ip-api.com/json/{$ip}?fields=status,countryCode,country,city";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 2,
            'ignore_errors' => true
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception('IP-API request failed');
    }
    
    $data = json_decode($response, true);
    
    if (!$data || $data['status'] !== 'success') {
        throw new Exception('IP-API returned error');
    }
    
    return [
        'country_code' => $data['countryCode'] ?? null,
        'country_name' => $data['country'] ?? null,
        'city' => $data['city'] ?? null
    ];
}

/**
 * GeoIP lookup using MaxMind database
 * Requires: composer require geoip2/geoip2
 * 
 * @param string $ip IP address
 * @return array Location data
 */
function geoip_maxmind($ip) {
    if (!file_exists(MAXMIND_DATABASE_PATH)) {
        throw new Exception('MaxMind database not found');
    }
    
    // This requires the MaxMind library
    // For simplicity, we'll return null and provide instructions in README
    throw new Exception('MaxMind support requires additional setup. See README.md');
}

// ═══════════════════════════════════════════════════════════
// CACHING FUNCTIONS
// ═══════════════════════════════════════════════════════════

/**
 * Get cached value
 * 
 * @param string $key Cache key
 * @return mixed Cached value or false if not found/expired
 */
function get_cache($key) {
    $cache_file = CACHE_PATH . '/' . md5($key) . '.cache';
    
    if (!file_exists($cache_file)) {
        return false;
    }
    
    $data = @file_get_contents($cache_file);
    if ($data === false) {
        return false;
    }
    
    $cache = json_decode($data, true);
    
    if (!$cache || $cache['expires'] < time()) {
        @unlink($cache_file);
        return false;
    }
    
    return $cache['value'];
}

/**
 * Set cache value
 * 
 * @param string $key Cache key
 * @param mixed $value Value to cache
 * @param int $ttl Time to live in seconds
 */
function set_cache($key, $value, $ttl = 3600) {
    $cache_file = CACHE_PATH . '/' . md5($key) . '.cache';
    
    $cache = [
        'value' => $value,
        'expires' => time() + $ttl
    ];
    
    @file_put_contents($cache_file, json_encode($cache));
}

/**
 * Clear all cache
 */
function clear_cache() {
    $files = glob(CACHE_PATH . '/*.cache');
    foreach ($files as $file) {
        @unlink($file);
    }
}

// ═══════════════════════════════════════════════════════════
// UTILITY FUNCTIONS
// ═══════════════════════════════════════════════════════════

/**
 * Get client IP address
 * 
 * @return string IP address
 */
function get_client_ip() {
    $ip_keys = [
        'HTTP_CF_CONNECTING_IP',  // Cloudflare
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER)) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get user agent
 * 
 * @return string User agent
 */
function get_user_agent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Get referrer
 * 
 * @return string|null Referrer URL
 */
function get_referrer() {
    return $_SERVER['HTTP_REFERER'] ?? null;
}

/**
 * Generate slug from title
 * 
 * @param string $title Title
 * @return string Slug
 */
function generate_slug($title) {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

/**
 * Format number with abbreviation (1000 -> 1K)
 * 
 * @param int $number Number to format
 * @return string Formatted number
 */
function format_number($number) {
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return round($number / 1000, 1) . 'K';
    }
    return number_format($number);
}

/**
 * Format bytes to human readable
 * 
 * @param int $bytes Bytes
 * @return string Formatted size
 */
function format_bytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Time ago format
 * 
 * @param string $datetime Datetime string
 * @return string Relative time
 */
function time_ago($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}

/**
 * JSON response helper
 * 
 * @param bool $success Success status
 * @param mixed $data Response data
 * @param string $message Message
 */
function json_response($success, $data = null, $message = '') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ]);
    exit;
}

/**
 * Redirect helper
 * 
 * @param string $url URL to redirect to
 * @param int $code HTTP status code
 */
function redirect($url, $code = 302) {
    header("Location: $url", true, $code);
    exit;
}

/**
 * Get current page URL
 * 
 * @return string Current URL
 */
function current_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

// ═══════════════════════════════════════════════════════════
// PLACEMENT HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════

/**
 * Get placement count for a link
 * 
 * @param int $link_id Link ID
 * @param string $status Filter by status ('active', 'removed', 'broken', or '' for all)
 * @return int Placement count
 */
function get_placement_count($link_id, $status = 'active') {
    try {
        if (empty($status)) {
            $sql = "SELECT COUNT(*) as count FROM " . table('placements') . " WHERE link_id = ?";
            $result = db_fetch($sql, [$link_id]);
        } else {
            $sql = "SELECT COUNT(*) as count FROM " . table('placements') . " WHERE link_id = ? AND status = ?";
            $result = db_fetch($sql, [$link_id, $status]);
        }
        
        return (int)$result['count'];
    } catch (Exception $e) {
        log_error('Failed to get placement count: ' . $e->getMessage());
        return 0;
    }
}

