<?php
/**
 * Authentication and Session Management
 */

// Prevent direct access
if (!defined('LT_INIT')) {
    die('Direct access not permitted');
}

// ═══════════════════════════════════════════════════════════
// SESSION FUNCTIONS
// ═══════════════════════════════════════════════════════════

/**
 * Start secure session
 */
function start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Lax');
        
        if (!DEBUG_MODE && isset($_SERVER['HTTPS'])) {
            ini_set('session.cookie_secure', 1);
        }
        
        session_start();
    }
}

/**
 * Create user session
 * 
 * @param int $user_id User ID
 * @param bool $remember Remember me option
 * @return bool Success status
 */
function create_session($user_id, $remember = false) {
    try {
        $token = generate_token(32);
        $ip = get_client_ip();
        $ua = get_user_agent();
        $lifetime = $remember ? SESSION_LIFETIME_REMEMBER : SESSION_LIFETIME;
        $expires = date('Y-m-d H:i:s', time() + $lifetime);
        
        $sql = "INSERT INTO " . table('sessions') . " 
                (user_id, session_token, ip_address, user_agent, expires_at) 
                VALUES (?, ?, ?, ?, ?)";
        
        db_query($sql, [$user_id, $token, $ip, $ua, $expires]);
        
        // Store token in cookie
        $cookie_lifetime = $remember ? time() + SESSION_LIFETIME_REMEMBER : 0;
        setcookie('lt_session', $token, $cookie_lifetime, '/', '', !DEBUG_MODE, true);
        
        // Update last login
        $sql = "UPDATE " . table('users') . " SET last_login = NOW() WHERE id = ?";
        db_query($sql, [$user_id]);
        
        return true;
        
    } catch (Exception $e) {
        log_error('Failed to create session: ' . $e->getMessage());
        return false;
    }
}

/**
 * Validate session
 * 
 * @return array|false User data if valid, false otherwise
 */
