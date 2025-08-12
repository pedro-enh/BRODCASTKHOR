<?php
session_start();

if (!isset($_SESSION['discord_user'])) {
    die("Please login first to add yourself as admin.");
}

$user = $_SESSION['discord_user'];
$currentUserId = $user['id'];

echo "<!DOCTYPE html>";
echo "<html><head><title>Add Current User as Admin</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#2c2f33;color:white;} .info{background:#36393f;padding:20px;border-radius:8px;margin:10px 0;} code{background:#40444b;padding:5px;border-radius:3px;} .success{color:#43b581;} .error{color:#f04747;}</style>";
echo "</head><body>";

echo "<h1>🔧 Add Current User as Admin</h1>";

echo "<div class='info'>";
echo "<h3>Current User:</h3>";
echo "<p><strong>Discord ID:</strong> <code>{$currentUserId}</code></p>";
echo "<p><strong>Username:</strong> {$user['username']}#{$user['discriminator']}</p>";
echo "</div>";

// Read current config
$configPath = 'config.php';
$configContent = file_get_contents($configPath);

// Check if user is already in admin list
if (strpos($configContent, $currentUserId) !== false) {
    echo "<div class='info success'>";
    echo "<h3>✅ Already Admin</h3>";
    echo "<p>Your Discord ID is already in the admin list!</p>";
    echo "</div>";
} else {
    // Add user to admin list
    $pattern = "/'ADMIN_DISCORD_IDS' => \[\s*'675332512414695441',\s*\/\/ pedr_o\.1#0 - Main admin ID/";
    $replacement = "'ADMIN_DISCORD_IDS' => [\n        '675332512414695441',  // pedr_o.1#0 - Main admin ID\n        '{$currentUserId}',  // {$user['username']}#{$user['discriminator']} - Added automatically";
    
    $newConfigContent = preg_replace($pattern, $replacement, $configContent);
    
    if ($newConfigContent && $newConfigContent !== $configContent) {
        // Write updated config
        if (file_put_contents($configPath, $newConfigContent)) {
            echo "<div class='info success'>";
            echo "<h3>✅ Success!</h3>";
            echo "<p>Your Discord ID has been added to the admin list in config.php</p>";
            echo "<p>You should now see the Admin Access button on the website.</p>";
            echo "</div>";
        } else {
            echo "<div class='info error'>";
            echo "<h3>❌ Error</h3>";
            echo "<p>Failed to write to config.php. Please check file permissions.</p>";
            echo "</div>";
        }
    } else {
        echo "<div class='info error'>";
        echo "<h3>❌ Error</h3>";
        echo "<p>Failed to update config.php. Please add manually:</p>";
        echo "<code>'ADMIN_DISCORD_IDS' => [<br>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;'675332512414695441', // Main admin<br>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;'{$currentUserId}', // Your ID<br>";
        echo "]</code>";
        echo "</div>";
    }
}

// Test admin status
require_once 'admin-helper.php';
$isAdmin = isAdmin();

echo "<div class='info'>";
echo "<h3>Admin Status Check:</h3>";
if ($isAdmin) {
    echo "<p class='success'>✅ You are now an admin!</p>";
} else {
    echo "<p class='error'>❌ Admin check failed. Please refresh the page.</p>";
}
echo "</div>";

echo "<div class='info'>";
echo "<a href='index.php' style='color:#7289da;'>← Back to Home</a> | ";
echo "<a href='admin-access.php' style='color:#7289da;'>Admin Panel →</a>";
echo "</div>";

echo "</body></html>";
?>
