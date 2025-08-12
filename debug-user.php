<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['discord_user'])) {
    echo "User not logged in";
    exit;
}

$user = $_SESSION['discord_user'];

// Display user info
echo "<h2>Current User Info:</h2>";
echo "<p><strong>Username:</strong> " . htmlspecialchars($user['username'] . '#' . $user['discriminator']) . "</p>";
echo "<p><strong>Discord ID:</strong> " . htmlspecialchars($user['id']) . "</p>";

// Check if user is admin
require_once 'admin-helper.php';
$isAdmin = isAdmin();
echo "<p><strong>Is Admin:</strong> " . ($isAdmin ? 'Yes' : 'No') . "</p>";

// Show current admin IDs
$config = require_once 'config.php';
echo "<h3>Current Admin IDs:</h3>";
echo "<ul>";
foreach ($config['ADMIN_DISCORD_IDS'] as $adminId) {
    echo "<li>" . htmlspecialchars($adminId) . "</li>";
}
echo "</ul>";

// Add current user to admin list if not already
if (!$isAdmin) {
    echo "<h3>Adding current user to admin list...</h3>";
    
    // Read current config
    $configContent = file_get_contents('config.php');
    
    // Replace the admin IDs array
    $newAdminIds = "    'ADMIN_DISCORD_IDS' => [\n";
    $newAdminIds .= "        '675332512414695441',  // Main admin ID\n";
    $newAdminIds .= "        '" . $user['id'] . "', // " . $user['username'] . " - added automatically\n";
    $newAdminIds .= "        // Add more admin IDs if needed\n";
    $newAdminIds .= "    ],";
    
    $pattern = "/'ADMIN_DISCORD_IDS' => \[[^\]]*\],/s";
    $newConfigContent = preg_replace($pattern, $newAdminIds, $configContent);
    
    if ($newConfigContent && $newConfigContent !== $configContent) {
        file_put_contents('config.php', $newConfigContent);
        echo "<p style='color: green;'>✅ Successfully added your Discord ID to admin list!</p>";
        echo "<p>Your Discord ID: <strong>" . htmlspecialchars($user['id']) . "</strong></p>";
        echo "<p><a href='wallet.php'>Go to Wallet to see Admin Tools</a></p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to update config file</p>";
    }
} else {
    echo "<p style='color: green;'>✅ You already have admin privileges!</p>";
    echo "<p><a href='wallet.php'>Go to Wallet to see Admin Tools</a></p>";
}
?>
