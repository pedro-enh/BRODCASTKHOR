<?php
// Background worker for processing broadcast queue
require_once 'broadcast-queue.php';
require_once 'database.php';

// Load configuration
try {
    $config = require_once 'config.php';
} catch (Exception $e) {
    die('Configuration file not found. Please check your environment variables.');
}

$queue = new BroadcastQueue();
$db = new Database();

// Function to send broadcast
function sendBroadcast($bot_token, $guild_id, $message, $target_type, $delay, $enable_mentions) {
    // Get guild members first
    $members_result = getGuildMembers($bot_token, $guild_id);
    
    if (!$members_result['success']) {
        return $members_result;
    }
    
    $members = $members_result['members'];
    $sent_count = 0;
    $failed_count = 0;
    $failed_users = [];
    
    // Process mentions if enabled
    if ($enable_mentions) {
        $message = processMentions($message, $guild_id, $bot_token);
    }
    
    foreach ($members as $member) {
        $user_id = $member['user']['id'];
        $username = $member['user']['username'] . '#' . $member['user']['discriminator'];
        
        // Create DM channel
        $dm_result = createDMChannel($bot_token, $user_id);
        if (!$dm_result['success']) {
            $failed_count++;
            $failed_users[] = [
                'user' => $username,
                'reason' => 'Failed to create DM channel'
            ];
            continue;
        }
        
        $dm_channel_id = $dm_result['channel_id'];
        
        // Personalize message with user mention if enabled
        $personalized_message = $message;
        if ($enable_mentions) {
            $personalized_message = str_replace('{user}', "<@{$user_id}>", $personalized_message);
            $personalized_message = str_replace('{username}', $member['user']['username'], $personalized_message);
        }
        
        // Send message
        $send_result = sendDirectMessage($bot_token, $dm_channel_id, $personalized_message);
        if ($send_result['success']) {
            $sent_count++;
        } else {
            $failed_count++;
            $failed_users[] = [
                'user' => $username,
                'reason' => $send_result['error']
            ];
        }
        
        // Anti-ban protection: wait between messages
        sleep($delay);
        
        // Break if too many failures (additional protection)
        if ($failed_count > 10 && $sent_count < 5) {
            break;
        }
    }
    
    return [
        'success' => true,
        'sent_count' => $sent_count,
        'failed_count' => $failed_count,
        'total_targeted' => count($members),
        'failed_users' => $failed_users
    ];
}

function getGuildMembers($bot_token, $guild_id) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://discord.com/api/guilds/{$guild_id}/members?limit=1000");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bot ' . $bot_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $members = json_decode($response, true);
        // Filter out bots
        $filtered_members = array_filter($members, function($member) {
            return !isset($member['user']['bot']) || !$member['user']['bot'];
        });
        return ['success' => true, 'members' => array_values($filtered_members)];
    } else {
        return ['success' => false, 'error' => 'Failed to fetch members', 'code' => $http_code];
    }
}

function processMentions($message, $guild_id, $bot_token) {
    // This is a simplified version - in a full implementation,
    // you'd fetch roles and process @role mentions
    return $message;
}

function createDMChannel($bot_token, $user_id) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://discord.com/api/users/@me/channels');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['recipient_id' => $user_id]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bot ' . $bot_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $channel_data = json_decode($response, true);
        return ['success' => true, 'channel_id' => $channel_data['id']];
    } else {
        return ['success' => false, 'error' => 'Failed to create DM channel'];
    }
}

function sendDirectMessage($bot_token, $channel_id, $message) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://discord.com/api/channels/{$channel_id}/messages");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['content' => $message]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bot ' . $bot_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        return ['success' => true];
    } else {
        $error_data = json_decode($response, true);
        $error_message = isset($error_data['message']) ? $error_data['message'] : 'Unknown error';
        return ['success' => false, 'error' => $error_message];
    }
}

// Main worker loop
echo "Broadcast Worker Started\n";

while (true) {
    try {
        // Get next pending broadcast
        $broadcast = $queue->getNextPendingBroadcast();
        
        if ($broadcast) {
            echo "Processing broadcast ID: {$broadcast['id']}\n";
            
            // Update status to processing
            $queue->updateBroadcastStatus($broadcast['id'], 'processing');
            
            // Get members count first
            $members_result = getGuildMembers($broadcast['bot_token'], $broadcast['guild_id']);
            
            if ($members_result['success']) {
                $total_members = count($members_result['members']);
                $queue->updateBroadcastStatus($broadcast['id'], 'processing', [
                    'total_members' => $total_members
                ]);
                
                // Send broadcast
                $result = sendBroadcast(
                    $broadcast['bot_token'],
                    $broadcast['guild_id'],
                    $broadcast['message'],
                    $broadcast['target_type'],
                    $broadcast['delay_seconds'],
                    $broadcast['enable_mentions']
                );
                
                if ($result['success']) {
                    // Update with completion status
                    $queue->updateBroadcastStatus($broadcast['id'], 'completed', [
                        'sent_count' => $result['sent_count'],
                        'failed_count' => $result['failed_count'],
                        'total_members' => $result['total_targeted']
                    ]);
                    
                    echo "Broadcast completed: {$result['sent_count']} sent, {$result['failed_count']} failed\n";
                } else {
                    // Update with failure status
                    $queue->updateBroadcastStatus($broadcast['id'], 'failed', [
                        'error_message' => $result['error'] ?? 'Unknown error'
                    ]);
                    
                    echo "Broadcast failed: {$result['error']}\n";
                }
            } else {
                // Failed to get members
                $queue->updateBroadcastStatus($broadcast['id'], 'failed', [
                    'error_message' => $members_result['error']
                ]);
                
                echo "Failed to get members: {$members_result['error']}\n";
            }
        } else {
            // No pending broadcasts, wait a bit
            sleep(5);
        }
        
    } catch (Exception $e) {
        echo "Worker error: " . $e->getMessage() . "\n";
        sleep(10); // Wait longer on error
    }
}
?>
