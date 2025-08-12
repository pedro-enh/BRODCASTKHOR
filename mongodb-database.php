<?php
/**
 * MongoDB Database Class for Discord Broadcaster Pro
 * Handles all database operations using MongoDB
 */

require_once 'vendor/autoload.php';

class MongoDatabase {
    private $client;
    private $database;
    private $users;
    private $transactions;
    private $broadcasts;
    private $payments;
    
    public function __construct() {
        try {
            // MongoDB connection string
            $connectionString = 'mongodb+srv://pedro:JpvgRYEsYWE4eu2s@cluster0.9ezky8k.mongodb.net/?retryWrites=true&w=majority';
            
            $this->client = new MongoDB\Client($connectionString);
            $this->database = $this->client->discord_broadcaster;
            
            // Collections
            $this->users = $this->database->users;
            $this->transactions = $this->database->transactions;
            $this->broadcasts = $this->database->broadcasts;
            $this->payments = $this->database->payments;
            
            // Create indexes for better performance
            $this->createIndexes();
            
        } catch (Exception $e) {
            throw new Exception('MongoDB connection failed: ' . $e->getMessage());
        }
    }
    
    private function createIndexes() {
        try {
            // Create unique index on discord_id
            $this->users->createIndex(['discord_id' => 1], ['unique' => true]);
            
            // Create indexes for better query performance
            $this->transactions->createIndex(['user_id' => 1, 'created_at' => -1]);
            $this->broadcasts->createIndex(['discord_user_id' => 1, 'created_at' => -1]);
            $this->payments->createIndex(['discord_user_id' => 1, 'status' => 1]);
            
        } catch (Exception $e) {
            // Indexes might already exist, ignore errors
        }
    }
    
