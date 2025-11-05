<?php
/**
 * EMERGENCY ADMIN PASSWORD RESET
 * Upload this file to /public_html/ and visit it once
 * It will reset your admin credentials and then delete itself
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'u609212978_costscompared');
define('DB_USER', 'u609212978_costscomparedu');
define('DB_PASS', '&erAFQRDJ&BRcBnR%w5M*L');

// New admin credentials
$new_username = 'Links';
$new_password = 'VerySecretTesting888**!!';

echo "<!DOCTYPE html><html><head><title>Admin Reset</title><style>
body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
.box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.success { color: #28a745; font-weight: bold; font-size: 18px; }
.error { color: #dc3545; font-weight: bold; }
.info { color: #666; margin: 10px 0; }
.credentials { background: #f8f9fa; padding: 15px; margin: 15px 0; border-left: 4px solid #28a745; }
</style></head><body><div class='box'>";

echo "<h2>🔧 Emergency Admin Reset - costscompared.com</h2>";

try {
    // Connect to database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "<p class='info'>✓ Connected to database</p>";
    
    // Generate password hash
    $password_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    echo "<p class='info'>✓ Password hash generated</p>";
    
    // Update admin user
    $stmt = $pdo->prepare("
        UPDATE lt_users 
        SET username = :username, 
            password_hash = :password_hash,
            last_login = NULL
        WHERE id = 1
    ");
    
    $stmt->execute([
        'username' => $new_username,
        'password_hash' => $password_hash
    ]);
    
    echo "<p class='info'>✓ Admin credentials updated</p>";
    
    // Clear ALL login attempts (unlock account)
    $pdo->exec("TRUNCATE TABLE lt_login_attempts");
    
    echo "<p class='info'>✓ All login attempts cleared (account unlocked)</p>";
    
    // Success message
    echo "<p class='success'>✅ ADMIN RESET SUCCESSFUL!</p>";
    
    echo "<div class='credentials'>";
    echo "<strong>New Login Credentials:</strong><br>";
    echo "Username: <code>{$new_username}</code><br>";
    echo "Password: <code>{$new_password}</code><br><br>";
    echo "<strong>Login URL:</strong><br>";
    echo "<a href='https://costscompared.com/link-tracker/link-tracking.php'>https://costscompared.com/link-tracker/link-tracking.php</a>";
    echo "</div>";
    
    echo "<p class='info'>⚠️ This file will now delete itself for security...</p>";
    
    // Delete this file for security
    if (unlink(__FILE__)) {
        echo "<p class='success'>✓ Reset file deleted successfully</p>";
    } else {
        echo "<p class='error'>⚠️ Please manually delete reset-admin.php from your server!</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p class='info'>Please check your database credentials and try again.</p>";
}

echo "</div></body></html>";
?>

