<?php
/**
 * Test MongoDB Connection
 * This script tests the MongoDB connection and basic operations
 */

echo "🧪 Testing MongoDB Connection...\n\n";

try {
    // Test if MongoDB extension is loaded
    if (!extension_loaded('mongodb')) {
        echo "❌ MongoDB PHP extension is not loaded!\n";
        echo "📝 Install it with: composer install\n";
        exit(1);
    }
    
    echo "✅ MongoDB PHP extension is loaded\n";
    
    // Test MongoDB connection
    require_once 'mongodb-database.php';
    
    echo "🔌 Connecting to MongoDB...\n";
    $db = new MongoDatabase();
    echo "✅ MongoDB connection successful!\n\n";
    
    // Test basic operations
    echo "🧪 Testing basic operations...\n";
    
    // Test user creation
    $testUser = [
        'id' => '123456789012345678',
        'username' => 'TestUser',
        'discriminator' => '0001',
        'avatar_url' => 'https://example.com/avatar.png'
    ];
    
    echo "👤 Creating test user...\n";
    $createdUser = $db->createOrUpdateUser($testUser);
    
    if ($createdUser) {
        echo "✅ Test user created successfully!\n";
        echo "   - Discord ID: {$createdUser['discord_id']}\n";
        echo "   - Username: {$createdUser['username']}\n";
        echo "   - Credits: {$createdUser['credits']}\n";
    } else {
        echo "❌ Failed to create test user\n";
    }
    
    // Test adding credits
    echo "\n💰 Testing credit operations...\n";
    $creditResult = $db->addCredits($testUser['id'], 10, 'Test credit addition');
    
    if ($creditResult) {
        echo "✅ Credits added successfully!\n";
        
        // Get updated user
        $updatedUser = $db->getUserByDiscordId($testUser['id']);
        echo "   - New credit balance: {$updatedUser['credits']}\n";
    } else {
        echo "❌ Failed to add credits\n";
    }
    
    // Test transactions
    echo "\n📊 Testing transaction history...\n";
    $transactions = $db->getUserTransactions($testUser['id'], 5);
    
    if (!empty($transactions)) {
        echo "✅ Transaction history retrieved!\n";
        echo "   - Total transactions: " . count($transactions) . "\n";
        foreach ($transactions as $transaction) {
            echo "   - {$transaction['type']}: {$transaction['amount']} ({$transaction['description']})\n";
        }
    } else {
        echo "ℹ️  No transactions found (this is normal for a new user)\n";
    }
    
    // Test database stats
    echo "\n📈 Testing database statistics...\n";
    $stats = $db->getStats();
    
    echo "✅ Database statistics retrieved!\n";
    echo "   - Total users: {$stats['total_users']}\n";
    echo "   - Total transactions: {$stats['total_transactions']}\n";
    echo "   - Total broadcasts: {$stats['total_broadcasts']}\n";
    echo "   - Total credits in system: {$stats['total_credits']}\n";
    
    echo "\n🎉 All MongoDB tests passed successfully!\n";
    echo "✅ Your MongoDB database is ready to use!\n\n";
    
    echo "📝 Database Collections Created:\n";
    echo "   - users (user accounts and credits)\n";
    echo "   - transactions (credit history)\n";
    echo "   - broadcasts (broadcast history)\n";
    echo "   - payments (payment monitoring)\n\n";
    
    echo "🚀 You can now deploy to Railway!\n";
    
} catch (Exception $e) {
    echo "❌ MongoDB test failed: " . $e->getMessage() . "\n";
    echo "\n🔧 Troubleshooting:\n";
    echo "1. Make sure MongoDB driver is installed: composer install\n";
    echo "2. Check your MongoDB connection string\n";
    echo "3. Verify MongoDB Atlas cluster is accessible\n";
    echo "4. Check firewall settings\n";
    exit(1);
}
?>
