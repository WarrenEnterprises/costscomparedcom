<?php
/**
 * AJAX Endpoint: Edit Placement
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

$placement_id = (int)($input['placement_id'] ?? 0);
$link_id = (int)($input['link_id'] ?? 0);
$title = sanitize_input($input['title'] ?? '');
$url = trim($input['url'] ?? '');
$star_rating = (int)($input['star_rating'] ?? 3);
$platform = sanitize_input($input['platform'] ?? '');
$platform_custom = sanitize_input($input['platform_custom'] ?? '');
$status = sanitize_input($input['status'] ?? 'active');
$notes = sanitize_input($input['notes'] ?? '');

// Validation
if ($placement_id <= 0) {
    json_response(false, null, 'Invalid placement ID');
}

if ($link_id <= 0) {
    json_response(false, null, 'Invalid link ID');
}

if (empty($title)) {
    json_response(false, null, 'Title is required');
}

if (strlen($title) > 500) {
    json_response(false, null, 'Title must be less than 500 characters');
}

if (empty($url)) {
    json_response(false, null, 'URL is required');
}

if (!validate_url($url)) {
    json_response(false, null, 'Invalid URL format');
}

if ($star_rating < 1 || $star_rating > 5) {
    json_response(false, null, 'Star rating must be between 1 and 5');
}

if (!in_array($status, ['active', 'removed', 'broken'])) {
    json_response(false, null, 'Invalid status');
}

if ($platform === 'Other' && empty($platform_custom)) {
    json_response(false, null, 'Please specify the custom platform name');
}

try {
    // Verify placement exists and belongs to this link
    $sql = "SELECT * FROM " . table('placements') . " WHERE id = ? AND link_id = ?";
    $placement = db_fetch($sql, [$placement_id, $link_id]);
    
    if (!$placement) {
        json_response(false, null, 'Placement not found');
    }
    
    // Check for duplicate URL (excluding current placement)
    $sql = "SELECT COUNT(*) as count FROM " . table('placements') . " 
            WHERE link_id = ? AND url = ? AND id != ?";
    $result = db_fetch($sql, [$link_id, $url, $placement_id]);
    
    if ($result['count'] > 0) {
        json_response(false, null, 'This URL is already tracked for this link');
    }
    
    // Update placement
    $sql = "UPDATE " . table('placements') . " 
            SET title = ?, url = ?, star_rating = ?, platform = ?, 
                platform_custom = ?, status = ?, notes = ?
            WHERE id = ? AND link_id = ?";
    
    db_query($sql, [
        $title,
        $url,
        $star_rating,
        $platform ?: null,
        $platform === 'Other' ? $platform_custom : null,
        $status,
        $notes ?: null,
        $placement_id,
        $link_id
    ]);
    
    json_response(true, null, 'Placement updated successfully!');
    
} catch (Exception $e) {
    log_error('Failed to update placement: ' . $e->getMessage());
    json_response(false, null, 'Failed to update placement. Please try again.');
}

