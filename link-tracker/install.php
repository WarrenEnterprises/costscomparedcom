<?php
/**
 * Link Tracker Installation Script
 * 
 * This script sets up the database and creates the initial admin user
 * Run this once, then delete this file for security
 */

// Prevent running if already installed
if (file_exists(__DIR__ . '/.installed')) {
    die('Installation already completed. Delete .installed file to run again (not recommended in production).');
}

session_start();

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success = [];

// Step 1: Requirements Check
if ($step === 1) {
    $requirements = [
        'PHP Version >= 8.0' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
        'mbstring Extension' => extension_loaded('mbstring'),
        'JSON Extension' => extension_loaded('json'),
        'cURL Extension' => extension_loaded('curl'),
        'Config file writable' => is_writable(__DIR__ . '/config.php'),
    ];
    
    $all_passed = true;
    foreach ($requirements as $name => $passed) {
        if (!$passed) {
            $all_passed = false;
        }
    }
}

// Step 2: Database Configuration
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim($_POST['db_host'] ?? '');
    $db_name = trim($_POST['db_name'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass'] ?? '';
    $site_url = trim($_POST['site_url'] ?? '');
    
    if (empty($db_host) || empty($db_name) || empty($db_user)) {
        $errors[] = 'All database fields are required';
    }
    
    if (empty($site_url)) {
        $errors[] = 'Site URL is required';
    }
    
    if (empty($errors)) {
        // Test database connection
        try {
            $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Save to session for next step
            $_SESSION['install_config'] = [
                'db_host' => $db_host,
                'db_name' => $db_name,
                'db_user' => $db_user,
                'db_pass' => $db_pass,
                'site_url' => rtrim($site_url, '/')
            ];
            
            header('Location: install.php?step=3');
            exit;
            
        } catch (PDOException $e) {
            $errors[] = 'Database connection failed: ' . $e->getMessage();
        }
    }
}

// Step 3: Create Admin User
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    
    if (empty($errors)) {
        $_SESSION['admin_user'] = [
            'username' => $username,
            'password' => $password
        ];
        
        header('Location: install.php?step=4');
        exit;
    }
}

