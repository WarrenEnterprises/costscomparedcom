<?php
/**
 * AJAX Endpoint: Clear All Click Data
 */

define('LT_INIT', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../auth.php';

start_session();
$user = validate_session();

if (!$user) {
    json_response(false, null, 'Unauthorized');
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$password = $input['password'] ?? '';

if (empty($password)) {
    json_response(false, null, 'Password is required');
}

try {
    // Verify password
    $sql = "SELECT * FROM " . table('users') . " WHERE id = ?";
    $user_data = db_fetch($sql, [$user['user_id']]);
    
    if (!verify_password($password, $user_data['password_hash'])) {
        json_response(false, null, 'Incorrect password');
    }
    
    // Get counts before deletion
    $sql = "SELECT COUNT(*) as clicks FROM " . table('clicks');
    $result = db_fetch($sql);
    $clicks_before = $result['clicks'];
    
    $sql = "SELECT COUNT(*) as links FROM " . table('links');
    $result = db_fetch($sql);
    $links_before = $result['links'];
    
    // Log the reset
    $sql = "INSERT INTO " . table('system_resets') . " 
            (clicks_before_reset, links_before_reset) 
            VALUES (?, ?)";
    db_query($sql, [$clicks_before, $links_before]);
    
    // Clear all click data
    $sql = "TRUNCATE TABLE " . table('clicks');
    db_query($sql);
    
    // Clear cache
    clear_cache();
    
    json_response(true, [
        'clicks_deleted' => $clicks_before
    ], "Successfully deleted {$clicks_before} click records!");
    
} catch (Exception $e) {
    log_error('Failed to clear data: ' . $e->getMessage());
    json_response(false, null, 'Failed to clear data. Please try again.');
}