function validate_session() {
    if (!isset($_COOKIE['lt_session'])) {
        return false;
    }
    
    $token = $_COOKIE['lt_session'];
    $ip = get_client_ip();
    $ua = get_user_agent();
    
    try {
        $sql = "SELECT s.*, u.username 
                FROM " . table('sessions') . " s
                JOIN " . table('users') . " u ON s.user_id = u.id
                WHERE s.session_token = ? 
                AND s.ip_address = ? 
                AND s.user_agent = ?
                AND s.expires_at > NOW()";
        
        $session = db_fetch($sql, [$token, $ip, $ua]);
        
        if (!$session) {
            return false;
        }
        
        return [
            'user_id' => $session['user_id'],
            'username' => $session['username']
        ];
        
    } catch (Exception $e) {
        log_error('Session validation error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Destroy session
 * 
 * @return bool Success status
 */
function destroy_session() {
    if (isset($_COOKIE['lt_session'])) {
        $token = $_COOKIE['lt_session'];
        
        try {
            $sql = "DELETE FROM " . table('sessions') . " WHERE session_token = ?";
            db_query($sql, [$token]);
        } catch (Exception $e) {
            log_error('Failed to destroy session: ' . $e->getMessage());
        }
        
        setcookie('lt_session', '', time() - 3600, '/', '', !DEBUG_MODE, true);
    }
    
    return true;
}

/**
 * Clean expired sessions
 */
function clean_expired_sessions() {
    try {
        $sql = "DELETE FROM " . table('sessions') . " WHERE expires_at < NOW()";
        db_query($sql);
    } catch (Exception $e) {
        log_error('Failed to clean expired sessions: ' . $e->getMessage());
    }
}

/**
 * Require authentication
 * Redirects to login if not authenticated
 */
function require_auth() {
    $user = validate_session();
    
    if (!$user) {
        redirect(TRACKER_PATH . '/link-tracking.php');
    }
    
    return $user;
}

// ═══════════════════════════════════════════════════════════
// LOGIN FUNCTIONS
// ═══════════════════════════════════════════════════════════

/**
 * Attempt login
 * 
 * @param string $username Username
 * @param string $password Password
 * @param bool $remember Remember me
 * @return array Result with success status and message
 */
function attempt_login($username, $password, $remember = false) {
    $ip = get_client_ip();
    
    // Check if IP is blocked
    $block_info = check_login_block($ip);
    if ($block_info['blocked']) {
        return [
            'success' => false,
            'message' => 'Too many failed attempts. Try again in ' . format_block_time($block_info['remaining']) . '.',
            'blocked' => true,
            'remaining' => $block_info['remaining']
        ];
    }
    
    // Check if CAPTCHA is required
    $attempts = get_recent_login_attempts($ip);
    $captcha_required = $attempts >= MAX_LOGIN_ATTEMPTS_BEFORE_CAPTCHA;
    
    try {
        $sql = "SELECT * FROM " . table('users') . " WHERE username = ?";
        $user = db_fetch($sql, [$username]);
        
        if (!$user || !verify_password($password, $user['password_hash'])) {
            // Record failed attempt
            record_login_attempt($ip);
            
            $remaining = MAX_LOGIN_ATTEMPTS_BEFORE_BLOCK - ($attempts + 1);
            
            return [
                'success' => false,
                'message' => 'Invalid username or password.',
                'captcha_required' => ($attempts + 1) >= MAX_LOGIN_ATTEMPTS_BEFORE_CAPTCHA,
                'attempts_remaining' => max(0, $remaining)
            ];
        }
        
        // Successful login - clear failed attempts
        clear_login_attempts($ip);
        
        // Create session
        if (create_session($user['id'], $remember)) {
            return [
                'success' => true,
                'message' => 'Login successful.',
                'redirect' => TRACKER_PATH . '/dashboard.php'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to create session.'
            ];
        }
        
    } catch (Exception $e) {
        log_error('Login error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An error occurred. Please try again.'
        ];
    }
}

// ═══════════════════════════════════════════════════════════
// BRUTE FORCE PROTECTION
// ═══════════════════════════════════════════════════════════

/**
 * Record failed login attempt
 * 
 * @param string $ip IP address
 */
function record_login_attempt($ip) {
    try {
        $ua = get_user_agent();
        $sql = "INSERT INTO " . table('login_attempts') . " (ip_address, user_agent) VALUES (?, ?)";
        db_query($sql, [$ip, $ua]);
    } catch (Exception $e) {
        log_error('Failed to record login attempt: ' . $e->getMessage());
    }
}

/**
 * Get recent login attempts count
 * 
 * @param string $ip IP address
 * @param int $minutes Time window in minutes
 * @return int Number of attempts
 */
function get_recent_login_attempts($ip, $minutes = 5) {
    try {
        $sql = "SELECT COUNT(*) as count 
                FROM " . table('login_attempts') . " 
                WHERE ip_address = ? 
                AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
        
        $result = db_fetch($sql, [$ip, $minutes]);
        return (int)$result['count'];
        
    } catch (Exception $e) {
        log_error('Failed to get login attempts: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Check if IP is blocked
 * 
 * @param string $ip IP address
 * @return array Block info with 'blocked' status and 'remaining' seconds
 */
function check_login_block($ip) {
    // Check for extended block (10+ attempts in last hour)
    $attempts_hour = get_recent_login_attempts($ip, 60);
    
    if ($attempts_hour >= MAX_LOGIN_ATTEMPTS_EXTENDED_BLOCK) {
        $sql = "SELECT attempted_at 
                FROM " . table('login_attempts') . " 
                WHERE ip_address = ? 
                ORDER BY attempted_at DESC 
                LIMIT 1";
        
        $last_attempt = db_fetch($sql, [$ip]);
        
        if ($last_attempt) {
            $time_since = time() - strtotime($last_attempt['attempted_at']);
            $remaining = LOGIN_EXTENDED_BLOCK_DURATION - $time_since;
            
            if ($remaining > 0) {
                return ['blocked' => true, 'remaining' => $remaining];
            }
        }
    }
    
    // Check for short block (5+ attempts in last 5 minutes)
    $attempts_5min = get_recent_login_attempts($ip, 5);
    
    if ($attempts_5min >= MAX_LOGIN_ATTEMPTS_BEFORE_BLOCK) {
        $sql = "SELECT attempted_at 
                FROM " . table('login_attempts') . " 
                WHERE ip_address = ? 
                ORDER BY attempted_at DESC 
                LIMIT 1";
        
        $last_attempt = db_fetch($sql, [$ip]);
        
        if ($last_attempt) {
            $time_since = time() - strtotime($last_attempt['attempted_at']);
            $remaining = LOGIN_BLOCK_DURATION - $time_since;
            
            if ($remaining > 0) {
                return ['blocked' => true, 'remaining' => $remaining];
            }
        }
    }
    
    return ['blocked' => false, 'remaining' => 0];
}

/**
 * Clear login attempts for IP
 * 
 * @param string $ip IP address
 */
function clear_login_attempts($ip) {
    try {
        $sql = "DELETE FROM " . table('login_attempts') . " WHERE ip_address = ?";
        db_query($sql, [$ip]);
    } catch (Exception $e) {
        log_error('Failed to clear login attempts: ' . $e->getMessage());
    }
}

/**
 * Clean old login attempts (older than 24 hours)
 */
function clean_old_login_attempts() {
    try {
        $sql = "DELETE FROM " . table('login_attempts') . " 
                WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        db_query($sql);
    } catch (Exception $e) {
        log_error('Failed to clean old login attempts: ' . $e->getMessage());
    }
}

/**
 * Format block time remaining
 * 
 * @param int $seconds Seconds remaining
 * @return string Formatted time
 */
function format_block_time($seconds) {
    if ($seconds >= 3600) {
        return ceil($seconds / 3600) . ' hour(s)';
    } elseif ($seconds >= 60) {
        return ceil($seconds / 60) . ' minute(s)';
    } else {
        return $seconds . ' second(s)';
    }
}

// ═══════════════════════════════════════════════════════════
// USER MANAGEMENT
// ═══════════════════════════════════════════════════════════

/**
 * Create new user
 * 
 * @param string $username Username
 * @param string $password Password
 * @return bool Success status
 */
function create_user($username, $password) {
    try {
        $password_hash = hash_password($password);
        
        $sql = "INSERT INTO " . table('users') . " (username, password_hash) VALUES (?, ?)";
        db_query($sql, [$username, $password_hash]);
        
        return true;
        
    } catch (Exception $e) {
        log_error('Failed to create user: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update user password
 * 
 * @param int $user_id User ID
 * @param string $new_password New password
 * @return bool Success status
 */
function update_password($user_id, $new_password) {
    try {
        $password_hash = hash_password($new_password);
        
        $sql = "UPDATE " . table('users') . " SET password_hash = ? WHERE id = ?";
        db_query($sql, [$password_hash, $user_id]);
        
        return true;
        
    } catch (Exception $e) {
        log_error('Failed to update password: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if any users exist
 * 
 * @return bool True if users exist
 */
function users_exist() {
    try {
        $sql = "SELECT COUNT(*) as count FROM " . table('users');
        $result = db_fetch($sql);
        return $result['count'] > 0;
    } catch (Exception $e) {
        return false;
    }
}

