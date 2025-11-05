<?php
/**
 * Analytics Page
 * Detailed statistics with charts and filters
 */

define('LT_INIT', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

start_session();
$user = require_auth();

// Get filters
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
$link_id = isset($_GET['link']) ? (int)$_GET['link'] : 0;
$bot_filter = isset($_GET['bot']) ? $_GET['bot'] : 'all'; // 'all', 'human', 'bot'
$filter_referrer = isset($_GET['filter_referrer']) ? sanitize_input($_GET['filter_referrer']) : '';
$filter_param = isset($_GET['filter_param']) ? sanitize_input($_GET['filter_param']) : '';
$group_by_ip = isset($_GET['group_by_ip']) ? (bool)$_GET['group_by_ip'] : true; // Grouped view is default
$ip_range = isset($_GET['ip_range']) ? sanitize_input($_GET['ip_range']) : '';

// Sorting for detailed clicks table (individual view only)
$sort_column = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'date';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Whitelist allowed sort columns (prevent SQL injection)
$allowed_sorts = [
    'date' => 'c.clicked_at',
    'referrer' => 'c.referrer',
    'link' => 'l.title',
    'country' => 'c.country_name',
    'ip' => 'c.ip_address',
    'device' => 'c.device_type',
    'browser' => 'c.browser',
    'os' => 'c.os',
    'type' => 'c.is_bot'
];

// Validate sort column
if (!isset($allowed_sorts[$sort_column])) {
    $sort_column = 'date';
}

$sort_column_sql = $allowed_sorts[$sort_column];

// Validate days
$allowed_days = [1, 2, 5, 7, 14, 30, 90];
if (!in_array($days, $allowed_days)) {
    $days = 30;
}

// Build WHERE clause
$where = "WHERE c.clicked_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
$params = [];

if ($link_id > 0) {
    $where .= " AND c.link_id = ?";
    $params[] = $link_id;
}

// Bot filter
if ($bot_filter === 'human') {
    $where .= " AND c.is_bot = 0";
} elseif ($bot_filter === 'bot') {
    $where .= " AND c.is_bot = 1";
}
// 'all' doesn't add filter

// Referrer filter
if (!empty($filter_referrer)) {
    $where .= " AND (c.referrer LIKE ? OR c.inferred_source = ?)";
    $params[] = '%' . $filter_referrer . '%';
    $params[] = $filter_referrer;
}

// Parameter filter
if (!empty($filter_param)) {
    if ($filter_param === 'direct') {
        $where .= " AND (c.query_params IS NULL OR c.query_params = '')";
    } else {
        $where .= " AND c.query_params LIKE ?";
        $params[] = '%' . $filter_param . '%';
    }
}

// Filter by IP range if specified (e.g., "205.169.39" or "2001:4454:599:ad00")
if (!empty($ip_range)) {
    if (strpos($ip_range, ':') !== false) {
        // IPv6 - add colon to match pattern
        $where .= " AND c.ip_address LIKE ?";
        $params[] = $ip_range . ':%';
    } else {
        // IPv4 - add dot to match pattern
        $where .= " AND c.ip_address LIKE ?";
        $params[] = $ip_range . '.%';
    }
}

try {
    // Get all links for filter
    $sql = "SELECT id, title, slug FROM " . table('links') . " ORDER BY title ASC";
    $all_links = db_fetch_all($sql);
    
    // Overview stats (with bot/human split)
    $sql = "SELECT 
            COUNT(*) as total_clicks,
            SUM(is_unique) as unique_clicks,
            SUM(is_bot) as bot_clicks,
            SUM(CASE WHEN is_bot = 0 THEN 1 ELSE 0 END) as human_clicks
            FROM " . table('clicks') . " c
            $where";
    $overview = db_fetch($sql, $params);
    $overview['human_clicks'] = (int)$overview['human_clicks'];
    $overview['bot_clicks'] = (int)$overview['bot_clicks'];
    
    // Average clicks per day
    $avg_clicks_per_day = $overview['total_clicks'] > 0 ? round($overview['total_clicks'] / $days, 1) : 0;
    
    // Most active day
    $sql = "SELECT DATE(clicked_at) as date, COUNT(*) as count
            FROM " . table('clicks') . " c
            $where
            GROUP BY DATE(clicked_at)
            ORDER BY count DESC
            LIMIT 1";
    $most_active_day = db_fetch($sql, $params);
    
    // Clicks over time (for line chart)
    $sql = "SELECT DATE(clicked_at) as date, COUNT(*) as count
            FROM " . table('clicks') . " c
            $where
            GROUP BY DATE(clicked_at)
            ORDER BY date ASC";
    $clicks_over_time = db_fetch_all($sql, $params);
    
    // Fill in missing dates
    $chart_dates = [];
    $chart_counts = [];
    $start_date = strtotime("-" . ($days - 1) . " days");
    
    for ($i = 0; $i < $days; $i++) {
        $date = date('Y-m-d', strtotime("+$i days", $start_date));
        $chart_dates[] = date('M j', strtotime($date));
        $chart_counts[$date] = 0;
    }
    
    foreach ($clicks_over_time as $row) {
        $chart_counts[$row['date']] = (int)$row['count'];
    }
    
    $chart_values = array_values($chart_counts);
    
    // Traffic by country
    $sql = "SELECT country_name, COUNT(*) as count
            FROM " . table('clicks') . " c
            $where AND country_name IS NOT NULL
            GROUP BY country_name
            ORDER BY count DESC
            LIMIT 10";
    $by_country = db_fetch_all($sql, $params);
    
    // Top referrers (combine real referrer and inferred sources)
    $sql = "SELECT 
            COALESCE(c.inferred_source, 
                     CASE 
                         WHEN c.referrer IS NOT NULL AND c.referrer != '' 
                         THEN SUBSTRING_INDEX(SUBSTRING_INDEX(c.referrer, '/', 3), '/', -1)
                         ELSE 'Direct Traffic'
                     END
            ) as referrer_source,
            COUNT(*) as count
            FROM " . table('clicks') . " c
            $where
            GROUP BY referrer_source
            ORDER BY count DESC
            LIMIT 10";
    $by_referrer = db_fetch_all($sql, $params);
    
    // Device types
    $sql = "SELECT device_type, COUNT(*) as count
            FROM " . table('clicks') . " c
            $where AND device_type IS NOT NULL
            GROUP BY device_type
            ORDER BY count DESC";
    $by_device = db_fetch_all($sql, $params);
    
    // Browsers
    $sql = "SELECT browser, COUNT(*) as count
            FROM " . table('clicks') . " c
            $where AND browser IS NOT NULL
            GROUP BY browser
            ORDER BY count DESC
            LIMIT 10";
    $by_browser = db_fetch_all($sql, $params);
    
    // Operating systems
    $sql = "SELECT os, COUNT(*) as count
            FROM " . table('clicks') . " c
            $where AND os IS NOT NULL
            GROUP BY os
            ORDER BY count DESC
            LIMIT 10";
    $by_os = db_fetch_all($sql, $params);
    
    // Recent clicks for detailed table (individual or grouped)
    if ($group_by_ip && empty($ip_range)) {
        // Grouped by IP range view - handle both IPv4 and IPv6
        $sql = "SELECT 
                CASE 
                    WHEN c.ip_address LIKE '%:%' THEN 
                        SUBSTRING_INDEX(c.ip_address, ':', 4)
                    ELSE 
                        SUBSTRING_INDEX(c.ip_address, '.', 3)
                END as ip_range,
                COUNT(*) as click_count,
                MIN(c.clicked_at) as first_click,
                MAX(c.clicked_at) as last_click,
                MAX(c.country_name) as country_name,
                MAX(c.country_code) as country_code,
                MAX(c.city) as city,
                SUM(c.is_bot) as bot_count,
                COUNT(*) - SUM(c.is_bot) as human_count,
                GROUP_CONCAT(DISTINCT l.slug SEPARATOR ', ') as links
                FROM " . table('clicks') . " c
                JOIN " . table('links') . " l ON c.link_id = l.id
                $where
                GROUP BY ip_range
                ORDER BY click_count DESC, last_click DESC
                LIMIT 100";
        $recent_clicks = db_fetch_all($sql, $params);
        $is_grouped_view = true;
    } else {
        // Individual clicks view
        $sql = "SELECT c.*, l.title, l.slug
                FROM " . table('clicks') . " c
                JOIN " . table('links') . " l ON c.link_id = l.id
                $where
                ORDER BY $sort_column_sql $sort_order
                LIMIT 100";
        $recent_clicks = db_fetch_all($sql, $params);
        $is_grouped_view = false;
    }
    
    // Get unique query parameters for filter dropdown
    $sql = "SELECT DISTINCT query_params 
            FROM " . table('clicks') . " c
            $where AND query_params IS NOT NULL AND query_params != ''
            ORDER BY query_params ASC
            LIMIT 50";
    $all_params = db_fetch_all($sql, $params);
    
    // Extract unique parameter keys for dropdown
    $unique_params = [];
    foreach ($all_params as $row) {
        if (!empty($row['query_params'])) {
            parse_str($row['query_params'], $parsed);
            foreach ($parsed as $key => $value) {
                if (!in_array($key, $unique_params)) {
                    $unique_params[] = $key;
                }
            }
        }
    }
    sort($unique_params);
    
} catch (Exception $e) {
    log_error('Failed to fetch analytics: ' . $e->getMessage());
}

// Helper functions for displaying data
function format_query_params($query_string) {
    if (empty($query_string)) {
        return '<span class="text-gray-400 italic">Direct</span>';
    }
    
    parse_str($query_string, $params);
    $badges = [];
    
    foreach ($params as $key => $value) {
        // Skip 'slug' parameter - it's redundant with the Link column
        if (strtolower($key) === 'slug') {
            continue;
        }
        
        $color_class = 'bg-blue-100 text-blue-800';
        if (in_array(strtolower($key), ['utm_source', 'utm_campaign', 'utm_medium'])) {
            $color_class = 'bg-purple-100 text-purple-800';
        } elseif (in_array(strtolower($key), ['fbclid', 'gclid', 'msclkid', 'ttclid'])) {
            $color_class = 'bg-green-100 text-green-800';
        }
        
        $display_value = is_array($value) ? implode(',', $value) : $value;
        $badges[] = '<span class="inline-block px-2 py-1 text-xs font-medium rounded ' . $color_class . '">' . 
                    htmlspecialchars($key) . (empty($display_value) ? '' : ': ' . htmlspecialchars(substr($display_value, 0, 20))) . 
                    '</span>';
    }
    
    // If only slug was present and we filtered it out, show as Direct
    if (empty($badges)) {
        return '<span class="text-gray-400 italic">Direct</span>';
    }
    
    return implode(' ', $badges);
}

function format_referrer_display($referrer, $inferred_source, $source_confidence) {
    // Use inferred source if referrer is empty
    if (empty($referrer) && !empty($inferred_source)) {
        $confidence_badge = $source_confidence === 'high' ? 'bg-orange-100 text-orange-800' : 'bg-yellow-100 text-yellow-800';
        return '<span class="inline-flex items-center space-x-1">
                    <span class="px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-800">' . 
                    htmlspecialchars($inferred_source) . 
                    '</span>
                    <span class="px-1 py-0.5 text-xs rounded ' . $confidence_badge . '">(inferred)</span>
                </span>';
    }
    
    if (empty($referrer)) {
        return '<span class="px-2 py-1 text-xs font-medium rounded bg-gray-100 text-gray-600">Direct Traffic</span>';
    }
    
    // Extract domain from referrer URL
    $domain = parse_url($referrer, PHP_URL_HOST);
    if (!$domain) {
        $domain = $referrer;
    }
    
    // Check if internal (same domain)
    $current_domain = parse_url(SITE_URL, PHP_URL_HOST);
    if ($domain === $current_domain) {
        return '<span class="px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-800" title="' . htmlspecialchars($referrer) . '">Internal</span>';
    }
    
    return '<span class="px-2 py-1 text-xs font-medium rounded bg-green-100 text-green-800" title="' . htmlspecialchars($referrer) . '">' . htmlspecialchars($domain) . '</span>';
}

// Helper function for sortable table headers (analytics clicks table)
function sortable_header_analytics($column_key, $label, $current_sort, $current_order, $style = '', $center = false) {
    $new_order = ($current_sort === $column_key && $current_order === 'DESC') ? 'asc' : 'desc';
    $arrow = '';
    
    if ($current_sort === $column_key) {
        $arrow = $current_order === 'ASC' ? ' ↑' : ' ↓';
    }
    
    // Preserve all current GET parameters
    $params = $_GET;
    $params['sort'] = $column_key;
    $params['order'] = $new_order;
    
    $url = '?' . http_build_query($params);
    
    $style_attr = $style ? ' style="' . $style . '"' : '';
    $text_align = $center ? 'text-center' : 'text-left';
    
    return '<th class="px-3 py-3 ' . $text_align . ' text-xs font-medium text-gray-500 uppercase hover:bg-gray-100 cursor-pointer transition-colors"' . $style_attr . '>
                <a href="' . htmlspecialchars($url) . '" class="block w-full h-full flex items-center ' . ($center ? 'justify-center' : 'justify-between') . '">
                    <span>' . $label . '</span>
                    <span class="ml-1 text-purple-600 font-bold">' . $arrow . '</span>
                </a>
            </th>';
}

function format_environment($device_type, $browser, $browser_version, $os, $os_version) {
    // Device icon
    $device_icon = '💻'; // Default desktop
    if (strtolower($device_type) === 'mobile') {
        $device_icon = '📱';
    } elseif (strtolower($device_type) === 'tablet') {
        $device_icon = '🖥️';
    }
    
    // Browser display (name only, version in tooltip)
    $browser_name = htmlspecialchars($browser ?? 'Unknown');
    $browser_full = $browser_name;
    if (!empty($browser_version)) {
        $browser_full .= ' ' . htmlspecialchars($browser_version);
    }
    
    // OS display (name only, version in tooltip)
    $os_name = htmlspecialchars($os ?? 'Unknown');
    $os_full = $os_name;
    if (!empty($os_version)) {
        $os_full .= ' ' . htmlspecialchars($os_version);
    }
    
    // Tooltip with full details
    $tooltip = htmlspecialchars(ucfirst($device_type ?? 'Unknown')) . ' • ' . $browser_full . ' • ' . $os_full;
    
    return '<div class="flex items-center space-x-1" title="' . $tooltip . '">
                <span class="text-lg leading-none">' . $device_icon . '</span>
                <span class="text-xs text-gray-700">' . $browser_name . '</span>
                <span class="text-gray-400 text-xs">•</span>
                <span class="text-xs text-gray-600">' . $os_name . '</span>
            </div>';
}

function get_country_flag($country_code) {
    if (empty($country_code) || strlen($country_code) != 2) {
        return '';
    }
    
    $code = strtoupper($country_code);
    
    // Try emoji flag first
    $flag = '';
    for ($i = 0; $i < strlen($code); $i++) {
        $flag .= mb_chr(ord($code[$i]) + 127397);
    }
    
    // Return with styled span for better rendering
    // The emoji-flag class will use a font that supports flags
    return '<span class="emoji-flag" title="' . $code . '">' . $flag . '</span>';
}

$page_title = 'Analytics';
$active_page = 'analytics';

include __DIR__ . '/includes/header.php';
?>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Filters</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Date Range Filter -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
            <select 
                id="days-filter" 
                onchange="applyFilters()"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            >
                <option value="1" <?php echo $days === 1 ? 'selected' : ''; ?>>Last 24 hours</option>
                <option value="2" <?php echo $days === 2 ? 'selected' : ''; ?>>Last 48 hours</option>
                <option value="5" <?php echo $days === 5 ? 'selected' : ''; ?>>Last 5 days</option>
                <option value="7" <?php echo $days === 7 ? 'selected' : ''; ?>>Last 7 days</option>
                <option value="14" <?php echo $days === 14 ? 'selected' : ''; ?>>Last 14 days</option>
                <option value="30" <?php echo $days === 30 ? 'selected' : ''; ?>>Last 30 days</option>
                <option value="90" <?php echo $days === 90 ? 'selected' : ''; ?>>Last 90 days</option>
            </select>
        </div>
        
        <!-- Link Filter -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Link</label>
            <select 
                id="link-filter" 
                onchange="applyFilters()"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            >
                <option value="0">All Links</option>
                <?php foreach ($all_links as $link): ?>
                    <option value="<?php echo $link['id']; ?>" <?php echo $link_id === (int)$link['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($link['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Bot Filter -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Traffic Type</label>
            <select 
                id="bot-filter" 
                onchange="applyFilters()"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            >
                <option value="human" <?php echo $bot_filter === 'human' ? 'selected' : ''; ?>>Humans Only</option>
                <option value="bot" <?php echo $bot_filter === 'bot' ? 'selected' : ''; ?>>Bots Only</option>
                <option value="all" <?php echo $bot_filter === 'all' ? 'selected' : ''; ?>>All Traffic</option>
            </select>
        </div>
        
        <!-- Parameter Filter -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Parameters</label>
            <select 
                id="param-filter"
                onchange="applyFilters()"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            >
                <option value="">All Parameters</option>
                <option value="direct" <?php echo $filter_param === 'direct' ? 'selected' : ''; ?>>Direct (No Parameters)</option>
                <?php foreach ($unique_params as $param): ?>
                    <option value="<?php echo htmlspecialchars($param); ?>" <?php echo $filter_param === $param ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($param); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
    </div>
    
    <?php if (!empty($filter_referrer)): ?>
    <div class="mt-4 flex items-center space-x-2">
        <span class="text-sm text-gray-600">Filtered by referrer:</span>
        <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-lg text-sm font-medium"><?php echo htmlspecialchars($filter_referrer); ?></span>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['filter_referrer' => ''])); ?>" class="text-sm text-red-600 hover:text-red-700">Clear ✕</a>
    </div>
    <?php endif; ?>
</div>

<!-- Overview Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <p class="text-sm font-medium text-gray-600 mb-1">Total Clicks</p>
        <p class="text-3xl font-bold text-gray-900"><?php echo format_number($overview['total_clicks']); ?></p>
        <p class="text-sm text-gray-500 mt-2">
            <?php echo format_number($overview['human_clicks']); ?> human / <?php echo format_number($overview['bot_clicks']); ?> bot
        </p>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <p class="text-sm font-medium text-gray-600 mb-1">Human Clicks</p>
        <p class="text-3xl font-bold text-green-600"><?php echo format_number($overview['human_clicks']); ?></p>
        <p class="text-sm text-gray-500 mt-2"><?php echo $overview['total_clicks'] > 0 ? round(($overview['human_clicks'] / $overview['total_clicks']) * 100, 1) : 0; ?>% of total</p>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <p class="text-sm font-medium text-gray-600 mb-1">Unique Clicks</p>
        <p class="text-3xl font-bold text-gray-900"><?php echo format_number($overview['unique_clicks']); ?></p>
        <p class="text-sm text-gray-500 mt-2"><?php echo $overview['human_clicks'] > 0 ? round(($overview['unique_clicks'] / $overview['human_clicks']) * 100, 1) : 0; ?>% unique rate</p>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <p class="text-sm font-medium text-gray-600 mb-1">Avg Clicks/Day</p>
        <p class="text-3xl font-bold text-gray-900"><?php echo $avg_clicks_per_day; ?></p>
        <p class="text-sm text-gray-500 mt-2">Daily average</p>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <p class="text-sm font-medium text-gray-600 mb-1">Most Active Day</p>
        <p class="text-3xl font-bold text-gray-900"><?php echo isset($most_active_day['count']) ? format_number($most_active_day['count']) : '0'; ?></p>
        <p class="text-sm text-gray-500 mt-2"><?php echo isset($most_active_day['date']) ? date('M j', strtotime($most_active_day['date'])) : 'N/A'; ?></p>
    </div>
    
</div>

<!-- Clicks Over Time Chart -->
<div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-8">
    <h2 class="text-xl font-bold text-gray-900 mb-6">Clicks Over Time</h2>
    <canvas id="clicksTimeChart" height="80"></canvas>
</div>

<!-- Charts Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    
    <!-- Traffic by Country -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Traffic by Country</h2>
        <?php if (empty($by_country)): ?>
            <p class="text-gray-500 text-center py-8">No data available</p>
        <?php else: ?>
            <canvas id="countryChart" height="200"></canvas>
        <?php endif; ?>
    </div>
    
    <!-- Device Types -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Device Types</h2>
        <?php if (empty($by_device)): ?>
            <p class="text-gray-500 text-center py-8">No data available</p>
        <?php else: ?>
            <canvas id="deviceChart" height="200"></canvas>
        <?php endif; ?>
    </div>
    
    <!-- Top Browsers -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Top Browsers</h2>
        <?php if (empty($by_browser)): ?>
            <p class="text-gray-500 text-center py-8">No data available</p>
        <?php else: ?>
            <canvas id="browserChart" height="200"></canvas>
        <?php endif; ?>
    </div>
    
    <!-- Operating Systems -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Operating Systems</h2>
        <?php if (empty($by_os)): ?>
            <p class="text-gray-500 text-center py-8">No data available</p>
        <?php else: ?>
            <canvas id="osChart" height="200"></canvas>
        <?php endif; ?>
    </div>
    
</div>

<!-- Top Referrers -->
<?php if (!empty($by_referrer)): ?>
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Top Referrers</h2>
        <div class="space-y-3">
            <?php foreach ($by_referrer as $ref): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['filter_referrer' => htmlspecialchars($ref['referrer_source'])])); ?>" 
                   class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-purple-50 hover:border-purple-200 border border-transparent transition-colors cursor-pointer group">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate group-hover:text-purple-700">
                            <?php echo htmlspecialchars($ref['referrer_source']); ?>
                        </p>
                    </div>
                    <div class="ml-4 flex-shrink-0">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800 group-hover:bg-purple-200 transition-colors">
                            <?php echo format_number($ref['count']); ?> clicks
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Detailed Clicks Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-8">
    <div class="p-6 border-b border-gray-200 flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-900">Detailed Clicks</h2>
        <div class="flex items-center space-x-3">
            <!-- IP Grouping Toggle -->
            <div class="flex items-center bg-gray-100 rounded-lg p-1">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['group_by_ip' => '0', 'ip_range' => ''])); ?>" 
                   class="px-3 py-2 text-sm font-medium rounded-md transition-colors <?php echo !$group_by_ip ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'; ?>">
                    Individual
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['group_by_ip' => '1', 'ip_range' => ''])); ?>" 
                   class="px-3 py-2 text-sm font-medium rounded-md transition-colors <?php echo $group_by_ip ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'; ?>">
                    Grouped by IP
                </a>
            </div>
            
            <!-- Bot Filter Dropdown -->
            <select onchange="window.location.href=this.value" 
                    class="px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500">
                <option value="?<?php echo http_build_query(array_merge($_GET, ['bot' => 'all'])); ?>" 
                        <?php echo $bot_filter === 'all' ? 'selected' : ''; ?>>
                    Show All Traffic
                </option>
                <option value="?<?php echo http_build_query(array_merge($_GET, ['bot' => 'human'])); ?>" 
                        <?php echo $bot_filter === 'human' ? 'selected' : ''; ?>>
                    Humans Only
                </option>
                <option value="?<?php echo http_build_query(array_merge($_GET, ['bot' => 'bot'])); ?>" 
                        <?php echo $bot_filter === 'bot' ? 'selected' : ''; ?>>
                    Bots Only
                </option>
            </select>
            
            <button 
                onclick="exportCSV()"
                class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Export CSV
            </button>
        </div>
    </div>
    
    <?php if (!empty($ip_range)): ?>
    <div class="px-6 py-3 bg-blue-50 border-b border-blue-100 flex items-center justify-between">
        <div class="flex items-center space-x-2">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
            </svg>
            <span class="text-sm font-medium text-blue-900">Filtered by IP range:</span>
            <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-lg text-sm font-medium font-mono">
                <?php echo htmlspecialchars($ip_range); ?><?php echo strpos($ip_range, ':') !== false ? ':xx' : '.xx'; ?>
            </span>
        </div>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['ip_range' => ''])); ?>" 
           class="text-sm text-blue-600 hover:text-blue-700 font-medium">
            Clear Filter ✕
        </a>
    </div>
    <?php endif; ?>
    
    <?php if (empty($recent_clicks)): ?>
        <div class="p-12 text-center">
            <p class="text-gray-500">No clicks in this period</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
            <?php if ($is_grouped_view): ?>
                <!-- GROUPED VIEW: Show IP ranges with click counts -->
                <table class="w-full" id="clicks-table">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Range</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Clicks</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Links</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date Range</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recent_clicks as $group): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono">
                                    <span class="font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($group['ip_range']); ?><?php echo strpos($group['ip_range'], ':') !== false ? ':xx' : '.xx'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-blue-100 text-blue-800">
                                        <?php echo format_number($group['click_count']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?php if (!empty($group['country_code'])): ?>
                                        <span class="mr-1"><?php echo get_country_flag($group['country_code']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($group['city'] && $group['country_name']): ?>
                                        <?php echo htmlspecialchars($group['city']); ?>, <?php echo htmlspecialchars($group['country_code'] ?? $group['country_name']); ?>
                                    <?php elseif ($group['country_name']): ?>
                                        <?php echo htmlspecialchars($group['country_code'] ?? $group['country_name']); ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php echo htmlspecialchars(substr($group['links'], 0, 50)) . (strlen($group['links']) > 50 ? '...' : ''); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php 
                                    // If any clicks are bots, show Bot
                                    $is_bot = $group['bot_count'] > 0;
                                    ?>
                                    <?php if ($is_bot): ?>
                                        <span class="inline-block px-2 py-1 text-xs font-medium text-red-800 bg-red-100 rounded">
                                            Bot
                                        </span>
                                        <?php if ($group['human_count'] > 0): ?>
                                            <span class="text-xs text-gray-500 ml-1">(+<?php echo $group['human_count']; ?> human)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="inline-block px-2 py-1 text-xs font-medium text-green-800 bg-green-100 rounded">
                                            Human
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php echo date('M j', strtotime($group['first_click'])); ?> - <?php echo date('M j', strtotime($group['last_click'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['ip_range' => $group['ip_range'], 'group_by_ip' => '0'])); ?>" 
                                       class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-xs font-medium rounded hover:bg-blue-700 transition-colors">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <!-- INDIVIDUAL VIEW: Show each click separately -->
                <table class="w-full table-auto" id="clicks-table" style="min-width: 900px;">
                    <thead class="bg-gray-50 border-b border-gray-200 sticky top-0 z-10">
                        <tr>
                            <?php echo sortable_header_analytics('date', 'Date', $sort_column, $sort_order, 'width: 110px;'); ?>
                            <?php echo sortable_header_analytics('referrer', 'Referrer', $sort_column, $sort_order, 'width: 140px;'); ?>
                            <?php echo sortable_header_analytics('link', 'Link', $sort_column, $sort_order, 'width: 180px;'); ?>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Parameters</th>
                            <?php echo sortable_header_analytics('country', 'Location', $sort_column, $sort_order, 'width: 150px;'); ?>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase" style="width: 160px;">Environment</th>
                            <?php echo sortable_header_analytics('type', 'Type', $sort_column, $sort_order, 'width: 80px;', true); ?>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recent_clicks as $click): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <!-- Date: Compact format -->
                            <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-900">
                                <div class="font-medium"><?php echo date('M j', strtotime($click['clicked_at'])); ?></div>
                                <div class="text-gray-500"><?php echo date('g:i A', strtotime($click['clicked_at'])); ?></div>
                            </td>
                            
                            <!-- Referrer -->
                            <td class="px-4 py-3 text-xs">
                                <?php echo format_referrer_display($click['referrer'] ?? null, $click['inferred_source'] ?? null, $click['source_confidence'] ?? 'none'); ?>
                            </td>
                            
                            <!-- Link -->
                            <td class="px-4 py-3 text-xs">
                                <p class="font-medium text-gray-900 truncate" style="max-width: 180px;" title="<?php echo htmlspecialchars($click['title']); ?>">
                                    <?php echo htmlspecialchars($click['title']); ?>
                                </p>
                                <p class="text-gray-500 font-mono text-xs">/<?php echo htmlspecialchars($click['slug']); ?></p>
                            </td>
                            
                            <!-- Parameters -->
                            <td class="px-4 py-3 text-xs">
                                <?php echo format_query_params($click['query_params'] ?? null); ?>
                            </td>
                            
                            <!-- Location (Country + IP) -->
                            <td class="px-4 py-3 text-xs">
                                <div class="text-gray-900">
                                    <?php if (!empty($click['country_code'])): ?>
                                        <span class="mr-1"><?php echo get_country_flag($click['country_code']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($click['city'] && $click['country_name']): ?>
                                        <?php echo htmlspecialchars($click['city']); ?>, <?php echo htmlspecialchars($click['country_code'] ?? $click['country_name']); ?>
                                    <?php elseif ($click['country_name']): ?>
                                        <?php echo htmlspecialchars($click['country_code'] ?? $click['country_name']); ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">Unknown</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-gray-500 font-mono text-xs mt-0.5">
                                    <?php echo htmlspecialchars($click['ip_address'] ?? 'Unknown'); ?>
                                </div>
                            </td>
                            
                            <!-- Environment (Device + Browser + OS combined) -->
                            <td class="px-4 py-3 text-xs">
                                <?php echo format_environment(
                                    $click['device_type'] ?? 'Unknown',
                                    $click['browser'] ?? 'Unknown',
                                    $click['browser_version'] ?? '',
                                    $click['os'] ?? 'Unknown',
                                    $click['os_version'] ?? ''
                                ); ?>
                            </td>
                            
                            <!-- Type (Bot/Human) -->
                            <td class="px-3 py-3 whitespace-nowrap text-center">
                                <?php if (!empty($click['is_bot'])): ?>
                                    <span class="inline-block px-2 py-1 text-xs font-medium text-red-800 bg-red-100 rounded">
                                        Bot
                                    </span>
                                <?php else: ?>
                                    <span class="inline-block px-2 py-1 text-xs font-medium text-green-800 bg-green-100 rounded">
                                        Human
                                    </span>
                                    <?php if (!empty($click['is_unique'])): ?>
                                        <div class="mt-1">
                                            <span class="inline-block px-1.5 py-0.5 text-xs font-medium text-blue-800 bg-blue-100 rounded">
                                                Unique
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
/* Table Responsive Improvements */
#clicks-table {
    font-size: 0.875rem;
}

#clicks-table thead th {
    background-color: #f9fafb;
    position: sticky;
    top: 0;
    z-index: 10;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

/* Smooth scrolling for touch devices */
.overflow-x-auto {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 #f1f5f9;
}

.overflow-x-auto::-webkit-scrollbar {
    height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Compact badge styling */
#clicks-table td span {
    white-space: nowrap;
}

/* Mobile optimizations */
@media (max-width: 768px) {
    #clicks-table {
        font-size: 0.75rem;
    }
    
    #clicks-table th,
    #clicks-table td {
        padding: 0.5rem 0.375rem;
    }
    
    /* Make tap targets larger on mobile */
    #clicks-table a {
        min-height: 44px;
        display: inline-flex;
        align-items: center;
    }
}