    /**
     * Create or update user
     */
    public function createOrUpdateUser($discordUser) {
        try {
            $userData = [
                'discord_id' => $discordUser['id'],
                'username' => $discordUser['username'] . '#' . $discordUser['discriminator'],
                'avatar_url' => $discordUser['avatar_url'],
                'credits' => 0,
                'is_admin' => false,
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $result = $this->users->updateOne(
                ['discord_id' => $discordUser['id']],
                [
                    '$set' => $userData,
                    '$setOnInsert' => [
                        'created_at' => new MongoDB\BSON\UTCDateTime(),
                        'total_spent' => 0,
                        'total_messages_sent' => 0,
                        'total_broadcasts' => 0
                    ]
                ],
                ['upsert' => true]
            );
            
            return $this->getUserByDiscordId($discordUser['id']);
            
        } catch (Exception $e) {
            throw new Exception('Failed to create/update user: ' . $e->getMessage());
        }
    }
    
    /**
     * Get user by Discord ID
     */
    public function getUserByDiscordId($discordId) {
        try {
            $user = $this->users->findOne(['discord_id' => $discordId]);
            
            if ($user) {
                // Convert MongoDB document to array
                return [
                    'id' => (string)$user['_id'],
                    'discord_id' => $user['discord_id'],
                    'username' => $user['username'],
                    'avatar_url' => $user['avatar_url'] ?? '',
                    'credits' => $user['credits'] ?? 0,
                    'is_admin' => $user['is_admin'] ?? false,
                    'total_spent' => $user['total_spent'] ?? 0,
                    'total_messages_sent' => $user['total_messages_sent'] ?? 0,
                    'total_broadcasts' => $user['total_broadcasts'] ?? 0,
                    'created_at' => $user['created_at']->toDateTime()->format('Y-m-d H:i:s'),
                    'updated_at' => $user['updated_at']->toDateTime()->format('Y-m-d H:i:s')
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            throw new Exception('Failed to get user: ' . $e->getMessage());
        }
    }
    
    /**
     * Add credits to user
     */
    public function addCredits($discordId, $amount, $description = 'Credits added') {
        try {
            $result = $this->users->updateOne(
                ['discord_id' => $discordId],
                [
                    '$inc' => ['credits' => $amount],
                    '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime()]
                ]
            );
            
            if ($result->getModifiedCount() > 0) {
                // Record transaction
                $this->recordTransaction($discordId, 'credit', $amount, $description);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            throw new Exception('Failed to add credits: ' . $e->getMessage());
        }
    }
    
    /**
     * Spend credits
     */
    public function spendCredits($discordId, $amount, $description = 'Credits spent') {
        try {
            // Check if user has enough credits
            $user = $this->getUserByDiscordId($discordId);
            if (!$user || $user['credits'] < $amount) {
                throw new Exception('Insufficient credits');
            }
            
            $result = $this->users->updateOne(
                ['discord_id' => $discordId],
                [
                    '$inc' => [
                        'credits' => -$amount,
                        'total_spent' => $amount
                    ],
                    '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime()]
                ]
            );
            
            if ($result->getModifiedCount() > 0) {
                // Record transaction
                $this->recordTransaction($discordId, 'spend', $amount, $description);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            throw new Exception('Failed to spend credits: ' . $e->getMessage());
        }
    }
    
    /**
     * Record transaction
     */
    public function recordTransaction($discordId, $type, $amount, $description) {
        try {
            $user = $this->getUserByDiscordId($discordId);
            if (!$user) {
                throw new Exception('User not found');
            }
            
            $transaction = [
                'user_id' => $user['id'],
                'discord_id' => $discordId,
                'type' => $type, // 'credit', 'spend', 'refund'
                'amount' => $amount,
                'description' => $description,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $result = $this->transactions->insertOne($transaction);
            return (string)$result->getInsertedId();
            
        } catch (Exception $e) {
            throw new Exception('Failed to record transaction: ' . $e->getMessage());
        }
    }
    
    /**
     * Get user transactions
     */
    public function getUserTransactions($discordId, $limit = 20) {
        try {
            $transactions = $this->transactions->find(
                ['discord_id' => $discordId],
                [
                    'sort' => ['created_at' => -1],
                    'limit' => $limit
                ]
            );
            
            $result = [];
            foreach ($transactions as $transaction) {
                $result[] = [
                    'id' => (string)$transaction['_id'],
                    'type' => $transaction['type'],
                    'amount' => $transaction['amount'],
                    'description' => $transaction['description'],
                    'created_at' => $transaction['created_at']->toDateTime()->format('Y-m-d H:i:s')
                ];
            }
            
            return $result;
            
        } catch (Exception $e) {
            throw new Exception('Failed to get transactions: ' . $e->getMessage());
        }
    }
    
    /**
     * Record broadcast
     */
    public function recordBroadcast($discordUserId, $guildId, $guildName, $message, $targetType, $messagesSent, $messagesFailed, $creditsUsed) {
        try {
            $broadcast = [
                'discord_user_id' => $discordUserId,
                'guild_id' => $guildId,
                'guild_name' => $guildName,
                'message' => $message,
                'target_type' => $targetType,
                'messages_sent' => $messagesSent,
                'messages_failed' => $messagesFailed,
                'credits_used' => $creditsUsed,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $result = $this->broadcasts->insertOne($broadcast);
            
            // Update user stats
            $this->users->updateOne(
                ['discord_id' => $discordUserId],
                [
                    '$inc' => [
                        'total_messages_sent' => $messagesSent,
                        'total_broadcasts' => 1
                    ],
                    '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime()]
                ]
            );
            
            return (string)$result->getInsertedId();
            
        } catch (Exception $e) {
            throw new Exception('Failed to record broadcast: ' . $e->getMessage());
        }
    }
    
    /**
     * Get user broadcasts
     */
    public function getUserBroadcasts($discordUserId, $limit = 10) {
        try {
            $broadcasts = $this->broadcasts->find(
                ['discord_user_id' => $discordUserId],
                [
                    'sort' => ['created_at' => -1],
                    'limit' => $limit
                ]
            );
            
            $result = [];
            foreach ($broadcasts as $broadcast) {
                $result[] = [
                    'id' => (string)$broadcast['_id'],
                    'guild_id' => $broadcast['guild_id'],
                    'guild_name' => $broadcast['guild_name'],
                    'message' => $broadcast['message'],
                    'target_type' => $broadcast['target_type'],
                    'messages_sent' => $broadcast['messages_sent'],
                    'messages_failed' => $broadcast['messages_failed'],
                    'credits_used' => $broadcast['credits_used'],
                    'created_at' => $broadcast['created_at']->toDateTime()->format('Y-m-d H:i:s')
                ];
            }
            
            return $result;
            
        } catch (Exception $e) {
            throw new Exception('Failed to get broadcasts: ' . $e->getMessage());
        }
    }
    
    /**
     * Get user stats
     */
    public function getUserStats($discordId) {
        try {
            $user = $this->getUserByDiscordId($discordId);
            
            if (!$user) {
                return [
                    'credits' => 0,
                    'total_spent' => 0,
                    'total_messages_sent' => 0,
                    'total_broadcasts' => 0
                ];
            }
            
            return [
                'credits' => $user['credits'],
                'total_spent' => $user['total_spent'],
                'total_messages_sent' => $user['total_messages_sent'],
                'total_broadcasts' => $user['total_broadcasts']
            ];
            
        } catch (Exception $e) {
            throw new Exception('Failed to get user stats: ' . $e->getMessage());
        }
    }
    
    /**
     * Create payment monitoring
     */
    public function createPaymentMonitoring($discordUserId, $amount) {
        try {
            $payment = [
                'discord_user_id' => $discordUserId,
                'amount' => $amount,
                'status' => 'pending',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'expires_at' => new MongoDB\BSON\UTCDateTime(time() * 1000 + (30 * 60 * 1000)) // 30 minutes
            ];
            
            $result = $this->payments->insertOne($payment);
            return (string)$result->getInsertedId();
            
        } catch (Exception $e) {
            throw new Exception('Failed to create payment monitoring: ' . $e->getMessage());
        }
    }
    
    /**
     * Get payment monitoring
     */
    public function getPaymentMonitoring($discordUserId, $amount) {
        try {
            $payment = $this->payments->findOne([
                'discord_user_id' => $discordUserId,
                'amount' => $amount,
                'expires_at' => ['$gt' => new MongoDB\BSON\UTCDateTime()]
            ]);
            
            if ($payment) {
                return [
                    'id' => (string)$payment['_id'],
                    'discord_user_id' => $payment['discord_user_id'],
                    'amount' => $payment['amount'],
                    'status' => $payment['status'],
                    'created_at' => $payment['created_at']->toDateTime()->format('Y-m-d H:i:s')
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            throw new Exception('Failed to get payment monitoring: ' . $e->getMessage());
        }
    }
    
    /**
     * Update payment status
     */
    public function updatePaymentStatus($paymentId, $status) {
        try {
            $result = $this->payments->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($paymentId)],
                [
                    '$set' => [
                        'status' => $status,
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );
            
            return $result->getModifiedCount() > 0;
            
        } catch (Exception $e) {
            throw new Exception('Failed to update payment status: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all users (admin function)
     */
    public function getAllUsers($limit = 100, $skip = 0) {
        try {
            $users = $this->users->find(
                [],
                [
                    'sort' => ['created_at' => -1],
                    'limit' => $limit,
                    'skip' => $skip
                ]
            );
            
            $result = [];
            foreach ($users as $user) {
                $result[] = [
                    'id' => (string)$user['_id'],
                    'discord_id' => $user['discord_id'],
                    'username' => $user['username'],
                    'credits' => $user['credits'] ?? 0,
                    'is_admin' => $user['is_admin'] ?? false,
                    'total_spent' => $user['total_spent'] ?? 0,
                    'total_messages_sent' => $user['total_messages_sent'] ?? 0,
                    'total_broadcasts' => $user['total_broadcasts'] ?? 0,
                    'created_at' => $user['created_at']->toDateTime()->format('Y-m-d H:i:s')
                ];
            }
            
            return $result;
            
        } catch (Exception $e) {
            throw new Exception('Failed to get all users: ' . $e->getMessage());
        }
    }
    
    /**
     * Get database statistics
     */
    public function getStats() {
        try {
            $totalUsers = $this->users->countDocuments();
            $totalTransactions = $this->transactions->countDocuments();
            $totalBroadcasts = $this->broadcasts->countDocuments();
            
            // Get total credits in system
            $pipeline = [
                ['$group' => ['_id' => null, 'total_credits' => ['$sum' => '$credits']]]
            ];
            $creditsResult = $this->users->aggregate($pipeline)->toArray();
            $totalCredits = isset($creditsResult[0]) ? $creditsResult[0]['total_credits'] : 0;
            
            return [
                'total_users' => $totalUsers,
                'total_transactions' => $totalTransactions,
                'total_broadcasts' => $totalBroadcasts,
                'total_credits' => $totalCredits
            ];
            
        } catch (Exception $e) {
            throw new Exception('Failed to get stats: ' . $e->getMessage());
        }
    }
}
?>
