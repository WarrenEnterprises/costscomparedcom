<?php
/**
 * AJAX Endpoint: Edit Link
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
$title = sanitize_input($input['title'] ?? '');
$slug = sanitize_input($input['slug'] ?? '');
$destination_url = trim($input['destination_url'] ?? '');
$cloaking_enabled = (int)($input['cloaking_enabled'] ?? 0);
$cloaking_url = trim($input['cloaking_url'] ?? '');

// Validation
if ($id <= 0) {
    json_response(false, null, 'Invalid link ID');
}

if (empty($title)) {
    json_response(false, null, 'Title is required');
}

if (empty($slug)) {
    json_response(false, null, 'Slug is required');
}

if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
    json_response(false, null, 'Slug can only contain lowercase letters, numbers, and hyphens');
}

if (empty($destination_url)) {
    json_response(false, null, 'Destination URL is required');
}

if (!validate_url($destination_url)) {
    json_response(false, null, 'Invalid destination URL');
}

if ($cloaking_enabled && !empty($cloaking_url) && !validate_url($cloaking_url)) {
    json_response(false, null, 'Invalid cloaking URL');
}

try {
    // Check if link exists
    $sql = "SELECT * FROM " . table('links') . " WHERE id = ?";
    $link = db_fetch($sql, [$id]);
    
    if (!$link) {
        json_response(false, null, 'Link not found');
    }
    
    // Check if slug already exists (excluding current link)
    $sql = "SELECT COUNT(*) as count FROM " . table('links') . " WHERE slug = ? AND id != ?";
    $result = db_fetch($sql, [$slug, $id]);
    
    if ($result['count'] > 0) {
        json_response(false, null, 'Slug already exists. Please choose a different one.');
    }
    
    // Update link
    $sql = "UPDATE " . table('links') . " 
            SET title = ?, slug = ?, destination_url = ?, 
                cloaking_enabled = ?, cloaking_url = ?
            WHERE id = ?";
    
    db_query($sql, [
        $title,
        $slug,
        $destination_url,
        $cloaking_enabled,
        $cloaking_enabled ? $cloaking_url : null,
        $id
    ]);
    
    json_response(true, null, 'Link updated successfully!');
    
} catch (Exception $e) {
    log_error('Failed to update link: ' . $e->getMessage());
    json_response(false, null, 'Failed to update link. Please try again.');
}

