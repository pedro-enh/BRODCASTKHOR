<?php
/**
 * Simple MongoDB Database Class
 * Uses MongoDB extension directly without Composer
 */

class SimpleMongoDatabase {
    private $manager;
    private $database;
    
    public function __construct() {
        try {
            // Check if MongoDB extension is loaded
            if (!extension_loaded('mongodb')) {
                throw new Exception('MongoDB extension is not loaded');
            }
            
            // MongoDB connection string
            $connectionString = 'mongodb+srv://pedro:JpvgRYEsYWE4eu2s@cluster0.9ezky8k.mongodb.net/?retryWrites=true&w=majority';
            
            $this->manager = new MongoDB\Driver\Manager($connectionString);
            $this->database = 'discord_broadcaster';
            
        } catch (Exception $e) {
            throw new Exception('MongoDB connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Create or update user
     */
    public function createOrUpdateUser($discordUser) {
        try {
            $collection = $this->database . '.users';
            
            $userData = [
                'discord_id' => $discordUser['id'],
                'username' => $discordUser['username'] . '#' . $discordUser['discriminator'],
                'avatar_url' => $discordUser['avatar_url'],
                'credits' => 0,
                'is_admin' => false,
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->update(
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
            
            $this->manager->executeBulkWrite($collection, $bulk);
            
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
            $collection = $this->database . '.users';
            
            $query = new MongoDB\Driver\Query(['discord_id' => $discordId]);
            $cursor = $this->manager->executeQuery($collection, $query);
            
            $user = $cursor->toArray();
            
            if (!empty($user)) {
                $user = $user[0];
                return [
                    'id' => (string)$user->_id,
                    'discord_id' => $user->discord_id,
                    'username' => $user->username,
                    'avatar_url' => $user->avatar_url ?? '',
                    'credits' => $user->credits ?? 0,
                    'is_admin' => $user->is_admin ?? false,
                    'total_spent' => $user->total_spent ?? 0,
                    'total_messages_sent' => $user->total_messages_sent ?? 0,
                    'total_broadcasts' => $user->total_broadcasts ?? 0,
                    'created_at' => $user->created_at->toDateTime()->format('Y-m-d H:i:s'),
                    'updated_at' => $user->updated_at->toDateTime()->format('Y-m-d H:i:s')
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
            $collection = $this->database . '.users';
            
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->update(
                ['discord_id' => $discordId],
                [
                    '$inc' => ['credits' => $amount],
                    '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime()]
                ]
            );
            
            $result = $this->manager->executeBulkWrite($collection, $bulk);
            
            if ($result->getModifiedCount() > 0) {
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
            
            $collection = $this->database . '.users';
            
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->update(
                ['discord_id' => $discordId],
                [
                    '$inc' => [
                        'credits' => -$amount,
                        'total_spent' => $amount
                    ],
                    '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime()]
                ]
            );
            
            $result = $this->manager->executeBulkWrite($collection, $bulk);
            
            if ($result->getModifiedCount() > 0) {
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
            
            $collection = $this->database . '.transactions';
            
            $transaction = [
                'user_id' => $user['id'],
                'discord_id' => $discordId,
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->insert($transaction);
            
            $result = $this->manager->executeBulkWrite($collection, $bulk);
            return (string)$result->getInsertedIds()[0];
            
        } catch (Exception $e) {
            throw new Exception('Failed to record transaction: ' . $e->getMessage());
        }
    }
    
    /**
     * Get user transactions
     */
    public function getUserTransactions($discordId, $limit = 20) {
        try {
            $collection = $this->database . '.transactions';
            
            $options = [
                'sort' => ['created_at' => -1],
                'limit' => $limit
            ];
            
            $query = new MongoDB\Driver\Query(['discord_id' => $discordId], $options);
            $cursor = $this->manager->executeQuery($collection, $query);
            
            $result = [];
            foreach ($cursor as $transaction) {
                $result[] = [
                    'id' => (string)$transaction->_id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at->toDateTime()->format('Y-m-d H:i:s')
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
            $collection = $this->database . '.broadcasts';
            
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
            
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->insert($broadcast);
            
            $result = $this->manager->executeBulkWrite($collection, $bulk);
            
            // Update user stats
            $userCollection = $this->database . '.users';
            $userBulk = new MongoDB\Driver\BulkWrite;
            $userBulk->update(
                ['discord_id' => $discordUserId],
                [
                    '$inc' => [
                        'total_messages_sent' => $messagesSent,
                        'total_broadcasts' => 1
                    ],
                    '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime()]
                ]
            );
            
            $this->manager->executeBulkWrite($userCollection, $userBulk);
            
            return (string)$result->getInsertedIds()[0];
            
        } catch (Exception $e) {
            throw new Exception('Failed to record broadcast: ' . $e->getMessage());
        }
    }
    
    /**
     * Get user broadcasts
     */
    public function getUserBroadcasts($discordUserId, $limit = 10) {
        try {
            $collection = $this->database . '.broadcasts';
            
            $options = [
                'sort' => ['created_at' => -1],
                'limit' => $limit
            ];
            
            $query = new MongoDB\Driver\Query(['discord_user_id' => $discordUserId], $options);
            $cursor = $this->manager->executeQuery($collection, $query);
            
            $result = [];
            foreach ($cursor as $broadcast) {
                $result[] = [
                    'id' => (string)$broadcast->_id,
                    'guild_id' => $broadcast->guild_id,
                    'guild_name' => $broadcast->guild_name,
                    'message' => $broadcast->message,
                    'target_type' => $broadcast->target_type,
                    'messages_sent' => $broadcast->messages_sent,
                    'messages_failed' => $broadcast->messages_failed,
                    'credits_used' => $broadcast->credits_used,
                    'created_at' => $broadcast->created_at->toDateTime()->format('Y-m-d H:i:s')
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
            $collection = $this->database . '.payments';
            
            $payment = [
                'discord_user_id' => $discordUserId,
                'amount' => $amount,
                'status' => 'pending',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'expires_at' => new MongoDB\BSON\UTCDateTime(time() * 1000 + (30 * 60 * 1000))
            ];
            
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->insert($payment);
            
            $result = $this->manager->executeBulkWrite($collection, $bulk);
            return (string)$result->getInsertedIds()[0];
            
        } catch (Exception $e) {
            throw new Exception('Failed to create payment monitoring: ' . $e->getMessage());
        }
    }
    
    /**
     * Get payment monitoring
     */
    public function getPaymentMonitoring($discordUserId, $amount) {
        try {
            $collection = $this->database . '.payments';
            
            $query = new MongoDB\Driver\Query([
                'discord_user_id' => $discordUserId,
                'amount' => $amount,
                'expires_at' => ['$gt' => new MongoDB\BSON\UTCDateTime()]
            ]);
            
            $cursor = $this->manager->executeQuery($collection, $query);
            $payments = $cursor->toArray();
            
            if (!empty($payments)) {
                $payment = $payments[0];
                return [
                    'id' => (string)$payment->_id,
                    'discord_user_id' => $payment->discord_user_id,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'created_at' => $payment->created_at->toDateTime()->format('Y-m-d H:i:s')
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            throw new Exception('Failed to get payment monitoring: ' . $e->getMessage());
        }
    }
}

// Create alias for compatibility
class_alias('SimpleMongoDatabase', 'MongoDatabase');
?>
