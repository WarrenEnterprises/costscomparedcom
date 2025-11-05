<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Link Tracker'; ?> - Link Tracker Admin</title>
    
    <!-- Favicons with cache-busting -->
    <?php $favicon_version = '2'; // Increment this to force browser refresh ?>
    <link rel="icon" type="image/jpeg" href="/link-tracker/assets/favicon.jpg?v=<?php echo $favicon_version; ?>">
    <link rel="shortcut icon" type="image/jpeg" href="/link-tracker/assets/favicon.jpg?v=<?php echo $favicon_version; ?>">
    <link rel="apple-touch-icon" href="/link-tracker/assets/favicon.jpg?v=<?php echo $favicon_version; ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="<?php echo TRACKER_PATH; ?>/assets/style.css">
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.3s ease-out;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <!-- Mobile Menu Button -->
    <div class="lg:hidden fixed top-4 left-4 z-50">
        <button 
            id="mobile-menu-button"
            class="bg-white p-2 rounded-lg shadow-lg hover:shadow-xl transition-shadow"
            onclick="toggleMobileMenu()"
        >
            <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
    </div>
    
    <!-- Sidebar -->
    <div 
        id="sidebar"
        class="fixed left-0 top-0 h-full w-64 bg-gradient-to-b from-purple-700 to-indigo-800 text-white transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out z-40 shadow-2xl"
    >
        <!-- Logo -->
        <div class="p-6 border-b border-purple-600">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold">Link Tracker</h1>
                    <p class="text-xs text-purple-200">v<?php echo LT_VERSION; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Navigation -->
        <nav class="p-4 flex-1">
            <ul class="space-y-2">
                <li>
                    <a href="<?php echo TRACKER_PATH; ?>/dashboard.php" 
                       class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-purple-600 transition-colors <?php echo ($active_page === 'dashboard') ? 'bg-purple-600 shadow-lg' : ''; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        <span class="font-medium">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo TRACKER_PATH; ?>/links.php" 
                       class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-purple-600 transition-colors <?php echo ($active_page === 'links') ? 'bg-purple-600 shadow-lg' : ''; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                        </svg>
                        <span class="font-medium">Manage Links</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo TRACKER_PATH; ?>/analytics.php" 
                       class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-purple-600 transition-colors <?php echo ($active_page === 'analytics') ? 'bg-purple-600 shadow-lg' : ''; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <span class="font-medium">Analytics</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo TRACKER_PATH; ?>/placements.php" 
                       class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-purple-600 transition-colors <?php echo ($active_page === 'placements') ? 'bg-purple-600 shadow-lg' : ''; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="font-medium">Placements</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo TRACKER_PATH; ?>/settings.php" 
                       class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-purple-600 transition-colors <?php echo ($active_page === 'settings') ? 'bg-purple-600 shadow-lg' : ''; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span class="font-medium">Settings</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- User Info & Logout -->
        <div class="p-4 border-t border-purple-600">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                        <span class="text-sm font-bold"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                    </div>
                    <div>
                        <p class="text-sm font-medium"><?php echo htmlspecialchars($user['username']); ?></p>
                        <p class="text-xs text-purple-200">Admin</p>
                    </div>
                </div>
                <a href="<?php echo TRACKER_PATH; ?>/logout.php" 
                   class="p-2 hover:bg-purple-600 rounded-lg transition-colors"
                   title="Logout">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Overlay for mobile menu -->
    <div 
        id="sidebar-overlay"
        class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"
        onclick="toggleMobileMenu()"
    ></div>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="p-6 lg:p-8">
            
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo $page_title ?? 'Link Tracker'; ?></h1>
                <p class="text-gray-600"><?php echo date('l, F j, Y'); ?></p>
            </div>
            
            <!-- Toast Container -->
            <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
            
            <!-- Main Content Area -->
            <div class="animate-fade-in">

