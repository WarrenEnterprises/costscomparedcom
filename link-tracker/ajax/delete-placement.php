<?php
/**
 * AJAX Endpoint: Delete Placement
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

$placement_id = (int)($input['id'] ?? 0);

if ($placement_id <= 0) {
    json_response(false, null, 'Invalid placement ID');
}

try {
    // Check if placement exists
    $sql = "SELECT * FROM " . table('placements') . " WHERE id = ?";
    $placement = db_fetch($sql, [$placement_id]);
    
    if (!$placement) {
        json_response(false, null, 'Placement not found');
    }
    
    // Delete placement
    $sql = "DELETE FROM " . table('placements') . " WHERE id = ?";
    db_query($sql, [$placement_id]);
    
    json_response(true, null, 'Placement deleted successfully!');
    
} catch (Exception $e) {
    log_error('Failed to delete placement: ' . $e->getMessage());
    json_response(false, null, 'Failed to delete placement. Please try again.');
}