/* Tooltip hover effect */
[title]:hover {
    cursor: help;
}

/* Flag emoji support - use fonts that render flags properly */
.emoji-flag {
    font-family: "Segoe UI Emoji", "Noto Color Emoji", "Apple Color Emoji", "Segoe UI Symbol", sans-serif;
    font-size: 1.2em;
}

/* Fallback: If browser shows letter codes instead of flags, style them */
.emoji-flag::before {
    /* This ensures flags display with proper font support */
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Apply filters
function applyFilters() {
    const days = document.getElementById('days-filter').value;
    const link = document.getElementById('link-filter').value;
    const bot = document.getElementById('bot-filter').value;
    const param = document.getElementById('param-filter').value;
    
    let url = '?days=' + days;
    if (link > 0) {
        url += '&link=' + link;
    }
    if (bot !== 'human') {
        url += '&bot=' + bot;
    }
    if (param) {
        url += '&filter_param=' + encodeURIComponent(param);
    }
    
    window.location.href = url;
}

// Export CSV
function exportCSV() {
    const days = document.getElementById('days-filter').value;
    const link = document.getElementById('link-filter').value;
    
    let url = '<?php echo TRACKER_PATH; ?>/ajax/export-csv.php?days=' + days;
    if (link > 0) {
        url += '&link=' + link;
    }
    
    window.location.href = url;
}

// Chart colors
const chartColors = {
    primary: 'rgb(124, 58, 237)',
    secondary: 'rgb(99, 102, 241)',
    success: 'rgb(34, 197, 94)',
    warning: 'rgb(245, 158, 11)',
    danger: 'rgb(239, 68, 68)',
    info: 'rgb(59, 130, 246)',
};

// Clicks over time chart
new Chart(document.getElementById('clicksTimeChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_dates); ?>,
        datasets: [{
            label: 'Clicks',
            data: <?php echo json_encode($chart_values); ?>,
            borderColor: chartColors.primary,
            backgroundColor: 'rgba(124, 58, 237, 0.1)',
            tension: 0.4,
            fill: true,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});

<?php if (!empty($by_country)): ?>
// Country chart
new Chart(document.getElementById('countryChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_column($by_country, 'country_name')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($by_country, 'count')); ?>,
            backgroundColor: [
                chartColors.primary,
                chartColors.secondary,
                chartColors.success,
                chartColors.warning,
                chartColors.danger,
                chartColors.info,
                'rgb(236, 72, 153)',
                'rgb(168, 85, 247)',
                'rgb(14, 165, 233)',
                'rgb(132, 204, 22)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
<?php endif; ?>

<?php if (!empty($by_device)): ?>
// Device chart
new Chart(document.getElementById('deviceChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map('ucfirst', array_column($by_device, 'device_type'))); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($by_device, 'count')); ?>,
            backgroundColor: [chartColors.primary, chartColors.success, chartColors.warning]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
<?php endif; ?>

<?php if (!empty($by_browser)): ?>
// Browser chart
new Chart(document.getElementById('browserChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($by_browser, 'browser')); ?>,
        datasets: [{
            label: 'Clicks',
            data: <?php echo json_encode(array_column($by_browser, 'count')); ?>,
            backgroundColor: chartColors.secondary
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});
<?php endif; ?>

<?php if (!empty($by_os)): ?>
// OS chart
new Chart(document.getElementById('osChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($by_os, 'os')); ?>,
        datasets: [{
            label: 'Clicks',
            data: <?php echo json_encode(array_column($by_os, 'count')); ?>,
            backgroundColor: chartColors.success
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

