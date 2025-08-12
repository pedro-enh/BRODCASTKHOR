<?php
session_start();
require_once 'database.php';

// Check if user is logged in
if (!isset($_SESSION['discord_user'])) {
    echo "User not logged in. Please login first.\n";
    exit;
}

$user = $_SESSION['discord_user'];
echo "=== DEBUG ADMIN STATUS ===\n\n";

echo "Session User Info:\n";
echo "- Discord ID: " . $user['id'] . "\n";
echo "- Username: " . $user['username'] . "#" . $user['discriminator'] . "\n";
echo "- Avatar URL: " . $user['avatar_url'] . "\n\n";

try {
    $db = new Database();
    
    // Get user from database
    $dbUser = $db->getUserByDiscordId($user['id']);
    
    if ($dbUser) {
        echo "Database User Info:\n";
        echo "- ID: " . $dbUser['id'] . "\n";
        echo "- Discord ID: " . $dbUser['discord_id'] . "\n";
        echo "- Username: " . $dbUser['username'] . "\n";
        echo "- Credits: " . $dbUser['credits'] . "\n";
        echo "- Is Admin: " . (isset($dbUser['is_admin']) ? $dbUser['is_admin'] : 'NOT SET') . "\n";
        echo "- Created: " . $dbUser['created_at'] . "\n";
        echo "- Updated: " . ($dbUser['updated_at'] ?? 'Never') . "\n\n";
        
        // Check admin status
        if (isset($dbUser['is_admin']) && $dbUser['is_admin'] == 1) {
            echo "✅ USER IS ADMIN\n";
        } else {
            echo "❌ USER IS NOT ADMIN\n";
            echo "To make this user admin, run:\n";
            echo "UPDATE users SET is_admin = 1 WHERE discord_id = '{$user['id']}';\n\n";
        }
        
    } else {
        echo "❌ User not found in database!\n";
        echo "Creating user...\n";
        
        $newUser = $db->createOrUpdateUser($user);
        if ($newUser) {
            echo "✅ User created successfully!\n";
            echo "Now making user admin...\n";
            
            $pdo = $db->getPdo();
            $stmt = $pdo->prepare("UPDATE users SET is_admin = 1 WHERE discord_id = ?");
            $stmt->execute([$user['id']]);
            
            echo "✅ User is now admin!\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== END DEBUG ===\n";
?>
