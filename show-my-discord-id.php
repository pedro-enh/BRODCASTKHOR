<?php
session_start();

if (!isset($_SESSION['discord_user'])) {
    echo "Please login first to see your Discord ID.";
    exit;
}

$user = $_SESSION['discord_user'];

echo "<!DOCTYPE html>";
echo "<html><head><title>Your Discord ID</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#2c2f33;color:white;} .info{background:#36393f;padding:20px;border-radius:8px;margin:10px 0;} code{background:#40444b;padding:5px;border-radius:3px;}</style>";
echo "</head><body>";

echo "<h1>🔍 Your Discord Information</h1>";

echo "<div class='info'>";
echo "<h3>Current User Info:</h3>";
echo "<p><strong>Discord ID:</strong> <code>" . htmlspecialchars($user['id']) . "</code></p>";
echo "<p><strong>Username:</strong> " . htmlspecialchars($user['username']) . "</p>";
echo "<p><strong>Discriminator:</strong> #" . htmlspecialchars($user['discriminator']) . "</p>";
echo "<p><strong>Full Username:</strong> " . htmlspecialchars($user['username'] . '#' . $user['discriminator']) . "</p>";
echo "</div>";

// Check current admin config
$config = require_once 'config.php';
$adminIds = $config['ADMIN_DISCORD_IDS'] ?? [];

echo "<div class='info'>";
echo "<h3>Admin Configuration:</h3>";
echo "<p><strong>Current Admin IDs in config.php:</strong></p>";
echo "<ul>";
foreach ($adminIds as $adminId) {
    $isCurrent = ($adminId === $user['id']) ? " ✅ (YOU)" : "";
    echo "<li><code>{$adminId}</code>{$isCurrent}</li>";
}
echo "</ul>";
echo "</div>";

// Check if current user is admin
require_once 'admin-helper.php';
$isAdmin = isAdmin();

echo "<div class='info'>";
echo "<h3>Admin Status:</h3>";
if ($isAdmin) {
    echo "<p style='color:#43b581;'>✅ <strong>You ARE an admin!</strong></p>";
} else {
    echo "<p style='color:#f04747;'>❌ <strong>You are NOT an admin</strong></p>";
    echo "<p>To make yourself admin, add your Discord ID to config.php:</p>";
    echo "<code>'ADMIN_DISCORD_IDS' => [<br>";
    echo "&nbsp;&nbsp;&nbsp;&nbsp;'{$user['id']}', // Your ID<br>";
    echo "&nbsp;&nbsp;&nbsp;&nbsp;'675332512414695441', // Existing admin<br>";
    echo "]</code>";
}
echo "</div>";

echo "<div class='info'>";
echo "<a href='index.php' style='color:#7289da;'>← Back to Home</a>";
echo "</div>";

echo "</body></html>";
?>
