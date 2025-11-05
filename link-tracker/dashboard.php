<?php
/**
 * Admin Dashboard - Main Overview Page
 */

define('LT_INIT', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

start_session();
$user = require_auth();

// Get statistics
$stats = [];

try {
    // Total clicks
    $sql = "SELECT COUNT(*) as count FROM " . table('clicks');
    $result = db_fetch($sql);
    $stats['total_clicks'] = $result['count'];
    
    // Unique clicks
    $sql = "SELECT COUNT(*) as count FROM " . table('clicks') . " WHERE is_unique = 1";
    $result = db_fetch($sql);
    $stats['unique_clicks'] = $result['count'];
    
    // Total active links
    $sql = "SELECT COUNT(*) as count FROM " . table('links') . " WHERE is_active = 1";
    $result = db_fetch($sql);
    $stats['active_links'] = $result['count'];
    
    // Clicks today
    $sql = "SELECT COUNT(*) as count FROM " . table('clicks') . " 
            WHERE DATE(clicked_at) = CURDATE()";
    $result = db_fetch($sql);
    $stats['clicks_today'] = $result['count'];
    
    // Clicks last 30 days (for chart)
    $sql = "SELECT DATE(clicked_at) as date, COUNT(*) as count 
            FROM " . table('clicks') . " 
            WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(clicked_at)
            ORDER BY date ASC";
    $stats['clicks_30_days'] = db_fetch_all($sql);
    
    // Top 5 performing links
    $sql = "SELECT l.id, l.title, l.slug, 
            COUNT(c.id) as total_clicks,
            SUM(c.is_unique) as unique_clicks
            FROM " . table('links') . " l
            LEFT JOIN " . table('clicks') . " c ON l.id = c.link_id
            WHERE l.is_active = 1
            GROUP BY l.id
            ORDER BY total_clicks DESC
            LIMIT 5";
    $stats['top_links'] = db_fetch_all($sql);
    
    // Recent activity (last 20 clicks)
    $sql = "SELECT c.*, l.title, l.slug 
            FROM " . table('clicks') . " c
            JOIN " . table('links') . " l ON c.link_id = l.id
            ORDER BY c.clicked_at DESC
            LIMIT 20";
    $stats['recent_activity'] = db_fetch_all($sql);
    
} catch (Exception $e) {
    log_error('Failed to fetch dashboard stats: ' . $e->getMessage());
    $stats = [
        'total_clicks' => 0,
        'unique_clicks' => 0,
        'active_links' => 0,
        'clicks_today' => 0,
        'clicks_30_days' => [],
        'top_links' => [],
        'recent_activity' => []
    ];
}

// Prepare chart data
$chart_labels = [];
$chart_data = [];

// Fill in missing dates for chart
$start_date = strtotime('-29 days');
for ($i = 0; $i < 30; $i++) {
    $date = date('Y-m-d', strtotime("+$i days", $start_date));
    $chart_labels[] = date('M j', strtotime($date));
    $chart_data[$date] = 0;
}

foreach ($stats['clicks_30_days'] as $row) {
    $chart_data[$row['date']] = (int)$row['count'];
}

$chart_values = array_values($chart_data);

$page_title = 'Dashboard';
$active_page = 'dashboard';

include __DIR__ . '/includes/header.php';
?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    
    <!-- Total Clicks -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Total Clicks</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo format_number($stats['total_clicks']); ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path>
                </svg>
            </div>
        </div>
    </div>
    
    <!-- Unique Clicks -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Unique Clicks</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo format_number($stats['unique_clicks']); ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
            </div>
        </div>
    </div>
    
    <!-- Active Links -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Active Links</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo format_number($stats['active_links']); ?></p>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                </svg>
            </div>
        </div>
    </div>
    
    <!-- Clicks Today -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Clicks Today</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo format_number($stats['clicks_today']); ?></p>
            </div>
            <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>
    
</div>

<!-- Clicks Chart -->
<div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-8">
    <h2 class="text-xl font-bold text-gray-900 mb-6">Clicks Over Last 30 Days</h2>
    <canvas id="clicksChart" height="80"></canvas>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    
    <!-- Top Performing Links -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Top 5 Performing Links</h2>
        
        <?php if (empty($stats['top_links'])): ?>
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                </svg>
                <p class="text-gray-600 mb-4">No links yet</p>
                <a href="<?php echo TRACKER_PATH; ?>/links.php" class="inline-block bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors">
                    Create Your First Link
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($stats['top_links'] as $link): ?>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($link['title']); ?></h3>
                            <p class="text-sm text-gray-600">/best/<?php echo htmlspecialchars($link['slug']); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-gray-900"><?php echo format_number($link['total_clicks']); ?></p>
                            <p class="text-xs text-gray-600"><?php echo format_number($link['unique_clicks']); ?> unique</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Recent Activity -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Recent Activity</h2>
        
        <?php if (empty($stats['recent_activity'])): ?>
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <p class="text-gray-600">No activity yet</p>
            </div>
        <?php else: ?>
            <div class="space-y-3 max-h-96 overflow-y-auto">
                <?php foreach ($stats['recent_activity'] as $click): ?>
                    <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                            <?php if ($click['device_type'] === 'mobile'): ?>
                                <svg class="w-4 h-4 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M7 2a2 2 0 00-2 2v12a2 2 0 002 2h6a2 2 0 002-2V4a2 2 0 00-2-2H7zm3 14a1 1 0 100-2 1 1 0 000 2z"/>
                                </svg>
                            <?php else: ?>
                                <svg class="w-4 h-4 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">
                                <?php echo htmlspecialchars($click['title']); ?>
                            </p>
                            <p class="text-xs text-gray-600">
                                <?php echo htmlspecialchars($click['country_name'] ?? 'Unknown'); ?> • 
                                <?php echo htmlspecialchars($click['device_type']); ?> • 
                                <?php echo time_ago($click['clicked_at']); ?>
                            </p>
                        </div>
                        <?php if ($click['is_unique']): ?>
                            <span class="flex-shrink-0 inline-block px-2 py-1 text-xs font-medium text-green-800 bg-green-100 rounded">
                                New
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // Clicks Chart
    const ctx = document.getElementById('clicksChart').getContext('2d');
    const clicksChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Clicks',
                data: <?php echo json_encode($chart_values); ?>,
                borderColor: 'rgb(124, 58, 237)',
                backgroundColor: 'rgba(124, 58, 237, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: 'rgb(124, 58, 237)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(124, 58, 237, 0.5)',
                    borderWidth: 1
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

