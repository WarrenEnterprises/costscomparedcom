<?php
/**
 * All Placements Page
 * Shows all link placements across all links
 */

define('LT_INIT', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

start_session();
$user = require_auth();

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filters
$filter_platform = isset($_GET['platform']) ? sanitize_input($_GET['platform']) : '';
$filter_status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$filter_link = isset($_GET['link']) ? (int)$_GET['link'] : 0;
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Sorting
$sort_column = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'stars';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Whitelist allowed sort columns (prevent SQL injection)
$allowed_sorts = [
    'stars' => 'p.star_rating',
    'link' => 'l.title',
    'platform' => 'p.platform',
    'status' => 'p.status',
    'clicks' => 'link_clicks',
    'date' => 'p.date_added'
];

// Validate sort column
if (!isset($allowed_sorts[$sort_column])) {
    $sort_column = 'stars';
}

$sort_column_sql = $allowed_sorts[$sort_column];

// Build WHERE clause
$where = "WHERE 1=1";
$params = [];

if (!empty($filter_platform)) {
    if ($filter_platform === 'other') {
        $where .= " AND (p.platform = 'Other' OR p.platform IS NULL)";
    } else {
        $where .= " AND p.platform = ?";
        $params[] = $filter_platform;
    }
}

if (!empty($filter_status)) {
    $where .= " AND p.status = ?";
    $params[] = $filter_status;
}

if ($filter_link > 0) {
    $where .= " AND p.link_id = ?";
    $params[] = $filter_link;
}

if (!empty($search)) {
    $where .= " AND (p.title LIKE ? OR p.url LIKE ? OR l.title LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

try {
    // Get all links for filter dropdown
    $sql = "SELECT id, title, slug FROM " . table('links') . " ORDER BY title ASC";
    $all_links = db_fetch_all($sql);
    
    // Count total placements
    $count_params = $params;
    $sql = "SELECT COUNT(*) as count 
            FROM " . table('placements') . " p
            JOIN " . table('links') . " l ON p.link_id = l.id
            $where";
    $result = db_fetch($sql, $count_params);
    $total_placements = $result['count'];
    $total_pages = ceil($total_placements / $per_page);
    
    // Get placements with click counts
    $sql = "SELECT p.*, 
            l.title as link_title, 
            l.slug as link_slug,
            COUNT(DISTINCT c.id) as link_clicks
            FROM " . table('placements') . " p
            JOIN " . table('links') . " l ON p.link_id = l.id
            LEFT JOIN " . table('clicks') . " c ON l.id = c.link_id
            $where
            GROUP BY p.id
            ORDER BY $sort_column_sql $sort_order, p.date_added DESC
            LIMIT $per_page OFFSET $offset";
    
    $placements = db_fetch_all($sql, $params);
    
    // Get stats
    $sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'removed' THEN 1 ELSE 0 END) as removed,
            SUM(CASE WHEN status = 'broken' THEN 1 ELSE 0 END) as broken,
            AVG(star_rating) as avg_rating
            FROM " . table('placements');
    $stats = db_fetch($sql);
    
} catch (Exception $e) {
    log_error('Failed to fetch placements: ' . $e->getMessage());
    $placements = [];
    $total_placements = 0;
    $total_pages = 0;
    $stats = ['total' => 0, 'active' => 0, 'removed' => 0, 'broken' => 0, 'avg_rating' => 0];
}

// Helper function for sortable table headers
function sortable_header($column_key, $label, $current_sort, $current_order, $style = '', $center = false) {
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
    $justify = $center ? 'justify-center' : 'justify-between';
    
    return '<th class="px-3 py-3 ' . $text_align . ' text-xs font-medium text-gray-500 uppercase hover:bg-gray-100 cursor-pointer transition-colors"' . $style_attr . '>
                <a href="' . htmlspecialchars($url) . '" class="block w-full h-full flex items-center ' . $justify . '">
                    <span>' . $label . '</span>
                    <span class="ml-1 text-purple-600 font-bold">' . $arrow . '</span>
                </a>
            </th>';
}

$page_title = 'All Link Placements';
$active_page = 'placements';

include __DIR__ . '/includes/header.php';
?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <p class="text-sm font-medium text-gray-600 mb-1">Total Placements</p>
        <p class="text-3xl font-bold text-gray-900"><?php echo format_number($stats['total']); ?></p>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <p class="text-sm font-medium text-gray-600 mb-1">Active</p>
        <p class="text-3xl font-bold text-green-600"><?php echo format_number($stats['active']); ?></p>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <p class="text-sm font-medium text-gray-600 mb-1">Removed</p>
        <p class="text-3xl font-bold text-red-600"><?php echo format_number($stats['removed']); ?></p>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <p class="text-sm font-medium text-gray-600 mb-1">Avg Rating</p>
        <p class="text-3xl font-bold text-purple-600"><?php echo number_format($stats['avg_rating'], 1); ?></p>
        <p class="text-xs text-gray-500 mt-1">out of 5.0</p>
    </div>
    
</div>

<!-- Add Placement Section -->
<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-bold text-gray-900">All Placements</h2>
    <button onclick="openAddPlacementModal()" 
            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700 transition-all shadow-lg hover:shadow-xl transform hover:scale-105">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
        </svg>
        Add Placement
    </button>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Filters</h3>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        
        <!-- Search -->
        <div class="md:col-span-2">
            <form method="GET" action="">
                <input type="search" 
                       name="search" 
                       placeholder="Search placements or links..."
                       value="<?php echo htmlspecialchars($search); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
            </form>
        </div>
        
        <!-- Filter by Link -->
        <div>
            <select id="link-filter" onchange="applyFilters()" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                <option value="0">All Links</option>
                <?php foreach ($all_links as $link): ?>
                    <option value="<?php echo $link['id']; ?>" <?php echo $filter_link === $link['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($link['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Filter by Platform -->
        <div>
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
        
    </div>
</div>

<!-- Placements Table -->
<?php if (empty($placements)): ?>
    
    <div class="bg-white rounded-xl shadow-sm p-12 text-center border border-gray-100">
        <svg class="mx-auto h-24 w-24 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">No placements found</h3>
        <p class="text-gray-600">
            <?php echo !empty($search) || !empty($filter_platform) || !empty($filter_status) ? 'Try adjusting your filters' : 'Start by creating a link and adding placements'; ?>
        </p>
    </div>
    
<?php else: ?>
    
    <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
        <div class="overflow-x-auto">
            <table class="w-full" style="min-width: 900px;">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <?php echo sortable_header('stars', 'Stars', $sort_column, $sort_order, 'width: 60px;'); ?>
                        <?php echo sortable_header('link', 'Link', $sort_column, $sort_order, ''); ?>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Placement Title</th>
                        <?php echo sortable_header('platform', 'Platform', $sort_column, $sort_order, 'width: 100px;'); ?>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase" style="width: 180px;">URL</th>
                        <?php echo sortable_header('status', 'Status', $sort_column, $sort_order, 'width: 85px;'); ?>
                        <?php echo sortable_header('clicks', 'Clicks', $sort_column, $sort_order, 'width: 80px;', true); ?>
                        <?php echo sortable_header('date', 'Date Added', $sort_column, $sort_order, 'width: 105px;'); ?>
                        <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase" style="width: 75px;">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($placements as $placement): ?>
                        <tr class="hover:bg-gray-50 transition-colors" id="placement-row-<?php echo $placement['id']; ?>">
                            
                            <!-- Stars (Compact) -->
                            <td class="px-3 py-3">
                                <div class="flex items-center">
                                    <span class="text-lg font-bold text-gray-900">
                                        ⭐<?php echo $placement['star_rating']; ?>
                                    </span>
                                </div>
                            </td>
                            
                            <!-- Link -->
                            <td class="px-3 py-3">
                                <a href="<?php echo TRACKER_PATH; ?>/link-detail.php?id=<?php echo $placement['link_id']; ?>" 
                                   class="text-purple-600 hover:text-purple-700 font-medium text-sm">
                                    <?php echo htmlspecialchars($placement['link_title']); ?>
                                </a>
                                <p class="text-xs text-gray-500 font-mono mt-1">/<?php echo htmlspecialchars($placement['link_slug']); ?></p>
                            </td>
                            
                            <!-- Placement Title -->
                            <td class="px-3 py-3">
                                <p class="text-sm text-gray-900 <?php echo $placement['status'] === 'removed' ? 'line-through text-gray-500' : ''; ?>">
                                    <?php echo htmlspecialchars($placement['title']); ?>
                                </p>
                            </td>
                            
                            <!-- Platform -->
                            <td class="px-3 py-3">
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
                            <td class="px-3 py-3">
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
                            <td class="px-3 py-3 text-center">
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
                            
                            <!-- Clicks (NEW) -->
                            <td class="px-3 py-3 text-right">
                                <a href="<?php echo TRACKER_PATH; ?>/analytics.php?link=<?php echo $placement['link_id']; ?>&group_by_ip=0" 
                                   class="text-sm font-bold text-blue-600 hover:text-blue-700 hover:underline transition-colors"
                                   title="View analytics for this link">
                                    <?php echo format_number($placement['link_clicks']); ?>
                                </a>
                            </td>
                            
                            <!-- Date -->
                            <td class="px-3 py-3 text-sm text-gray-600">
                                <?php echo date('M j, Y', strtotime($placement['date_added'])); ?>
                            </td>
                            
                            <!-- Actions -->
                            <td class="px-3 py-3">
                                <div class="flex items-center justify-center space-x-2">
                                    <a href="<?php echo TRACKER_PATH; ?>/placement-form.php?link_id=<?php echo $placement['link_id']; ?>&id=<?php echo $placement['id']; ?>" 
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
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                <div class="text-sm text-gray-600">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_placements); ?> of <?php echo $total_placements; ?> placements
                </div>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors text-sm">
                            Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg <?php echo $i === $page ? 'bg-purple-600 text-white border-purple-600' : 'hover:bg-gray-50'; ?> transition-colors text-sm">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors text-sm">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
<?php endif; ?>

<script>
function applyFilters() {
    const link = document.getElementById('link-filter').value;
    const platform = document.getElementById('platform-filter').value;
    
    let url = '?';
    if (link > 0) {
        url += 'link=' + link + '&';
    }
    if (platform) {
        url += 'platform=' + encodeURIComponent(platform) + '&';
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
</script>

<!-- Select Link Modal -->
<div id="select-link-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4 modal-backdrop">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-xl font-bold text-gray-900">Select Link</h3>
            <p class="text-gray-600 text-sm mt-1">Choose which link to add a placement for</p>
        </div>
        <div class="p-6">
            <select id="selected-link" 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                <option value="">-- Select a link --</option>
                <?php foreach ($all_links as $link): ?>
                    <option value="<?php echo $link['id']; ?>">
                        <?php echo htmlspecialchars($link['title']); ?> (/<?php echo htmlspecialchars($link['slug']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="p-6 pt-0 flex space-x-3">
            <button onclick="proceedToAddPlacement()" 
                    class="flex-1 bg-purple-600 text-white py-3 rounded-lg hover:bg-purple-700 transition-colors font-medium">
                Continue
            </button>
            <button onclick="closeAddPlacementModal()" 
                    class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
function openAddPlacementModal() {
    document.getElementById('select-link-modal').classList.remove('hidden');
    // Focus on dropdown
    setTimeout(() => {
        document.getElementById('selected-link').focus();
    }, 100);
}

function closeAddPlacementModal() {
    document.getElementById('select-link-modal').classList.add('hidden');
}

function proceedToAddPlacement() {
    const linkId = document.getElementById('selected-link').value;
    if (!linkId) {
        showToast('Please select a link', 'warning');
        return;
    }
    window.location.href = '<?php echo TRACKER_PATH; ?>/placement-form.php?link_id=' + linkId;
}

// Keyboard support
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('select-link-modal');
    if (!modal.classList.contains('hidden')) {
        if (e.key === 'Escape') {
            closeAddPlacementModal();
        }
        if (e.key === 'Enter' && document.activeElement.id === 'selected-link') {
            e.preventDefault();
            proceedToAddPlacement();
        }
    }
});

// Close modal when clicking outside
document.getElementById('select-link-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAddPlacementModal();
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

