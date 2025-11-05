<?php
/**
 * Link Detail & Placement Management Page
 * Shows link information and manages all placements for this link
 */

define('LT_INIT', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

start_session();
$user = require_auth();

// Get link ID
$link_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($link_id <= 0) {
    redirect(TRACKER_PATH . '/links.php');
}

// Get link data
try {
    $sql = "SELECT l.*,
            COUNT(DISTINCT c.id) as total_clicks,
            SUM(c.is_unique) as unique_clicks,
            MAX(c.clicked_at) as last_click
            FROM " . table('links') . " l
            LEFT JOIN " . table('clicks') . " c ON l.id = c.link_id
            WHERE l.id = ?
            GROUP BY l.id";
    
    $link = db_fetch($sql, [$link_id]);
    
    if (!$link) {
        redirect(TRACKER_PATH . '/links.php');
    }
    
} catch (Exception $e) {
    log_error('Failed to fetch link: ' . $e->getMessage());
    redirect(TRACKER_PATH . '/links.php');
}

// Get sorting and filtering options
$sort = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'rating_desc';
$filter_platform = isset($_GET['platform']) ? sanitize_input($_GET['platform']) : '';
$filter_status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

// Build WHERE clause for placements
$where = "WHERE link_id = ?";
$params = [$link_id];

if (!empty($filter_platform)) {
    if ($filter_platform === 'other') {
        $where .= " AND (platform = 'Other' OR platform IS NULL)";
    } else {
        $where .= " AND platform = ?";
        $params[] = $filter_platform;
    }
}

if (!empty($filter_status)) {
    $where .= " AND status = ?";
    $params[] = $filter_status;
}

// Determine ORDER BY
$order_by = "star_rating DESC, created_at DESC";
switch ($sort) {
    case 'rating_asc':
        $order_by = "star_rating ASC, title ASC";
        break;
    case 'rating_desc':
        $order_by = "star_rating DESC, title ASC";
        break;
    case 'alpha_asc':
        $order_by = "title ASC";
        break;
    case 'alpha_desc':
        $order_by = "title DESC";
        break;
    case 'date_newest':
        $order_by = "date_added DESC";
        break;
    case 'date_oldest':
        $order_by = "date_added ASC";
        break;
}

// Get placements
try {
    $sql = "SELECT * FROM " . table('placements') . " $where ORDER BY $order_by";
    $placements = db_fetch_all($sql, $params);
} catch (Exception $e) {
    log_error('Failed to fetch placements: ' . $e->getMessage());
    $placements = [];
}

$placement_count = count($placements);

$page_title = $link['title'];
$active_page = 'links';

include __DIR__ . '/includes/header.php';
?>

<!-- Link Information Card -->
<div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl shadow-lg p-6 mb-6 text-white">
    <div class="flex items-start justify-between">
        <div class="flex-1">
            <div class="flex items-center space-x-2 mb-2">
                <a href="<?php echo TRACKER_PATH; ?>/links.php" class="text-purple-200 hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($link['title']); ?></h1>
            </div>
            
            <div class="flex items-center space-x-4 mt-4">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-purple-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                    </svg>
                    <span class="font-mono"><?php echo SITE_URL; ?>/best/<?php echo htmlspecialchars($link['slug']); ?></span>
                    <button onclick="copyToClipboard('<?php echo SITE_URL; ?>/best/<?php echo htmlspecialchars($link['slug']); ?>', this)" 
                            class="p-1 hover:bg-purple-500 rounded transition-colors"
                            title="Copy URL">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="text-sm text-purple-100 mt-2">
                Redirects to: <span class="font-mono"><?php echo htmlspecialchars(substr($link['destination_url'], 0, 60)); ?><?php echo strlen($link['destination_url']) > 60 ? '...' : ''; ?></span>
            </div>
        </div>
        
        <div class="text-right">
            <a href="<?php echo TRACKER_PATH; ?>/analytics.php?link=<?php echo $link_id; ?>&group_by_ip=0" 
               class="block bg-white bg-opacity-20 rounded-lg px-4 py-2 mb-2 hover:bg-opacity-30 transition-colors cursor-pointer"
               title="View detailed analytics">
                <div class="text-3xl font-bold"><?php echo format_number($link['total_clicks'] ?? 0); ?></div>
                <div class="text-sm text-purple-100 flex items-center justify-center">
                    Total Clicks
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                    </svg>
                </div>
            </a>
            <div class="text-xs text-purple-100">
                <?php echo format_number($link['unique_clicks'] ?? 0); ?> unique
            </div>
            <?php if ($link['last_click']): ?>
                <div class="text-xs text-purple-200 mt-1">
                    Last: <?php echo time_ago($link['last_click']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Placements Section -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-8">
    
    <!-- Header -->
    <div class="p-6 border-b border-gray-200">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Link Placements</h2>
                <p class="text-gray-600 mt-1">
                    <?php echo $placement_count; ?> location<?php echo $placement_count !== 1 ? 's' : ''; ?> where this link is posted
                </p>
            </div>
            <a href="<?php echo TRACKER_PATH; ?>/placement-form.php?link_id=<?php echo $link_id; ?>" 
               class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700 transition-all shadow-lg hover:shadow-xl transform hover:scale-105">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Add New Placement
            </a>
        </div>
    </div>
    
    <?php if ($placement_count > 0): ?>
    
    <!-- Filters & Sort -->
    <div class="p-6 bg-gray-50 border-b border-gray-200">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            
            <!-- Sort By -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                <select id="sort-filter" onchange="applyFilters()" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                    <option value="rating_desc" <?php echo $sort === 'rating_desc' ? 'selected' : ''; ?>>Stars: High to Low</option>
                    <option value="rating_asc" <?php echo $sort === 'rating_asc' ? 'selected' : ''; ?>>Stars: Low to High</option>
                    <option value="alpha_asc" <?php echo $sort === 'alpha_asc' ? 'selected' : ''; ?>>Alphabetically A-Z</option>
                    <option value="alpha_desc" <?php echo $sort === 'alpha_desc' ? 'selected' : ''; ?>>Alphabetically Z-A</option>
                    <option value="date_newest" <?php echo $sort === 'date_newest' ? 'selected' : ''; ?>>Date: Newest First</option>
                    <option value="date_oldest" <?php echo $sort === 'date_oldest' ? 'selected' : ''; ?>>Date: Oldest First</option>
                </select>
            </div>
            
            <!-- Filter by Platform -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Platform</label>
                <select id="platform-filter" onchange="applyFilters()" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                    <option value="">All Platforms</option>
                    <option value="Reddit" <?php echo $filter_platform === 'Reddit' ? 'selected' : ''; ?>>Reddit</option>
                    <option value="Quora" <?php echo $filter_platform === 'Quora' ? 'selected' : ''; ?>>Quora</option>
                    <option value="Twitter/X" <?php echo $filter_platform === 'Twitter/X' ? 'selected' : ''; ?>>Twitter/X</option>
                    <option value="YouTube" <?php echo $filter_platform === 'YouTube' ? 'selected' : ''; ?>>YouTube</option>
                    <option value="TikTok" <?php echo $filter_platform === 'TikTok' ? 'selected' : ''; ?>>TikTok</option>
                    <option value="Facebook" <?php echo $filter_platform === 'Facebook' ? 'selected' : ''; ?>>Facebook</option>
                    <option value="Medium" <?php echo $filter_platform === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="LinkedIn" <?php echo $filter_platform === 'LinkedIn' ? 'selected' : ''; ?>>LinkedIn</option>
                    <option value="other" <?php echo $filter_platform === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            
            <!-- Filter by Status -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select id="status-filter" onchange="applyFilters()" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="removed" <?php echo $filter_status === 'removed' ? 'selected' : ''; ?>>Removed</option>
                    <option value="broken" <?php echo $filter_status === 'broken' ? 'selected' : ''; ?>>Broken</option>
                </select>
            </div>
            
        </div>
    </div>
    
    <!-- Placements Table -->
    <div class="overflow-x-auto">
        <table class="w-full" style="min-width: 800px;">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase" style="width: 100px;">Stars</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase" style="width: 120px;">Platform</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase" style="width: 200px;">URL</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase" style="width: 100px;">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase" style="width: 120px;">Date Added</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase" style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($placements as $placement): ?>
                    <tr class="hover:bg-gray-50 transition-colors" id="placement-row-<?php echo $placement['id']; ?>">
                        
                        <!-- Star Rating -->
                        <td class="px-4 py-3">
                            <div class="flex items-center space-x-1">
                                <span class="text-lg">
                                    <?php 
                                    // Show only the number of stars that match the rating
                                    for ($i = 1; $i <= $placement['star_rating']; $i++) {
                                        echo '⭐';
                                    }
                                    ?>
                                </span>
                                <span class="text-xs text-gray-600 ml-1">
                                    (<?php echo $placement['star_rating']; ?>/5)
                                </span>
                            </div>
                        </td>
                        
                        <!-- Title -->
                        <td class="px-4 py-3">
                            <p class="text-sm font-medium text-gray-900 <?php echo $placement['status'] === 'removed' ? 'line-through text-gray-500' : ''; ?>">
                                <?php echo htmlspecialchars($placement['title']); ?>
                            </p>
                            <?php if (!empty($placement['notes'])): ?>
                                <p class="text-xs text-gray-500 mt-1" title="<?php echo htmlspecialchars($placement['notes']); ?>">
                                    <?php echo htmlspecialchars(substr($placement['notes'], 0, 60)); ?><?php echo strlen($placement['notes']) > 60 ? '...' : ''; ?>
                                </p>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Platform -->
                        <td class="px-4 py-3">
                            <?php 
                            $platform = $placement['platform'] ?: 'Other';
                            $platform_display = $platform === 'Other' && !empty($placement['platform_custom']) 
                                ? htmlspecialchars($placement['platform_custom']) 
                                : htmlspecialchars($platform);
                            
                            $platform_colors = [
                                'Reddit' => 'bg-orange-100 text-orange-800',
                                'Quora' => 'bg-red-100 text-red-800',
                                'Twitter/X' => 'bg-blue-100 text-blue-800',
                                'YouTube' => 'bg-red-100 text-red-800',
                                'TikTok' => 'bg-gray-900 text-white',
                                'Facebook' => 'bg-blue-100 text-blue-800',
                                'Medium' => 'bg-green-100 text-green-800',
                                'LinkedIn' => 'bg-blue-100 text-blue-800',
                            ];
                            
                            $color_class = $platform_colors[$platform] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <span class="inline-block px-2 py-1 text-xs font-medium rounded <?php echo $color_class; ?>">
                                <?php echo $platform_display; ?>
                            </span>
                        </td>
                        
                        <!-- URL -->
                        <td class="px-4 py-3">
                            <a href="<?php echo htmlspecialchars($placement['url']); ?>" 
                               target="_blank"
                               class="text-blue-600 hover:text-blue-700 text-xs flex items-center space-x-1 <?php echo $placement['status'] === 'removed' ? 'line-through text-gray-400' : ''; ?>">
                                <span class="truncate" style="max-width: 180px;" title="<?php echo htmlspecialchars($placement['url']); ?>">
                                    <?php echo htmlspecialchars($placement['url']); ?>
                                </span>
                                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                            </a>
                        </td>
                        
                        <!-- Status -->
                        <td class="px-4 py-3 text-center">
                            <?php
                            $status_badges = [
                                'active' => 'bg-green-100 text-green-800',
                                'removed' => 'bg-red-100 text-red-800',
                                'broken' => 'bg-yellow-100 text-yellow-800'
                            ];
                            $status_class = $status_badges[$placement['status']] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <span class="inline-block px-2 py-1 text-xs font-medium rounded <?php echo $status_class; ?>">
                                <?php echo ucfirst($placement['status']); ?>
                            </span>
                        </td>
                        
                        <!-- Date Added -->
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?php echo date('M j, Y', strtotime($placement['date_added'])); ?>
                        </td>
                        
                        <!-- Actions -->
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center space-x-2">
                                <a href="<?php echo TRACKER_PATH; ?>/placement-form.php?link_id=<?php echo $link_id; ?>&id=<?php echo $placement['id']; ?>" 
                                   class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                   title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </a>
                                <button onclick="deletePlacement(<?php echo $placement['id']; ?>, '<?php echo htmlspecialchars(addslashes($placement['title'])); ?>')" 
                                        class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                        title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </td>
                        
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Export Button -->
    <div class="p-4 bg-gray-50 border-t border-gray-200">
        <button onclick="exportPlacementsCSV()" 
                class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Export Placements CSV
        </button>
    </div>
    
    <?php else: ?>
    
    <!-- Empty State -->
    <div class="p-12 text-center">
        <svg class="mx-auto h-24 w-24 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">No Placements Yet</h3>
        <p class="text-gray-600 mb-6">
            Start tracking where this link is posted across the web
        </p>
        <a href="<?php echo TRACKER_PATH; ?>/placement-form.php?link_id=<?php echo $link_id; ?>" 
           class="inline-flex items-center px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Add First Placement
        </a>
    </div>
    
    <?php endif; ?>
    
</div>

<script>
function applyFilters() {
    const sort = document.getElementById('sort-filter').value;
    const platform = document.getElementById('platform-filter').value;
    const status = document.getElementById('status-filter').value;
    
    let url = '?id=<?php echo $link_id; ?>';
    if (sort !== 'rating_desc') {
        url += '&sort=' + sort;
    }
    if (platform) {
        url += '&platform=' + encodeURIComponent(platform);
    }
    if (status) {
        url += '&status=' + status;
    }
    
    window.location.href = url;
}

async function deletePlacement(placementId, title) {
    if (!confirmAction(`Are you sure you want to delete "${title}"? This cannot be undone.`)) {
        return;
    }
    
    const response = await makeRequest('<?php echo TRACKER_PATH; ?>/ajax/delete-placement.php', 'POST', {
        id: placementId
    });
    
    if (response.success) {
        showToast('Placement deleted successfully!', 'success');
        document.getElementById('placement-row-' + placementId).style.opacity = '0';
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast(response.message || 'Failed to delete placement', 'error');
    }
}

function exportPlacementsCSV() {
    const sort = document.getElementById('sort-filter')?.value || 'rating_desc';
    const platform = document.getElementById('platform-filter')?.value || '';
    const status = document.getElementById('status-filter')?.value || '';
    
    let url = '<?php echo TRACKER_PATH; ?>/ajax/export-placements-csv.php?link_id=<?php echo $link_id; ?>';
    if (sort) url += '&sort=' + sort;
    if (platform) url += '&platform=' + encodeURIComponent(platform);
    if (status) url += '&status=' + status;
    
    window.location.href = url;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

