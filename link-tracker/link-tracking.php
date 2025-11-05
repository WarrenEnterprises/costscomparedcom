<?php
/**
 * Login Page
 * Secure admin login with brute force protection
 */

define('LT_INIT', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

start_session();

// Check if already logged in
$user = validate_session();
if ($user) {
    redirect(TRACKER_PATH . '/dashboard.php');
}

// Handle login form submission
$error = '';
$captcha_required = false;
$blocked = false;
$block_time = 0;
$attempts_remaining = MAX_LOGIN_ATTEMPTS_BEFORE_BLOCK;

$ip = get_client_ip();

// Check if blocked
$block_info = check_login_block($ip);
if ($block_info['blocked']) {
    $blocked = true;
    $block_time = $block_info['remaining'];
}

// Check if CAPTCHA is required
$recent_attempts = get_recent_login_attempts($ip);
if ($recent_attempts >= MAX_LOGIN_ATTEMPTS_BEFORE_CAPTCHA) {
    $captcha_required = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (!empty($username) && !empty($password)) {
        $result = attempt_login($username, $password, $remember);
        
        if ($result['success']) {
            redirect($result['redirect']);
        } else {
            $error = $result['message'];
            
            if (isset($result['blocked']) && $result['blocked']) {
                $blocked = true;
                $block_time = $result['remaining'];
            }
            
            if (isset($result['captcha_required'])) {
                $captcha_required = $result['captcha_required'];
            }
            
            if (isset($result['attempts_remaining'])) {
                $attempts_remaining = $result['attempts_remaining'];
            }
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Tracker - Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-slide-up {
            animation: slideUp 0.5s ease-out;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center px-4 py-12">
    
    <div class="max-w-md w-full animate-slide-up">
        <!-- Logo/Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-full shadow-lg mb-4">
                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Link Tracker</h1>
            <p class="text-purple-100">Sign in to your admin dashboard</p>
        </div>
        
        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            
            <?php if ($blocked): ?>
                <!-- Blocked Message -->
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-red-500 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <h3 class="text-sm font-medium text-red-800">Account Temporarily Locked</h3>
                            <p class="mt-1 text-sm text-red-700">
                                Too many failed login attempts. Please try again in 
                                <strong><span id="countdown"><?php echo format_block_time($block_time); ?></span></strong>.
                            </p>
                        </div>
                    </div>
                </div>
                
                <script>
                    let remaining = <?php echo $block_time; ?>;
                    const countdown = document.getElementById('countdown');
                    
                    const interval = setInterval(() => {
                        remaining--;
                        
                        if (remaining <= 0) {
                            clearInterval(interval);
                            location.reload();
                        } else {
                            countdown.textContent = formatTime(remaining);
                        }
                    }, 1000);
                    
                    function formatTime(seconds) {
                        if (seconds >= 3600) {
                            return Math.ceil(seconds / 3600) + ' hour(s)';
                        } else if (seconds >= 60) {
                            return Math.ceil(seconds / 60) + ' minute(s)';
                        } else {
                            return seconds + ' second(s)';
                        }
                    }
                </script>
                
            <?php else: ?>
                
                <?php if ($error): ?>
                    <!-- Error Message -->
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-red-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                        <?php if ($attempts_remaining > 0 && $attempts_remaining < MAX_LOGIN_ATTEMPTS_BEFORE_BLOCK): ?>
                            <p class="text-xs text-red-600 mt-2 ml-8">
                                <?php echo $attempts_remaining; ?> attempt(s) remaining before temporary lockout.
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Login Form -->
                <form method="POST" action="" class="space-y-6">
                    
                    <!-- Username Field -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            Username
                        </label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            required
                            autofocus
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                            placeholder="Enter your username"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        >
                    </div>
                    
                    <!-- Password Field -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Password
                        </label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                            placeholder="Enter your password"
                        >
                    </div>
                    
                    <!-- CAPTCHA (shown after 3 failed attempts) -->
                    <?php if ($captcha_required): ?>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <p class="text-sm text-yellow-800 mb-3">
                                <strong>Security Check Required</strong><br>
                                Please verify you're not a robot by solving this simple math problem:
                            </p>
                            <?php
                                $num1 = rand(1, 10);
                                $num2 = rand(1, 10);
                                $_SESSION['captcha_answer'] = $num1 + $num2;
                            ?>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                What is <?php echo $num1; ?> + <?php echo $num2; ?>?
                            </label>
                            <input 
                                type="number" 
                                name="captcha" 
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                placeholder="Your answer"
                            >
                        </div>
                    <?php endif; ?>
                    
                    <!-- Remember Me -->
                    <div class="flex items-center">
                        <input 
                            type="checkbox" 
                            id="remember" 
                            name="remember" 
                            class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded"
                        >
                        <label for="remember" class="ml-2 block text-sm text-gray-700">
                            Remember me for 30 days
                        </label>
                    </div>
                    
                    <!-- Submit Button -->
                    <button 
                        type="submit"
                        class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-3 px-4 rounded-lg font-medium hover:from-purple-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-all transform hover:scale-[1.02] active:scale-[0.98]"
                    >
                        Sign In
                    </button>
                    
                </form>
            <?php endif; ?>
            
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-6 text-purple-100 text-sm">
            <p>Link Tracker v<?php echo LT_VERSION; ?></p>
        </div>
        
    </div>
    
</body>
</html>

