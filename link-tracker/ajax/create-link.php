<?php
/**
 * AJAX Endpoint: Create Link
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

$title = sanitize_input($input['title'] ?? '');
$slug = sanitize_input($input['slug'] ?? '');
$destination_url = trim($input['destination_url'] ?? '');
$cloaking_enabled = (int)($input['cloaking_enabled'] ?? 0);
$cloaking_url = trim($input['cloaking_url'] ?? '');

// Validation
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
    // Check if slug already exists
    $sql = "SELECT COUNT(*) as count FROM " . table('links') . " WHERE slug = ?";
    $result = db_fetch($sql, [$slug]);
    
    if ($result['count'] > 0) {
        json_response(false, null, 'Slug already exists. Please choose a different one.');
    }
    
    // Insert link
    $sql = "INSERT INTO " . table('links') . " 
            (title, slug, destination_url, cloaking_enabled, cloaking_url) 
            VALUES (?, ?, ?, ?, ?)";
    
    $link_id = db_insert($sql, [
        $title,
        $slug,
        $destination_url,
        $cloaking_enabled,
        $cloaking_enabled ? $cloaking_url : null
    ]);
    
    json_response(true, ['id' => $link_id], 'Link created successfully!');
    
} catch (Exception $e) {
    log_error('Failed to create link: ' . $e->getMessage());
    json_response(false, null, 'Failed to create link. Please try again.');
}

