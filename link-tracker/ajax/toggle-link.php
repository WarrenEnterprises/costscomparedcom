<?php
/**
 * AJAX Endpoint: Toggle Link Status
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

$id = (int)($input['id'] ?? 0);
$is_active = (int)($input['is_active'] ?? 0);

if ($id <= 0) {
    json_response(false, null, 'Invalid link ID');
}

try {
    // Check if link exists
    $sql = "SELECT * FROM " . table('links') . " WHERE id = ?";
    $link = db_fetch($sql, [$id]);
    
    if (!$link) {
        json_response(false, null, 'Link not found');
    }
    
    // Update status
    $sql = "UPDATE " . table('links') . " SET is_active = ? WHERE id = ?";
    db_query($sql, [$is_active, $id]);
    
    $message = $is_active ? 'Link activated successfully!' : 'Link deactivated successfully!';
    json_response(true, null, $message);
    
} catch (Exception $e) {
    log_error('Failed to toggle link: ' . $e->getMessage());
    json_response(false, null, 'Failed to update link status. Please try again.');
}

