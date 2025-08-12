<?php
session_start();

// Load configuration and database
try {
    $config = require_once 'config.php';
    require_once 'mongodb-simple.php';
    require_once 'admin-helper.php';
} catch (Exception $e) {
    die('Configuration or database error: ' . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['discord_user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['discord_user'];
$isAdmin = isAdmin();
$db = new MongoDatabase();

// Create or update user in database
$dbUser = $db->createOrUpdateUser($user);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'get_wallet_info':
            $stats = $db->getUserStats($user['id']);
            $transactions = $db->getUserTransactions($user['id'], 10);
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'transactions' => $transactions
            ]);
            break;
            
        case 'create_payment_request':
            $amount = $input['amount'] ?? 5000;
            $credits = $input['credits'] ?? 10;
            
            try {
                $paymentId = $db->createPaymentMonitoring($user['id'], $amount);
                echo json_encode([
                    'success' => true,
                    'payment_id' => $paymentId,
                    'amount' => $amount,
                    'credits' => $credits,
                    'recipient_id' => '675332512414695441'
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'check_payment_status':
            $paymentId = $input['payment_id'] ?? 0;
            $amount = $input['amount'] ?? 5000;
            
            $payment = $db->getPaymentMonitoring($user['id'], $amount);
            if ($payment && $payment['status'] === 'received') {
                echo json_encode(['success' => true, 'status' => 'completed']);
            } else {
                echo json_encode(['success' => true, 'status' => 'waiting']);
            }
            break;
            
        case 'run_probot_monitor':
            try {
                // Use the Discord bot to check for payments
                require_once 'discord-bot.php';
                $bot = new DiscordBot();
                
                // Get recent messages and process them
                $messages = $bot->getChannelMessages(10);
                $processed = 0;
                
                if ($messages) {
                    foreach ($messages as $message) {
                        // Check if message is from ProBot and contains transfer
                        if (isset($message['author']['id']) && 
                            $message['author']['id'] === '282859044593598464') {
                            
                            $content = $message['content'] ?? '';
                            $embeds = $message['embeds'] ?? [];
                            
                            // Check for transfer patterns
                            $patterns = [
                                '/✅.*transferred\s+(\d+)\s+credits?\s+to\s+<@675332512414695441>/i',
                                '/successfully\s+transferred\s+(\d+)\s+credits?\s+to\s+<@675332512414695441>/i'
                            ];
                            
                            $found = false;
                            foreach ($patterns as $pattern) {
                                if (preg_match($pattern, $content) || 
                                    (isset($embeds[0]['description']) && preg_match($pattern, $embeds[0]['description']))) {
                                    $found = true;
                                    break;
                                }
                            }
                            
                            if ($found) {
                                $bot->processMessage($message);
                                $processed++;
                            }
                        }
                    }
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => "Payment check completed. Found {$processed} ProBot transfers."
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'admin_add_credits':
            // Check if user is admin
            if (!$isAdmin) {
                echo json_encode(['success' => false, 'error' => 'Access denied. Admin privileges required.']);
                break;
            }
            
            $targetUserId = trim($input['targetUserId'] ?? '');
            $creditsAmount = intval($input['creditsAmount'] ?? 0);
            $reason = trim($input['reason'] ?? 'Manual credit addition by admin');
            
            // Validation
            if (empty($targetUserId)) {
                echo json_encode(['success' => false, 'error' => 'Target user ID is required']);
                break;
            }
            
            if (!preg_match('/^\d{17,19}$/', $targetUserId)) {
                echo json_encode(['success' => false, 'error' => 'Invalid Discord ID format']);
                break;
            }
            
            if ($creditsAmount < 1 || $creditsAmount > 1000) {
                echo json_encode(['success' => false, 'error' => 'Credits amount must be between 1 and 1000']);
                break;
            }
            
            try {
                // Initialize database
                require_once 'database.php';
                $database = initializeDatabase();
                
                // Check if target user exists, if not create them
                $stmt = $database->prepare("SELECT id, credits FROM users WHERE discord_id = ?");
                $stmt->execute([$targetUserId]);
                $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$targetUser) {
                    // Create new user
                    $stmt = $database->prepare("INSERT INTO users (discord_id, username, credits, created_at) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$targetUserId, 'Unknown User', $creditsAmount, date('Y-m-d H:i:s')]);
                    $targetUserId_db = $database->lastInsertId();
                    $oldCredits = 0;
                } else {
                    // Update existing user
                    $targetUserId_db = $targetUser['id'];
                    $oldCredits = $targetUser['credits'];
                    $newCredits = $oldCredits + $creditsAmount;
                    
                    $stmt = $database->prepare("UPDATE users SET credits = ?, updated_at = ? WHERE id = ?");
                    $stmt->execute([$newCredits, date('Y-m-d H:i:s'), $targetUserId_db]);
                }
                
                // Record transaction
                $description = "Admin credit addition: {$creditsAmount} credits - {$reason}";
                $stmt = $database->prepare("INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$targetUserId_db, 'credit', $creditsAmount, $description, date('Y-m-d H:i:s')]);
                
                echo json_encode([
                    'success' => true,
                    'message' => "Successfully added {$creditsAmount} credits to user {$targetUserId}",
                    'target_user' => $targetUserId,
                    'credits_added' => $creditsAmount,
                    'admin_user' => $user['username']
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// Get user stats
$stats = $db->getUserStats($user['id']);
$transactions = $db->getUserTransactions($user['id'], 20);
$broadcasts = $db->getUserBroadcasts($user['id'], 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discord Broadcaster Pro - Wallet</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="logo">
                    <i class="fab fa-discord"></i>
                    <h1>Discord Broadcaster Pro</h1>
                </div>
                <div class="user-section">
                    <div class="user-profile">
                        <div class="user-info">
                            <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="User Avatar" class="user-avatar">
                            <div class="user-details">
                                <span class="user-name"><?php echo htmlspecialchars($user['username'] . '#' . $user['discriminator']); ?></span>
                                <div class="connection-status">
                                    <span class="status-indicator online"></span>
                                    <span class="status-text">Wallet Active</span>
                                </div>
                            </div>
                        </div>
                        <a href="index.php" class="btn btn-secondary btn-small">
                            <i class="fas fa-home"></i>
                            Home
                        </a>
                        <a href="broadcast.php" class="btn btn-primary btn-small">
                            <i class="fas fa-broadcast-tower"></i>
                            Broadcast
                        </a>
                        <?php
                        // Check if user is admin using admin-helper
                        if ($isAdmin): ?>
                        <a href="admin-access.php" class="btn btn-warning btn-small">
                            <i class="fas fa-crown"></i>
                            Admin Access
                        </a>
                        <?php endif; ?>
                        <a href="logout.php" class="btn btn-secondary btn-small">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Wallet Overview -->
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-wallet"></i> My Wallet</h2>
                </div>
                <div class="card-body">
                    <div class="wallet-stats">
                        <div class="stat-card primary">
                            <div class="stat-icon">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo number_format($stats['credits'] ?? 0); ?></h3>
                                <p>Available Credits</p>
                                <small><?php echo $stats['credits'] ?? 0; ?> messages remaining</small>
                            </div>
                        </div>
                        
                        <div class="stat-card success">
                            <div class="stat-icon">
                                <i class="fas fa-paper-plane"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo number_format($stats['total_messages_sent'] ?? 0); ?></h3>
                                <p>Messages Sent</p>
                                <small><?php echo $stats['total_broadcasts'] ?? 0; ?> broadcasts completed</small>
                            </div>
                        </div>
                        
                        <div class="stat-card info">
                            <div class="stat-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo number_format($stats['total_spent'] ?? 0); ?></h3>
                                <p>Total Spent</p>
                                <small>Lifetime usage</small>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Buy Credits -->
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-shopping-cart"></i> Buy Credits</h2>
                </div>
                <div class="card-body">
                    <div class="pricing-info">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <h4>How to Purchase Credits</h4>
                                <p>Send <strong>5,000 ProBot Credits</strong> to user ID <code>675332512414695441</code> to receive <strong>10 broadcast messages</strong>.</p>
                                <p>Our bot will automatically detect your payment and add credits to your wallet within 1-2 minutes.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pricing-packages">
                        <div class="package-card recommended">
                            <div class="package-header">
                                <h3>Standard Package</h3>
                                <div class="package-badge">Most Popular</div>
                            </div>
                            <div class="package-price">
                                <span class="price">5,000</span>
                                <span class="currency">ProBot Credits</span>
                            </div>
                            <div class="package-features">
                                <div class="feature">
                                    <i class="fas fa-check"></i>
                                    <span>10 Broadcast Messages</span>
                                </div>
                                <div class="feature">
                                    <i class="fas fa-check"></i>
                                    <span>All Server Types</span>
                                </div>
                                <div class="feature">
                                    <i class="fas fa-check"></i>
                                    <span>Anti-Ban Protection</span>
                                </div>
                                <div class="feature">
                                    <i class="fas fa-check"></i>
                                    <span>Instant Activation</span>
                                </div>
                            </div>
                            <button class="btn btn-success btn-large" onclick="startPaymentProcess(5000, 10)">
                                <i class="fas fa-credit-card"></i>
                                Purchase Now
                            </button>
                        </div>
                    </div>
                    
                    <div class="payment-tools">
                        <div class="alert alert-warning">
                            <i class="fas fa-robot"></i>
                            <div>
                                <h4>Payment Detection</h4>
                                <p>If your payment isn't detected automatically, you can manually check for new ProBot transfers or ask the server owner to use the <code>!addcredits</code> command.</p>
                                <button class="btn btn-info" onclick="runProbotMonitor()">
                                    <i class="fas fa-sync"></i>
                                    Check for Payments
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <?php if ($isAdmin): ?>
            <!-- Admin Tools -->
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-crown"></i> Admin Tools</h2>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <h4>Administrator Access</h4>
                            <p>You have admin privileges. Use these tools to manage user credits and payments.</p>
                        </div>
                    </div>
                    
                    <div class="admin-actions">
                        <div class="action-card admin-card">
                            <div class="action-icon">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <div class="action-content">
                                <h3>Add Credits</h3>
                                <p>Manually add credits to any user's wallet</p>
                            </div>
                            <button class="btn btn-warning" onclick="openAddCreditsModal()">
                                <i class="fas fa-plus"></i>
                                Add Credits
                            </button>
                        </div>
                        
                        <div class="action-card admin-card">
                            <div class="action-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="action-content">
                                <h3>Payment Checker</h3>
                                <p>Process manual payments and transfers</p>
                            </div>
                            <a href="payment-checker.php" class="btn btn-warning">
                                <i class="fas fa-external-link-alt"></i>
                                Open Panel
                            </a>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Add Credits Modal (Admin Only) -->
            <?php if ($isAdmin): ?>
            <div id="addCreditsModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-plus-circle"></i> Add Credits to User</h3>
                        <button class="modal-close" onclick="closeAddCreditsModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="addCreditsForm">
                            <div class="input-group">
                                <label for="targetUserId">Discord User ID</label>
                                <input type="text" id="targetUserId" name="targetUserId" 
                                       placeholder="123456789012345678" 
                                       pattern="[0-9]{17,19}" 
                                       required>
                                <small>Enter the Discord ID of the user to add credits to</small>
                            </div>
                            
                            <div class="input-group">
                                <label for="creditsAmount">Credits to Add</label>
                                <input type="number" id="creditsAmount" name="creditsAmount" 
                                       placeholder="10" 
                                       min="1" 
                                       max="1000" 
                                       required>
                                <small>Number of broadcast messages to add</small>
                            </div>
                            
                            <div class="input-group">
                                <label for="adminReason">Reason (Optional)</label>
                                <textarea id="adminReason" name="adminReason" 
                                          placeholder="Manual credit addition by admin"
                                          rows="3"></textarea>
                                <small>Reason for adding credits (will appear in transaction history)</small>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <div>
                                    <h4>Admin Credit Addition</h4>
                                    <p>This will immediately add the specified credits to the user's wallet and create a transaction record.</p>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="closeAddCreditsModal()">Cancel</button>
                        <button class="btn btn-warning" onclick="addCreditsToUser()">
                            <i class="fas fa-plus"></i>
                            Add Credits
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Payment Instructions Modal -->
            <div id="paymentModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-credit-card"></i> Payment Instructions</h3>
                        <button class="modal-close" onclick="closePaymentModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="payment-steps">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h4>Open Discord</h4>
                                    <p>Go to any Discord server with ProBot</p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <h4>Send Credits</h4>
                                    <p>Use the command:</p>
                                    <div class="command-box">
                                        <code id="paymentCommand">#credits <span id="recipientId">675332512414695441</span> <span id="paymentAmount">5000</span></code>
                                        <button class="copy-btn" onclick="copyCommand()">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <h4>Wait for Confirmation</h4>
                                    <p>Your credits will be added automatically within 1-2 minutes</p>
                                    <div class="payment-status" id="paymentStatus">
                                        <i class="fas fa-clock"></i>
                                        <span>Waiting for payment...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="payment-timer">
                            <p>Payment expires in: <span id="paymentTimer">30:00</span></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                        <button class="btn btn-primary" onclick="checkPaymentStatus()">
                            <i class="fas fa-sync"></i>
                            Check Status
                        </button>
                    </div>
                </div>
            </div>

            <!-- Transaction History -->
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Transaction History</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($transactions)): ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <h3>No Transactions Yet</h3>
                        <p>Your transaction history will appear here once you make your first purchase.</p>
                    </div>
                    <?php else: ?>
                    <div class="transaction-list">
                        <?php foreach ($transactions as $transaction): ?>
                        <div class="transaction-item <?php echo $transaction['type']; ?>">
                            <div class="transaction-icon">
                                <?php if ($transaction['type'] === 'purchase'): ?>
                                    <i class="fas fa-plus-circle"></i>
                                <?php elseif ($transaction['type'] === 'spend'): ?>
                                    <i class="fas fa-minus-circle"></i>
                                <?php else: ?>
                                    <i class="fas fa-undo"></i>
                                <?php endif; ?>
                            </div>
                            <div class="transaction-details">
                                <h4><?php echo htmlspecialchars($transaction['description']); ?></h4>
                                <p><?php echo date('M j, Y \a\t g:i A', strtotime($transaction['created_at'])); ?></p>
                            </div>
                            <div class="transaction-amount <?php echo $transaction['type']; ?>">
                                <?php echo $transaction['type'] === 'spend' ? '-' : '+'; ?><?php echo number_format($transaction['amount']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Broadcast History -->
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-broadcast-tower"></i> Recent Broadcasts</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($broadcasts)): ?>
                    <div class="empty-state">
                        <i class="fas fa-broadcast-tower"></i>
                        <h3>No Broadcasts Yet</h3>
                        <p>Your broadcast history will appear here once you send your first message.</p>
                        <a href="broadcast.php" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Start Broadcasting
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="broadcast-list">
                        <?php foreach ($broadcasts as $broadcast): ?>
                        <div class="broadcast-item">
                            <div class="broadcast-info">
                                <h4><?php echo htmlspecialchars($broadcast['guild_name'] ?? 'Unknown Server'); ?></h4>
                                <p class="broadcast-message"><?php echo htmlspecialchars(substr($broadcast['message'], 0, 100)) . (strlen($broadcast['message']) > 100 ? '...' : ''); ?></p>
                                <div class="broadcast-stats">
                                    <span class="stat">
                                        <i class="fas fa-check-circle"></i>
                                        <?php echo $broadcast['messages_sent']; ?> sent
                                    </span>
                                    <?php if ($broadcast['messages_failed'] > 0): ?>
                                    <span class="stat failed">
                                        <i class="fas fa-times-circle"></i>
                                        <?php echo $broadcast['messages_failed']; ?> failed
                                    </span>
                                    <?php endif; ?>
                                    <span class="stat">
                                        <i class="fas fa-coins"></i>
                                        <?php echo $broadcast['credits_used']; ?> credits
                                    </span>
                                </div>
                            </div>
                            <div class="broadcast-date">
                                <?php echo date('M j, Y', strtotime($broadcast['created_at'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <!-- Toast Notifications -->
        <div class="toast-container" id="toastContainer"></div>
    </div>

    <script src="wallet.js"></script>
</body>
</html>
