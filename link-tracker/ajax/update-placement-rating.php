<?php
/**
 * AJAX Endpoint: Update Placement Star Rating
 * For quick inline rating updates
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
$rating = (int)($input['rating'] ?? 0);

if ($placement_id <= 0) {
    json_response(false, null, 'Invalid placement ID');
}

if ($rating < 1 || $rating > 5) {
    json_response(false, null, 'Rating must be between 1 and 5');
}

try {
    // Verify placement exists
    $sql = "SELECT id FROM " . table('placements') . " WHERE id = ?";
    $placement = db_fetch($sql, [$placement_id]);
    
    if (!$placement) {
        json_response(false, null, 'Placement not found');
    }
    
    // Update rating
    $sql = "UPDATE " . table('placements') . " SET star_rating = ? WHERE id = ?";
    db_query($sql, [$rating, $placement_id]);
    
    json_response(true, ['rating' => $rating], 'Rating updated!');
    
} catch (Exception $e) {
    log_error('Failed to update rating: ' . $e->getMessage());
    json_response(false, null, 'Failed to update rating. Please try again.');
}

