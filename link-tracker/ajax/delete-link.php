<?php
/**
 * AJAX Endpoint: Delete Link
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
    
    // Delete link (clicks will be deleted automatically due to foreign key constraint)
    $sql = "DELETE FROM " . table('links') . " WHERE id = ?";
    db_query($sql, [$id]);
    
    json_response(true, null, 'Link deleted successfully!');
    
} catch (Exception $e) {
    log_error('Failed to delete link: ' . $e->getMessage());
    json_response(false, null, 'Failed to delete link. Please try again.');
}

