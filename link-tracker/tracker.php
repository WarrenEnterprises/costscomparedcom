<?php
/**
 * Link Tracker - Redirect & Click Tracking Handler
 * 
 * This file handles all redirect requests from /best/{slug}
 * It captures tracking data and performs the 302 redirect
 */

define('LT_INIT', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Clean up old sessions and login attempts periodically
if (rand(1, 100) === 1) {
    clean_expired_sessions();
    clean_old_login_attempts();
}

// ═══════════════════════════════════════════════════════════
// GET SLUG FROM REQUEST
// ═══════════════════════════════════════════════════════════

$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    http_response_code(404);
    show_404_page();
    exit;
}

// Sanitize slug
$slug = preg_replace('/[^a-zA-Z0-9-]/', '', $slug);

// ═══════════════════════════════════════════════════════════
// FETCH LINK FROM DATABASE
// ═══════════════════════════════════════════════════════════

try {
    $sql = "SELECT * FROM " . table('links') . " WHERE slug = ? AND is_active = 1";
    $link = db_fetch($sql, [$slug]);
    
    if (!$link) {
        http_response_code(404);
        show_404_page();
        exit;
    }
    
} catch (Exception $e) {
    log_error('Failed to fetch link: ' . $e->getMessage());
    http_response_code(500);
    echo 'An error occurred. Please try again later.';
    exit;
}

// ═══════════════════════════════════════════════════════════
// DETERMINE REDIRECT URL (CLOAKING CHECK)
// ═══════════════════════════════════════════════════════════

$redirect_url = $link['destination_url'];
$user_agent = get_user_agent();

// Check if cloaking is enabled and visitor is a bot
if ($link['cloaking_enabled'] && !empty($link['cloaking_url']) && is_bot($user_agent)) {
    $redirect_url = $link['cloaking_url'];
}

// ═══════════════════════════════════════════════════════════
// GATHER TRACKING DATA
// ═══════════════════════════════════════════════════════════

$ip = get_client_ip();
$referrer = get_referrer();
$query_params = !empty($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : null;
$request_uri = $_SERVER['REQUEST_URI'] ?? null;
$accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null;

// Detect if bot (check both user agent and IP)
$is_bot = is_bot($user_agent, $ip) ? 1 : 0;

// Infer referrer source if empty
$referrer_info = infer_referrer_source($referrer, $query_params);
$inferred_source = $referrer_info['source'];
$source_confidence = $referrer_info['confidence'];

// Parse user agent (enhanced with versions)
$ua_info = parse_user_agent($user_agent);
$device_type = $ua_info['device'];
$browser = $ua_info['browser'];
$browser_version = $ua_info['browser_version'];
$os = $ua_info['os'];
$os_version = $ua_info['os_version'];

// Get geolocation data
$geo = get_geolocation($ip);
$country_code = $geo['country_code'];
$country_name = $geo['country_name'];
$city = $geo['city'];

// Generate fingerprint
$fingerprint = generate_fingerprint($ip, $user_agent);

// Check if this is a unique visitor for this link
$is_unique = 0;

try {
    $sql = "SELECT COUNT(*) as count FROM " . table('clicks') . " 
            WHERE link_id = ? AND fingerprint = ?";
    $result = db_fetch($sql, [$link['id'], $fingerprint]);
    
    if ($result['count'] == 0) {
        $is_unique = 1;
    }
    
} catch (Exception $e) {
    log_error('Failed to check unique visitor: ' . $e->getMessage());
}

// ═══════════════════════════════════════════════════════════
// RECORD CLICK IN DATABASE
// ═══════════════════════════════════════════════════════════

try {
    $sql = "INSERT INTO " . table('clicks') . " 
            (link_id, ip_address, user_agent, referrer, inferred_source, source_confidence,
             query_params, request_uri, accept_language,
             country_code, country_name, city, 
             device_type, browser, browser_version, os, os_version,
             fingerprint, is_unique, is_bot) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    db_query($sql, [
        $link['id'],
        $ip,
        $user_agent,
        $referrer,
        $inferred_source,
        $source_confidence,
        $query_params,
        $request_uri,
        $accept_language,
        $country_code,
        $country_name,
        $city,
        $device_type,
        $browser,
        $browser_version,
        $os,
        $os_version,
        $fingerprint,
        $is_unique,
        $is_bot
    ]);
    
} catch (Exception $e) {
    log_error('Failed to record click: ' . $e->getMessage());
    // Continue with redirect even if tracking fails
}

// ═══════════════════════════════════════════════════════════
// PERFORM 302 REDIRECT
// ═══════════════════════════════════════════════════════════

header("Location: $redirect_url", true, 302);
exit;

// ═══════════════════════════════════════════════════════════
// 404 PAGE
// ═══════════════════════════════════════════════════════════

function show_404_page() {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>404 - Page Not Found</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50 flex items-center justify-center min-h-screen">
        <div class="text-center px-4">
            <div class="mb-8">
                <svg class="mx-auto h-32 w-32 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h1 class="text-6xl font-bold text-gray-900 mb-4">404</h1>
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Page Not Found</h2>
            <p class="text-gray-600 mb-8">The link you're looking for doesn't exist or has been deactivated.</p>
            <a href="/" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                Go Back Home
            </a>
        </div>
    </body>
    </html>
    <?php
}

