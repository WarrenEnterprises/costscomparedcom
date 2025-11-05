<?php
/**
 * Settings Page
 * Admin settings, password change, data management
 */

define('LT_INIT', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

start_session();
$user = require_auth();

$success = '';
$error = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters';
    } else {
        try {
            // Verify current password
            $sql = "SELECT * FROM " . table('users') . " WHERE id = ?";
            $user_data = db_fetch($sql, [$user['user_id']]);
            
            if (!verify_password($current_password, $user_data['password_hash'])) {
                $error = 'Current password is incorrect';
            } else {
                // Update password
                if (update_password($user['user_id'], $new_password)) {
                    $success = 'Password changed successfully!';
                } else {
                    $error = 'Failed to change password';
                }
            }
        } catch (Exception $e) {
            log_error('Password change error: ' . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}

// Get database stats
try {
    $sql = "SELECT 
            (SELECT COUNT(*) FROM " . table('links') . ") as total_links,
            (SELECT COUNT(*) FROM " . table('clicks') . ") as total_clicks,
            (SELECT COUNT(*) FROM " . table('sessions') . ") as total_sessions";
    $db_stats = db_fetch($sql);
    
    // Get table sizes (MySQL specific)
    $sql = "SELECT 
            table_name as 'table',
            ROUND(((data_length + index_length) / 1024 / 1024), 2) as 'size_mb'
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE()
            AND table_name LIKE '" . DB_PREFIX . "%'
            ORDER BY (data_length + index_length) DESC";
    $table_sizes = db_fetch_all($sql);
    
} catch (Exception $e) {
    log_error('Failed to fetch database stats: ' . $e->getMessage());
    $db_stats = ['total_links' => 0, 'total_clicks' => 0, 'total_sessions' => 0];
    $table_sizes = [];
}

$page_title = 'Settings';
$active_page = 'settings';

include __DIR__ . '/includes/header.php';
?>

<?php if ($success): ?>
    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6" data-auto-dismiss>
        <div class="flex items-center">
            <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <p class="text-sm text-green-700"><?php echo htmlspecialchars($success); ?></p>
        </div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6" data-auto-dismiss>
        <div class="flex items-center">
            <svg class="w-5 h-5 text-red-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    
    <!-- Change Password -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Change Password</h2>
        
        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="action" value="change_password">
            
            <div>
                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">
                    Current Password
                </label>
                <input 
                    type="password" 
                    id="current_password" 
                    name="current_password" 
                    required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                >
            </div>
            
            <div>
                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                    New Password
                </label>
                <input 
                    type="password" 
                    id="new_password" 
                    name="new_password" 
                    required
                    minlength="8"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                >
                <p class="mt-1 text-sm text-gray-500">At least 8 characters</p>
            </div>
            
            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                    Confirm New Password
                </label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    required
                    minlength="8"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                >
            </div>
            
            <button 
                type="submit"
                class="w-full bg-purple-600 text-white py-3 px-6 rounded-lg hover:bg-purple-700 transition-colors font-medium"
            >
                Update Password
            </button>
        </form>
    </div>
    
    <!-- Database Statistics -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Database Statistics</h2>
        
        <div class="space-y-4">
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Links</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo format_number($db_stats['total_links']); ?></p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                    </svg>
                </div>
            </div>
            
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Clicks Recorded</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo format_number($db_stats['total_clicks']); ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path>
                    </svg>
                </div>
            </div>
            
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div>
                    <p class="text-sm font-medium text-gray-600">Active Sessions</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo format_number($db_stats['total_sessions']); ?></p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <?php if (!empty($table_sizes)): ?>
            <div class="mt-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Table Sizes</h3>
                <div class="space-y-2">
                    <?php foreach ($table_sizes as $table): ?>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600"><?php echo htmlspecialchars($table['table']); ?></span>
                            <span class="font-medium text-gray-900"><?php echo $table['size_mb']; ?> MB</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
</div>

<!-- Danger Zone -->
<div class="bg-red-50 border border-red-200 rounded-xl p-6 mt-8">
    <h2 class="text-xl font-bold text-red-900 mb-4 flex items-center">
        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        Danger Zone
    </h2>
    <p class="text-red-800 mb-6">
        These actions are permanent and cannot be undone. Please proceed with caution.
    </p>
    
    <div class="bg-white rounded-lg p-6 border border-red-300">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Clear All Click Data</h3>
        <p class="text-gray-600 mb-4">
            This will delete <strong>all <?php echo format_number($db_stats['total_clicks']); ?> click records</strong> from the database. 
            Your links will remain intact, but all tracking data will be permanently removed.
        </p>
        <button 
            onclick="openClearDataModal()"
            class="inline-flex items-center px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium"
        >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
            </svg>
            Clear All Data
        </button>
    </div>
</div>

<!-- Clear Data Modal -->
<div id="clear-data-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4 modal-backdrop">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-fade-in">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center text-red-600 mb-2">
                <svg class="w-8 h-8 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <h2 class="text-2xl font-bold text-gray-900">Confirm Data Deletion</h2>
            </div>
        </div>
        
        <div class="p-6">
            <p class="text-gray-700 mb-4">
                You are about to delete <strong class="text-red-600"><?php echo format_number($db_stats['total_clicks']); ?> click records</strong>.
            </p>
            <p class="text-gray-700 mb-6">
                This action <strong>cannot be undone</strong>. Please enter your password to confirm.
            </p>
            
            <form id="clear-data-form" class="space-y-4">
                <div>
                    <label for="confirm_password_clear" class="block text-sm font-medium text-gray-700 mb-2">
                        Your Password
                    </label>
                    <input 
                        type="password" 
                        id="confirm_password_clear" 
                        name="password" 
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                        placeholder="Enter your password"
                    >
                </div>
                
                <div class="flex space-x-3">
                    <button 
                        type="submit"
                        class="flex-1 bg-red-600 text-white py-3 px-6 rounded-lg hover:bg-red-700 transition-colors font-medium"
                    >
                        Yes, Delete All Data
                    </button>
                    <button 
                        type="button"
                        onclick="closeClearDataModal()"
                        class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors font-medium"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openClearDataModal() {
    document.getElementById('clear-data-modal').classList.remove('hidden');
}

function closeClearDataModal() {
    document.getElementById('clear-data-modal').classList.add('hidden');
    document.getElementById('clear-data-form').reset();
}

document.getElementById('clear-data-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const password = document.getElementById('confirm_password_clear').value;
    const submitBtn = e.target.querySelector('button[type="submit"]');
    
    if (!confirmAction('Are you ABSOLUTELY SURE? This will permanently delete all click data!')) {
        return;
    }
    
    setButtonLoading(submitBtn, true);
    
    const response = await makeRequest('<?php echo TRACKER_PATH; ?>/ajax/clear-data.php', 'POST', {
        password: password
    });
    
    setButtonLoading(submitBtn, false);
    
    if (response.success) {
        showToast(response.message, 'success');
        closeClearDataModal();
        setTimeout(() => location.reload(), 2000);
    } else {
        showToast(response.message, 'error');
    }
});

// Close modal on ESC key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeClearDataModal();
    }
});

// Close modal on backdrop click
document.getElementById('clear-data-modal').addEventListener('click', (e) => {
    if (e.target.id === 'clear-data-modal') {
        closeClearDataModal();
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