// Step 4: Install
if ($step === 4 && isset($_SESSION['install_config']) && isset($_SESSION['admin_user'])) {
    $config = $_SESSION['install_config'];
    $admin = $_SESSION['admin_user'];
    
    try {
        // Connect to database
        $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Read and execute schema
        $schema = file_get_contents(__DIR__ . '/schema.sql');
        
        // Execute each statement
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^--/', $statement)) {
                $pdo->exec($statement);
            }
        }
        
        $success[] = 'Database tables created successfully';
        
        // Create admin user
        $password_hash = password_hash($admin['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        
        $stmt = $pdo->prepare("INSERT INTO lt_users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$admin['username'], $password_hash]);
        
        $success[] = 'Admin user created successfully';
        
        // Update config.php
        $config_content = file_get_contents(__DIR__ . '/config.php');
        
        $replacements = [
            "'localhost'" => "'{$config['db_host']}'",
            "'your_database_name'" => "'{$config['db_name']}'",
            "'your_username'" => "'{$config['db_user']}'",
            "'your_password'" => "'{$config['db_pass']}'",
            "'https://yourdomain.com'" => "'{$config['site_url']}'"
        ];
        
        // Generate random salts
        $fingerprint_salt = bin2hex(random_bytes(32));
        $session_secret = bin2hex(random_bytes(32));
        $csrf_secret = bin2hex(random_bytes(32));
        
        $config_content = str_replace('CHANGE-THIS-TO-RANDOM-STRING-XYZ123', $fingerprint_salt, $config_content);
        $config_content = str_replace('CHANGE-THIS-TO-ANOTHER-RANDOM-STRING-ABC789', $session_secret, $config_content);
        $config_content = str_replace('CHANGE-THIS-TO-YET-ANOTHER-RANDOM-STRING-DEF456', $csrf_secret, $config_content);
        
        foreach ($replacements as $search => $replace) {
            $config_content = str_replace($search, $replace, $config_content);
        }
        
        file_put_contents(__DIR__ . '/config.php', $config_content);
        
        $success[] = 'Configuration file updated';
        
        // Create necessary directories
        $dirs = ['logs', 'cache', 'geoip'];
        foreach ($dirs as $dir) {
            $path = __DIR__ . '/' . $dir;
            if (!file_exists($path)) {
                mkdir($path, 0755, true);
                $success[] = "Created directory: $dir";
            }
        }
        
        // Create .installed file
        file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s'));
        
        $success[] = 'Installation completed!';
        
        // Clear session
        unset($_SESSION['install_config']);
        unset($_SESSION['admin_user']);
        
    } catch (Exception $e) {
        $errors[] = 'Installation failed: ' . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Tracker Installation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen py-12 px-4">
    
    <div class="max-w-3xl mx-auto">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-purple-600 rounded-full shadow-lg mb-4">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                </svg>
            </div>
            <h1 class="text-4xl font-bold text-gray-900 mb-2">Link Tracker</h1>
            <p class="text-gray-600">Installation Wizard</p>
        </div>
        
        <!-- Progress Steps -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between">
                <?php
                $steps_info = [
                    1 => 'Requirements',
                    2 => 'Database',
                    3 => 'Admin User',
                    4 => 'Install'
                ];
                
                foreach ($steps_info as $num => $name) {
                    $active = $step === $num;
                    $completed = $step > $num;
                    ?>
                    <div class="flex items-center <?php echo $num < 4 ? 'flex-1' : ''; ?>">
                        <div class="flex flex-col items-center">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold <?php echo $active ? 'bg-purple-600 text-white' : ($completed ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'); ?>">
                                <?php if ($completed): ?>
                                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                <?php else: ?>
                                    <?php echo $num; ?>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs mt-2 <?php echo $active ? 'text-purple-600 font-semibold' : 'text-gray-600'; ?>">
                                <?php echo $name; ?>
                            </span>
                        </div>
                        <?php if ($num < 4): ?>
                            <div class="flex-1 h-1 mx-2 <?php echo $completed ? 'bg-green-500' : 'bg-gray-200'; ?>"></div>
                        <?php endif; ?>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
        
        <!-- Content -->
        <div class="bg-white rounded-xl shadow-lg p-8">
            
            <?php if (!empty($errors)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                    <div class="flex">
                        <svg class="w-5 h-5 text-red-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <?php foreach ($errors as $error): ?>
                                <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
                    <div class="flex">
                        <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <?php foreach ($success as $msg): ?>
                                <p class="text-sm text-green-700"><?php echo htmlspecialchars($msg); ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($step === 1): ?>
                <!-- Step 1: Requirements Check -->
                <h2 class="text-2xl font-bold text-gray-900 mb-6">System Requirements</h2>
                
                <div class="space-y-4 mb-8">
                    <?php foreach ($requirements as $name => $passed): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <span class="font-medium text-gray-700"><?php echo $name; ?></span>
                            <?php if ($passed): ?>
                                <svg class="w-6 h-6 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            <?php else: ?>
                                <svg class="w-6 h-6 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($all_passed): ?>
                    <a href="install.php?step=2" class="block w-full bg-purple-600 text-white text-center py-3 px-6 rounded-lg hover:bg-purple-700 transition-colors font-medium">
                        Continue to Database Setup
                    </a>
                <?php else: ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-800">
                        Please fix the requirements above before continuing.
                    </div>
                <?php endif; ?>
                
            <?php elseif ($step === 2): ?>
                <!-- Step 2: Database Configuration -->
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Database Configuration</h2>
                
                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Database Host</label>
                        <input type="text" name="db_host" value="localhost" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <p class="mt-1 text-sm text-gray-500">Usually "localhost"</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Database Name</label>
                        <input type="text" name="db_name" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Database Username</label>
                        <input type="text" name="db_user" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Database Password</label>
                        <input type="password" name="db_pass"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <p class="mt-1 text-sm text-gray-500">Leave empty if no password</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Site URL</label>
                        <input type="url" name="site_url" value="<?php echo 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST']; ?>" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <p class="mt-1 text-sm text-gray-500">Your website URL (no trailing slash)</p>
                    </div>
                    
                    <button type="submit" class="w-full bg-purple-600 text-white py-3 px-6 rounded-lg hover:bg-purple-700 transition-colors font-medium">
                        Test Connection & Continue
                    </button>
                </form>
                
            <?php elseif ($step === 3): ?>
                <!-- Step 3: Create Admin User -->
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Create Admin User</h2>
                
                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                        <input type="text" name="username" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <input type="password" name="password" required minlength="8"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <p class="mt-1 text-sm text-gray-500">At least 8 characters</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                        <input type="password" name="confirm_password" required minlength="8"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    
                    <button type="submit" class="w-full bg-purple-600 text-white py-3 px-6 rounded-lg hover:bg-purple-700 transition-colors font-medium">
                        Create Admin & Install
                    </button>
                </form>
                
            <?php elseif ($step === 4): ?>
                <!-- Step 4: Installation Complete -->
                <?php if (empty($errors)): ?>
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-24 h-24 bg-green-100 rounded-full mb-6">
                            <svg class="w-12 h-12 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">Installation Complete!</h2>
                        <p class="text-gray-600 mb-8">Link Tracker has been successfully installed.</p>
                        
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6 text-left">
                            <h3 class="font-bold text-yellow-900 mb-2">⚠️ Important Security Steps:</h3>
                            <ol class="list-decimal list-inside space-y-2 text-sm text-yellow-800">
                                <li><strong>Delete this install.php file immediately</strong></li>
                                <li>Update the security salts in config.php if not already randomized</li>
                                <li>Ensure your .htaccess files are in place</li>
                                <li>Add the robots.txt entries to your existing robots.txt</li>
                            </ol>
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-left">
                            <h3 class="font-bold text-blue-900 mb-2">📋 Next Steps:</h3>
                            <ol class="list-decimal list-inside space-y-2 text-sm text-blue-800">
                                <li>Log in to your admin panel</li>
                                <li>Create your first tracked link</li>
                                <li>(Optional) Set up MaxMind GeoIP database for better location tracking</li>
                                <li>Test a tracked link to verify everything works</li>
                            </ol>
                        </div>
                        
                        <a href="link-tracking.php" class="inline-block bg-purple-600 text-white py-3 px-8 rounded-lg hover:bg-purple-700 transition-colors font-medium">
                            Go to Admin Login
                        </a>
                    </div>
                <?php else: ?>
                    <a href="install.php?step=1" class="block w-full bg-purple-600 text-white text-center py-3 px-6 rounded-lg hover:bg-purple-700 transition-colors font-medium">
                        Start Over
                    </a>
                <?php endif; ?>
                
            <?php endif; ?>
            
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-8 text-gray-600 text-sm">
            <p>Link Tracker v1.0.0</p>
        </div>
        
    </div>
    
</body>
</html>

