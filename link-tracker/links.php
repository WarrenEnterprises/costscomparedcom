<?php
/**
 * Links Management Page
 * CRUD interface for managing tracked links
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
$per_page = LINKS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// Search
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Get links
$where = '';
$params = [];

if (!empty($search)) {
    $where = " WHERE (l.title LIKE ? OR l.slug LIKE ? OR l.destination_url LIKE ?)";
    $search_term = "%{$search}%";
    $params = [$search_term, $search_term, $search_term];
}

try {
    // Count total links
    $sql = "SELECT COUNT(*) as count FROM " . table('links') . " l" . $where;
    $result = db_fetch($sql, $params);
    $total_links = $result['count'];
    $total_pages = ceil($total_links / $per_page);
    
    // Get links with click counts and placement counts
    $sql = "SELECT l.*, 
            COUNT(DISTINCT c.id) as total_clicks,
            SUM(c.is_unique) as unique_clicks,
            MAX(c.clicked_at) as last_click,
            COUNT(DISTINCT p.id) as placement_count
            FROM " . table('links') . " l
            LEFT JOIN " . table('clicks') . " c ON l.id = c.link_id
            LEFT JOIN " . table('placements') . " p ON l.id = p.link_id AND p.status = 'active'
            $where
            GROUP BY l.id
            ORDER BY l.created_at DESC
            LIMIT $per_page OFFSET $offset";
    
    $links = db_fetch_all($sql, $params);
    
} catch (Exception $e) {
    log_error('Failed to fetch links: ' . $e->getMessage());
    $links = [];
    $total_links = 0;
    $total_pages = 0;
}

$page_title = 'Manage Links';
$active_page = 'links';

include __DIR__ . '/includes/header.php';
?>

<!-- Page Actions -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
    
    <!-- Search Bar -->
    <div class="flex-1 max-w-md">
        <form method="GET" action="">
            <div class="relative">
                <input 
                    type="search" 
                    name="search" 
                    placeholder="Search links..."
                    value="<?php echo htmlspecialchars($search); ?>"
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                >
                <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
        </form>
    </div>
    
    <!-- Create Link Button -->
    <button 
        onclick="openCreateModal()"
        class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700 transition-all shadow-lg hover:shadow-xl transform hover:scale-105"
    >
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
        </svg>
        Create New Link
    </button>
    
</div>

<!-- Links Table -->
<?php if (empty($links)): ?>
    
    <div class="bg-white rounded-xl shadow-sm p-12 text-center border border-gray-100">
        <svg class="mx-auto h-24 w-24 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
        </svg>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">
            <?php echo !empty($search) ? 'No links found' : 'No links yet'; ?>
        </h3>
        <p class="text-gray-600 mb-6">
            <?php echo !empty($search) ? 'Try adjusting your search terms' : 'Create your first tracked link to get started'; ?>
        </p>
        <?php if (empty($search)): ?>
            <button 
                onclick="openCreateModal()"
                class="inline-flex items-center px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Create Your First Link
            </button>
        <?php endif; ?>
    </div>
    
<?php else: ?>
    
    <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Link</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Destination</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Clicks</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Unique</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">Placements</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($links as $link): ?>
                        <tr class="hover:bg-gray-50 transition-colors" id="link-row-<?php echo $link['id']; ?>">
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($link['title']); ?></p>
                                    <div class="flex items-center space-x-2 mt-1">
                                        <p class="text-sm text-gray-600">/best/<?php echo htmlspecialchars($link['slug']); ?></p>
                                        <button 
                                            onclick="copyToClipboard('<?php echo SITE_URL; ?>/best/<?php echo htmlspecialchars($link['slug']); ?>', this)"
                                            class="text-purple-600 hover:text-purple-700 transition-colors"
                                            title="Copy URL"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">
                                <p class="text-sm text-gray-600 truncate max-w-xs" title="<?php echo htmlspecialchars($link['destination_url']); ?>">
                                    <?php echo htmlspecialchars($link['destination_url']); ?>
                                </p>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <a href="<?php echo TRACKER_PATH; ?>/analytics.php?link=<?php echo $link['id']; ?>&group_by_ip=0" 
                                   class="text-lg font-bold text-blue-600 hover:text-blue-700 hover:underline transition-colors cursor-pointer"
                                   title="View analytics for this link">
                                    <?php echo format_number($link['total_clicks'] ?? 0); ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 text-center hidden sm:table-cell">
                                <span class="text-sm font-medium text-gray-700">
                                    <?php echo format_number($link['unique_clicks'] ?? 0); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center hidden lg:table-cell">
                                <a href="<?php echo TRACKER_PATH; ?>/link-detail.php?id=<?php echo $link['id']; ?>" 
                                   class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-lg <?php echo ($link['placement_count'] ?? 0) > 0 ? 'bg-purple-100 text-purple-800 hover:bg-purple-200' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?> transition-colors">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <?php echo $link['placement_count'] ?? 0; ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <label class="toggle-switch">
                                    <input 
                                        type="checkbox" 
                                        <?php echo $link['is_active'] ? 'checked' : ''; ?>
                                        onchange="toggleLink(<?php echo $link['id']; ?>, this)"
                                    >
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center space-x-2">
                                    <button 
                                        onclick="openEditModal(<?php echo htmlspecialchars(json_encode($link)); ?>)"
                                        class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                        title="Edit"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                    <button 
                                        onclick="deleteLink(<?php echo $link['id']; ?>, '<?php echo htmlspecialchars($link['title']); ?>')"
                                        class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                        title="Delete"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_links); ?> of <?php echo $total_links; ?> links
                </div>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg <?php echo $i === $page ? 'bg-purple-600 text-white border-purple-600' : 'hover:bg-gray-50'; ?> transition-colors">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
<?php endif; ?>

<!-- Create/Edit Modal -->
<div id="link-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4 modal-backdrop">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto animate-fade-in">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 id="modal-title" class="text-2xl font-bold text-gray-900">Create New Link</h2>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <form id="link-form" class="p-6 space-y-6">
            <input type="hidden" id="link-id" name="id">
            
            <!-- Title -->
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                    Title <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    id="title" 
                    name="title" 
                    required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    placeholder="e.g., Best Lawyer in Texas"
                >
                <p class="mt-1 text-sm text-gray-500">A descriptive name for your link</p>
            </div>
            
            <!-- Slug -->
            <div>
                <label for="slug" class="block text-sm font-medium text-gray-700 mb-2">
                    Slug <span class="text-red-500">*</span>
                </label>
                <div class="flex items-center">
                    <span class="text-gray-600 bg-gray-100 px-3 py-3 border border-r-0 border-gray-300 rounded-l-lg">/best/</span>
                    <input 
                        type="text" 
                        id="slug" 
                        name="slug" 
                        required
                        pattern="[a-z0-9-]+"
                        class="flex-1 px-4 py-3 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        placeholder="e.g., letslucky-casino"
                    >
                </div>
                <p class="mt-1 text-sm text-gray-500">Only lowercase letters, numbers, and hyphens</p>
            </div>
            
            <!-- Destination URL -->
            <div>
                <label for="destination_url" class="block text-sm font-medium text-gray-700 mb-2">
                    Destination URL <span class="text-red-500">*</span>
                </label>
                <input 
                    type="url" 
                    id="destination_url" 
                    name="destination_url" 
                    required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    placeholder="https://example.com/page"
                >
                <p class="mt-1 text-sm text-gray-500">Where visitors will be redirected</p>
            </div>
            
            <!-- Cloaking -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <label for="cloaking_enabled" class="font-medium text-gray-900">Enable Cloaking</label>
                        <p class="text-sm text-gray-600">Show different URL to bots/crawlers</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="cloaking_enabled" name="cloaking_enabled" onchange="toggleCloakingUrl()">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                
                <div id="cloaking-url-field" class="hidden">
                    <label for="cloaking_url" class="block text-sm font-medium text-gray-700 mb-2">
                        Cloaking URL for Bots
                    </label>
                    <input 
                        type="url" 
                        id="cloaking_url" 
                        name="cloaking_url"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        placeholder="https://example.com/public-page"
                    >
                    <p class="mt-1 text-sm text-gray-500">Bots will be redirected here instead</p>
                </div>
            </div>
            
            <!-- Submit Buttons -->
            <div class="flex space-x-3 pt-4">
                <button 
                    type="submit"
                    class="flex-1 bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-3 px-6 rounded-lg hover:from-purple-700 hover:to-indigo-700 transition-all font-medium"
                >
                    <span id="submit-text">Create Link</span>
                </button>
                <button 
                    type="button"
                    onclick="closeModal()"
                    class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors font-medium"
                >
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let editMode = false;

// Toggle cloaking URL field
function toggleCloakingUrl() {
    const enabled = document.getElementById('cloaking_enabled').checked;
    const field = document.getElementById('cloaking-url-field');
    
    if (enabled) {
        field.classList.remove('hidden');
        document.getElementById('cloaking_url').required = true;
    } else {
        field.classList.add('hidden');
        document.getElementById('cloaking_url').required = false;
    }
}

// Open create modal
function openCreateModal() {
    editMode = false;
    document.getElementById('modal-title').textContent = 'Create New Link';
    document.getElementById('submit-text').textContent = 'Create Link';
    document.getElementById('link-form').reset();
    document.getElementById('link-id').value = '';
    document.getElementById('cloaking-url-field').classList.add('hidden');
    document.getElementById('link-modal').classList.remove('hidden');
}

// Open edit modal
function openEditModal(link) {
    editMode = true;
    document.getElementById('modal-title').textContent = 'Edit Link';
    document.getElementById('submit-text').textContent = 'Update Link';
    document.getElementById('link-id').value = link.id;
    document.getElementById('title').value = link.title;
    document.getElementById('slug').value = link.slug;
    document.getElementById('destination_url').value = link.destination_url;
    document.getElementById('cloaking_enabled').checked = link.cloaking_enabled == 1;
    document.getElementById('cloaking_url').value = link.cloaking_url || '';
    
    toggleCloakingUrl();
    document.getElementById('link-modal').classList.remove('hidden');
}

// Close modal
function closeModal() {
    document.getElementById('link-modal').classList.add('hidden');
}

// Submit form
document.getElementById('link-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const linkId = document.getElementById('link-id').value;
    
    const data = {
        id: linkId,
        title: document.getElementById('title').value,
        slug: document.getElementById('slug').value,
        destination_url: document.getElementById('destination_url').value,
        cloaking_enabled: document.getElementById('cloaking_enabled').checked ? 1 : 0,
        cloaking_url: document.getElementById('cloaking_url').value
    };
    
    setButtonLoading(submitBtn, true);
    
    const endpoint = linkId ? '<?php echo TRACKER_PATH; ?>/ajax/edit-link.php' : '<?php echo TRACKER_PATH; ?>/ajax/create-link.php';
    const response = await makeRequest(endpoint, 'POST', data);
    
    setButtonLoading(submitBtn, false);
    
    if (response.success) {
        showToast(response.message, 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast(response.message, 'error');
    }
});

// Toggle link active status
async function toggleLink(linkId, checkbox) {
    const isActive = checkbox.checked ? 1 : 0;
    
    const response = await makeRequest('<?php echo TRACKER_PATH; ?>/ajax/toggle-link.php', 'POST', {
        id: linkId,
        is_active: isActive
    });
    
    if (response.success) {
        showToast(response.message, 'success');
    } else {
        showToast(response.message, 'error');
        checkbox.checked = !checkbox.checked;
    }
}

// Delete link
async function deleteLink(linkId, title) {
    if (!confirmAction(`Are you sure you want to delete "${title}"? This will also delete all associated click data.`)) {
        return;
    }
    
    const response = await makeRequest('<?php echo TRACKER_PATH; ?>/ajax/delete-link.php', 'POST', {
        id: linkId
    });
    
    if (response.success) {
        showToast(response.message, 'success');
        document.getElementById('link-row-' + linkId).style.opacity = '0';
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast(response.message, 'error');
    }
}

// Close modal on ESC key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// Close modal on backdrop click
document.getElementById('link-modal').addEventListener('click', (e) => {
    if (e.target.id === 'link-modal') {
        closeModal();
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

