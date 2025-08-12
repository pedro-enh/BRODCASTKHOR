<?php
// Script to make a user admin
require_once 'database.php';

// Discord ID of the user to make admin
$discord_id = 'pedr_o.1#0'; // Replace with actual Discord ID

try {
    $db = new Database();
    
    // Get user by Discord ID
    $user = $db->getUserByDiscordId($discord_id);
    
    if ($user) {
        // Update user to admin
        $pdo = $db->getPdo();
        $stmt = $pdo->prepare("UPDATE users SET is_admin = 1 WHERE discord_id = ?");
        $stmt->execute([$discord_id]);
        
        echo "User {$discord_id} has been made an admin!\n";
        echo "User ID: {$user['id']}\n";
        echo "Username: {$user['username']}\n";
        echo "Credits: {$user['credits']}\n";
    } else {
        echo "User {$discord_id} not found in database.\n";
        echo "Please login to the website first to create the user account.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
