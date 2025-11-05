<?php
/**
 * AJAX Endpoint: Check Placement URL for Duplicates and Removal Warnings
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

$link_id = (int)($input['link_id'] ?? 0);
$url = trim($input['url'] ?? '');
$placement_id = (int)($input['placement_id'] ?? 0); // For edit mode

if ($link_id <= 0 || empty($url)) {
    json_response(false, null, 'Invalid request');
}

try {
    // Check for duplicate URL for THIS link
    if ($placement_id > 0) {
        // Editing - exclude current placement
        $sql = "SELECT COUNT(*) as count FROM " . table('placements') . " 
                WHERE link_id = ? AND url = ? AND id != ?";
        $result = db_fetch($sql, [$link_id, $url, $placement_id]);
    } else {
        // Creating - check all placements
        $sql = "SELECT COUNT(*) as count FROM " . table('placements') . " 
                WHERE link_id = ? AND url = ?";
        $result = db_fetch($sql, [$link_id, $url]);
    }
    
    if ($result['count'] > 0) {
        json_response(true, [
            'duplicate' => true,
            'removed_warning' => false
        ], 'Duplicate URL detected');
    }
    
    // Check if ANY other link has this URL marked as 'removed'
    $sql = "SELECT l.title, l.slug, p.title as placement_title 
            FROM " . table('placements') . " p
            JOIN " . table('links') . " l ON p.link_id = l.id
            WHERE p.url = ? AND p.status = 'removed' AND p.link_id != ?
            LIMIT 1";
    
    $removed_placement = db_fetch($sql, [$url, $link_id]);
    
    if ($removed_placement) {
        $warning_message = sprintf(
            'Another link (<strong>%s</strong>) was previously marked as <strong>REMOVED</strong> from this URL. ' .
            'The site owner may be actively removing casino/gambling links. Consider this risk before adding.',
            htmlspecialchars($removed_placement['title'])
        );
        
        json_response(true, [
            'duplicate' => false,
            'removed_warning' => true,
            'warning_message' => $warning_message
        ], 'Removal warning');
    }
    
    // No issues found
    json_response(true, [
        'duplicate' => false,
        'removed_warning' => false
    ], 'URL is available');
    
} catch (Exception $e) {
    log_error('Failed to check placement URL: ' . $e->getMessage());
    json_response(false, null, 'Failed to check URL. Please try again.');
}

