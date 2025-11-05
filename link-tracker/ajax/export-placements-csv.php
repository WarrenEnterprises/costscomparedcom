<?php
/**
 * AJAX Endpoint: Export Placements to CSV
 */

define('LT_INIT', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../auth.php';

start_session();
$user = validate_session();

if (!$user) {
    die('Unauthorized');
}

// Get filters
$link_id = isset($_GET['link_id']) ? (int)$_GET['link_id'] : 0;
$platform = isset($_GET['platform']) ? sanitize_input($_GET['platform']) : '';
$status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$sort = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'rating_desc';

if ($link_id <= 0) {
    die('Invalid link ID');
}

// Build WHERE clause
$where = "WHERE p.link_id = ?";
$params = [$link_id];

if (!empty($platform)) {
    if ($platform === 'other') {
        $where .= " AND (p.platform = 'Other' OR p.platform IS NULL)";
    } else {
        $where .= " AND p.platform = ?";
        $params[] = $platform;
    }
}

if (!empty($status)) {
    $where .= " AND p.status = ?";
    $params[] = $status;
}

// Determine ORDER BY
$order_by = "p.star_rating DESC, p.created_at DESC";
switch ($sort) {
    case 'rating_asc':
        $order_by = "p.star_rating ASC, p.title ASC";
        break;
    case 'alpha_asc':
        $order_by = "p.title ASC";
        break;
    case 'alpha_desc':
        $order_by = "p.title DESC";
        break;
    case 'date_newest':
        $order_by = "p.date_added DESC";
        break;
    case 'date_oldest':
        $order_by = "p.date_added ASC";
        break;
}

try {
    // Get link info
    $sql = "SELECT title, slug FROM " . table('links') . " WHERE id = ?";
    $link = db_fetch($sql, [$link_id]);
    
    if (!$link) {
        die('Link not found');
    }
    
    // Get placements
    $sql = "SELECT p.*, l.title as link_title, l.slug as link_slug
            FROM " . table('placements') . " p
            JOIN " . table('links') . " l ON p.link_id = l.id
            $where
            ORDER BY $order_by";
    
    $placements = db_fetch_all($sql, $params);
    
    // Generate filename
    $filename = 'placements-' . $link['slug'] . '-' . date('Y-m-d') . '.csv';
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add CSV headers
    fputcsv($output, [
        'Link Title',
        'Link Slug',
        'Placement Title',
        'Placement URL',
        'Star Rating',
        'Platform',
        'Custom Platform',
        'Status',
        'Notes',
        'Date Added',
        'Created At',
        'Updated At'
    ]);
    
    // Add data rows
    foreach ($placements as $placement) {
        fputcsv($output, [
            $placement['link_title'],
            $placement['link_slug'],
            $placement['title'],
            $placement['url'],
            $placement['star_rating'],
            $placement['platform'] ?? '',
            $placement['platform_custom'] ?? '',
            $placement['status'],
            $placement['notes'] ?? '',
            $placement['date_added'],
            $placement['created_at'],
            $placement['updated_at']
        ]);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    log_error('Placement CSV export failed: ' . $e->getMessage());
    die('Export failed. Please try again.');
}

