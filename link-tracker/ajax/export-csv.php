<?php
/**
 * AJAX Endpoint: Export Clicks to CSV
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
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
$link_id = isset($_GET['link']) ? (int)$_GET['link'] : 0;

// Build WHERE clause
$where = "WHERE c.clicked_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
$params = [];

if ($link_id > 0) {
    $where .= " AND c.link_id = ?";
    $params[] = $link_id;
}

try {
    // Get clicks data
    $sql = "SELECT 
            c.clicked_at,
            l.title as link_title,
            l.slug,
            c.ip_address,
            c.referrer,
            c.country_name,
            c.city,
            c.device_type,
            c.browser,
            c.os,
            c.is_unique
            FROM " . table('clicks') . " c
            JOIN " . table('links') . " l ON c.link_id = l.id
            $where
            ORDER BY c.clicked_at DESC";
    
    $clicks = db_fetch_all($sql, $params);
    
    // Generate filename
    $filename = 'link-tracker-export-' . date('Y-m-d-His') . '.csv';
    
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
        'Date & Time',
        'Link Title',
        'Slug',
        'IP Address',
        'Referrer',
        'Country',
        'City',
        'Device',
        'Browser',
        'OS',
        'Type'
    ]);
    
    // Add data rows
    foreach ($clicks as $click) {
        fputcsv($output, [
            $click['clicked_at'],
            $click['link_title'],
            $click['slug'],
            $click['ip_address'],
            $click['referrer'] ?? '',
            $click['country_name'] ?? '',
            $click['city'] ?? '',
            $click['device_type'] ?? '',
            $click['browser'] ?? '',
            $click['os'] ?? '',
            $click['is_unique'] ? 'Unique' : 'Return'
        ]);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    log_error('CSV export failed: ' . $e->getMessage());
    die('Export failed. Please try again.');
}

