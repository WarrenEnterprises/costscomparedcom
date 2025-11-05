<?php
/**
 * Placement Add/Edit Form
 * Mobile-friendly form for managing link placements
 */

define('LT_INIT', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

start_session();
$user = require_auth();

// Get link ID
$link_id = isset($_GET['link_id']) ? (int)$_GET['link_id'] : 0;
$placement_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($link_id <= 0) {
    redirect(TRACKER_PATH . '/links.php');
}

// Get link data
try {
    $sql = "SELECT * FROM " . table('links') . " WHERE id = ?";
    $link = db_fetch($sql, [$link_id]);
    
    if (!$link) {
        redirect(TRACKER_PATH . '/links.php');
    }
} catch (Exception $e) {
    log_error('Failed to fetch link: ' . $e->getMessage());
    redirect(TRACKER_PATH . '/links.php');
}

// Get placement data if editing
$placement = null;
if ($placement_id > 0) {
    try {
        $sql = "SELECT * FROM " . table('placements') . " WHERE id = ? AND link_id = ?";
        $placement = db_fetch($sql, [$placement_id, $link_id]);
        
        if (!$placement) {
            redirect(TRACKER_PATH . '/link-detail.php?id=' . $link_id);
        }
    } catch (Exception $e) {
        log_error('Failed to fetch placement: ' . $e->getMessage());
        redirect(TRACKER_PATH . '/link-detail.php?id=' . $link_id);
    }
}

$is_edit = $placement !== null;

$page_title = $is_edit ? 'Edit Placement' : 'Add New Placement';
$active_page = 'links';

include __DIR__ . '/includes/header.php';
?>

<div class="max-w-3xl mx-auto">
    
    <!-- Back Navigation -->
    <div class="mb-6">
        <a href="<?php echo TRACKER_PATH; ?>/link-detail.php?id=<?php echo $link_id; ?>" 
           class="inline-flex items-center text-purple-600 hover:text-purple-700 transition-colors">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Link Details
        </a>
    </div>
    
    <!-- Link Info -->
    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-6">
        <div class="flex items-start space-x-3">
            <svg class="w-5 h-5 text-purple-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
            </svg>
            <div>
                <h3 class="font-medium text-purple-900"><?php echo htmlspecialchars($link['title']); ?></h3>
                <p class="text-sm text-purple-700 font-mono"><?php echo SITE_URL; ?>/best/<?php echo htmlspecialchars($link['slug']); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Form Card -->
    <div class="bg-white rounded-xl shadow-lg border border-gray-100">
        
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-2xl font-bold text-gray-900">
                <?php echo $is_edit ? 'Edit' : 'Add New'; ?> Placement
            </h2>
            <p class="text-gray-600 mt-1">Track where this redirect link is posted</p>
        </div>
        
        <form id="placement-form" class="p-6 space-y-6">
            <input type="hidden" name="link_id" value="<?php echo $link_id; ?>">
            <?php if ($is_edit): ?>
                <input type="hidden" name="placement_id" value="<?php echo $placement_id; ?>">
            <?php endif; ?>
            
            <!-- Title/Description -->
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                    Title / Description <span class="text-red-500">*</span>
                </label>
                <input type="text" 
                       id="title" 
                       name="title" 
                       required
                       maxlength="500"
                       value="<?php echo $is_edit ? htmlspecialchars($placement['title']) : ''; ?>"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                       placeholder="e.g., Reddit thread about best online casinos in UK">
                <p class="mt-1 text-xs text-gray-500">Describe where this link is posted (max 500 characters)</p>
            </div>
            
            <!-- URL -->
            <div>
                <label for="url" class="block text-sm font-medium text-gray-700 mb-2">
                    URL <span class="text-red-500">*</span>
                </label>
                <input type="url" 
                       id="url" 
                       name="url" 
                       required
                       value="<?php echo $is_edit ? htmlspecialchars($placement['url']) : ''; ?>"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm font-mono"
                       placeholder="https://reddit.com/r/gambling/comments/...">
                <p class="mt-1 text-xs text-gray-500">Full URL where this link is placed</p>
                <div id="url-warning" class="hidden mt-3 bg-yellow-50 border-l-4 border-yellow-400 p-4">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-yellow-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <div class="flex-1">
                            <h4 class="text-sm font-medium text-yellow-800">Warning: Previous Removal Detected</h4>
                            <p class="mt-1 text-sm text-yellow-700" id="warning-message"></p>
                            <div class="mt-3">
                                <label class="flex items-center">
                                    <input type="checkbox" id="acknowledge-risk" class="mr-2 h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                                    <span class="text-sm text-yellow-800">I understand the risk and want to add this placement anyway</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Star Rating -->
            <div>
                <label for="star_rating" class="block text-sm font-medium text-gray-700 mb-2">
                    Importance / Quality Rating <span class="text-red-500">*</span>
                </label>
                <select id="star_rating" 
                        name="star_rating" 
                        required
                        onchange="updateStarPreview()"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                    <option value="1" <?php echo ($is_edit && $placement['star_rating'] == 1) ? 'selected' : ''; ?>>⭐ 1 Star - Low Priority</option>
                    <option value="2" <?php echo ($is_edit && $placement['star_rating'] == 2) ? 'selected' : ''; ?>>⭐⭐ 2 Stars - Below Average</option>
                    <option value="3" <?php echo (!$is_edit || $placement['star_rating'] == 3) ? 'selected' : ''; ?>>⭐⭐⭐ 3 Stars - Average</option>
                    <option value="4" <?php echo ($is_edit && $placement['star_rating'] == 4) ? 'selected' : ''; ?>>⭐⭐⭐⭐ 4 Stars - Good</option>
                    <option value="5" <?php echo ($is_edit && $placement['star_rating'] == 5) ? 'selected' : ''; ?>>⭐⭐⭐⭐⭐ 5 Stars - Excellent</option>
                </select>
                <p class="mt-1 text-xs text-gray-500">Rate the importance or quality of this placement</p>
            </div>
            
            <!-- Platform -->
            <div>
                <label for="platform" class="block text-sm font-medium text-gray-700 mb-2">
                    Platform
                </label>
                <select id="platform" 
                        name="platform"
                        onchange="toggleCustomPlatform()"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                    <option value="">Not specified</option>
                    <option value="Reddit" <?php echo ($is_edit && $placement['platform'] === 'Reddit') ? 'selected' : ''; ?>>Reddit</option>
                    <option value="Quora" <?php echo ($is_edit && $placement['platform'] === 'Quora') ? 'selected' : ''; ?>>Quora</option>
                    <option value="Twitter/X" <?php echo ($is_edit && $placement['platform'] === 'Twitter/X') ? 'selected' : ''; ?>>Twitter/X</option>
                    <option value="YouTube" <?php echo ($is_edit && $placement['platform'] === 'YouTube') ? 'selected' : ''; ?>>YouTube</option>
                    <option value="TikTok" <?php echo ($is_edit && $placement['platform'] === 'TikTok') ? 'selected' : ''; ?>>TikTok</option>
                    <option value="Facebook" <?php echo ($is_edit && $placement['platform'] === 'Facebook') ? 'selected' : ''; ?>>Facebook</option>
                    <option value="Medium" <?php echo ($is_edit && $placement['platform'] === 'Medium') ? 'selected' : ''; ?>>Medium</option>
                    <option value="LinkedIn" <?php echo ($is_edit && $placement['platform'] === 'LinkedIn') ? 'selected' : ''; ?>>LinkedIn</option>
                    <option value="Other" <?php echo ($is_edit && $placement['platform'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
                
                <div id="custom-platform-field" class="mt-3 <?php echo (!$is_edit || $placement['platform'] !== 'Other') ? 'hidden' : ''; ?>">
                    <input type="text" 
                           id="platform_custom" 
                           name="platform_custom"
                           maxlength="100"
                           value="<?php echo $is_edit ? htmlspecialchars($placement['platform_custom'] ?? '') : ''; ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                           placeholder="Enter custom platform name">
                </div>
            </div>
            
            <!-- Status -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">Status</label>
                <div class="space-y-2">
                    <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                        <input type="radio" 
                               name="status" 
                               value="active" 
                               <?php echo (!$is_edit || $placement['status'] === 'active') ? 'checked' : ''; ?>
                               class="h-4 w-4 text-purple-600 focus:ring-purple-500">
                        <div class="ml-3">
                            <span class="text-sm font-medium text-gray-900">Active</span>
                            <p class="text-xs text-gray-500">Link is currently posted and accessible</p>
                        </div>
                    </label>
                    
                    <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                        <input type="radio" 
                               name="status" 
                               value="removed" 
                               <?php echo ($is_edit && $placement['status'] === 'removed') ? 'checked' : ''; ?>
                               class="h-4 w-4 text-purple-600 focus:ring-purple-500">
                        <div class="ml-3">
                            <span class="text-sm font-medium text-gray-900">Removed</span>
                            <p class="text-xs text-gray-500">Link was removed from this location</p>
                        </div>
                    </label>
                    
                    <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                        <input type="radio" 
                               name="status" 
                               value="broken" 
                               <?php echo ($is_edit && $placement['status'] === 'broken') ? 'checked' : ''; ?>
                               class="h-4 w-4 text-purple-600 focus:ring-purple-500">
                        <div class="ml-3">
                            <span class="text-sm font-medium text-gray-900">Broken</span>
                            <p class="text-xs text-gray-500">URL returns 404 or is no longer accessible</p>
                        </div>
                    </label>
                </div>
            </div>
            
            <!-- Notes -->
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                    Notes (Optional)
                </label>
                <textarea id="notes" 
                          name="notes" 
                          rows="4"
                          maxlength="1000"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                          placeholder="Additional context about this placement..."><?php echo $is_edit ? htmlspecialchars($placement['notes'] ?? '') : ''; ?></textarea>
                <p class="mt-1 text-xs text-gray-500">Any additional information about this placement (max 1000 characters)</p>
            </div>
            
            <!-- Submit Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200">
                <button type="submit" 
                        id="submit-btn"
                        class="flex-1 sm:flex-none px-8 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700 transition-all font-medium shadow-lg hover:shadow-xl">
                    <?php echo $is_edit ? 'Update' : 'Save'; ?> Placement
                </button>
                <a href="<?php echo TRACKER_PATH; ?>/link-detail.php?id=<?php echo $link_id; ?>" 
                   class="flex-1 sm:flex-none px-8 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors font-medium text-center">
                    Cancel
                </a>
            </div>
            
        </form>
        
    </div>
    
</div>

<style>
/* Mobile optimizations */
@media (max-width: 640px) {
    form input[type="text"],
    form input[type="url"],
    form textarea,
    form select {
        font-size: 16px; /* Prevents zoom on iOS */
    }
}
</style>

<script>
function updateStarPreview() {
    // Optional: Can add visual feedback if needed
    const rating = document.getElementById('star_rating').value;
    console.log('Rating selected:', rating);
}

function toggleCustomPlatform() {
    const platform = document.getElementById('platform').value;
    const customField = document.getElementById('custom-platform-field');
    const customInput = document.getElementById('platform_custom');
    
    if (platform === 'Other') {
        customField.classList.remove('hidden');
        customInput.required = true;
    } else {
        customField.classList.add('hidden');
        customInput.required = false;
    }
}

// Check for duplicate URL and removal warnings
document.getElementById('url').addEventListener('blur', async function() {
    const url = this.value.trim();
    
    if (!url || !isValidUrl(url)) {
        return;
    }
    
    const response = await makeRequest('<?php echo TRACKER_PATH; ?>/ajax/check-placement-url.php', 'POST', {
        link_id: <?php echo $link_id; ?>,
        url: url,
        <?php if ($is_edit): ?>placement_id: <?php echo $placement_id; ?><?php endif; ?>
    });
    
    const warningDiv = document.getElementById('url-warning');
    const warningMsg = document.getElementById('warning-message');
    
    if (response.duplicate) {
        warningDiv.classList.remove('hidden');
        warningDiv.classList.remove('bg-yellow-50', 'border-yellow-400');
        warningDiv.classList.add('bg-red-50', 'border-red-400');
        warningMsg.innerHTML = '<strong>Error:</strong> This URL is already tracked for this link.';
        warningMsg.classList.add('text-red-700');
        document.getElementById('submit-btn').disabled = true;
    } else if (response.removed_warning) {
        warningDiv.classList.remove('hidden');
        warningDiv.classList.remove('bg-red-50', 'border-red-400');
        warningDiv.classList.add('bg-yellow-50', 'border-yellow-400');
        warningMsg.innerHTML = response.warning_message;
        warningMsg.classList.add('text-yellow-700');
        document.getElementById('submit-btn').disabled = false;
        document.getElementById('acknowledge-risk').parentElement.parentElement.classList.remove('hidden');
    } else {
        warningDiv.classList.add('hidden');
        document.getElementById('submit-btn').disabled = false;
    }
});

// Form submission
document.getElementById('placement-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });
    
    // Check if warning requires acknowledgment
    const warningDiv = document.getElementById('url-warning');
    const acknowledgeCheckbox = document.getElementById('acknowledge-risk');
    
    if (!warningDiv.classList.contains('hidden') && 
        !warningDiv.classList.contains('bg-red-50') && 
        !acknowledgeCheckbox.checked) {
        showToast('Please acknowledge the warning or change the URL', 'warning');
        return;
    }
    
    const submitBtn = document.getElementById('submit-btn');
    setButtonLoading(submitBtn, true);
    
    const endpoint = <?php echo $is_edit ? 'true' : 'false'; ?> 
        ? '<?php echo TRACKER_PATH; ?>/ajax/edit-placement.php' 
        : '<?php echo TRACKER_PATH; ?>/ajax/create-placement.php';
    
    const response = await makeRequest(endpoint, 'POST', data);
    
    setButtonLoading(submitBtn, false);
    
    if (response.success) {
        showToast(response.message, 'success');
        setTimeout(() => {
            window.location.href = '<?php echo TRACKER_PATH; ?>/link-detail.php?id=<?php echo $link_id; ?>';
        }, 1000);
    } else {
        showToast(response.message || 'An error occurred', 'error');
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

