<?php
/**
 * Manual Payment Checker
 * Use this to manually check and process ProBot transfers when bot is offline
 */

session_start();
require_once 'database.php';
require_once 'admin-helper.php';

// Check if user is logged in
if (!isset($_SESSION['discord_user'])) {
    header('Location: index.php');
    exit;
}

// Check if user is admin
requireAdmin('index.php');

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'process_payment') {
        $sender_id = trim($_POST['sender_id'] ?? '');
        $probot_credits = intval($_POST['probot_credits'] ?? 0);
        $transfer_proof = trim($_POST['transfer_proof'] ?? '');
        
        // Validation
        if (empty($sender_id)) {
            $error = 'Sender Discord ID is required';
        } elseif (!preg_match('/^\d{17,19}$/', $sender_id)) {
            $error = 'Invalid Discord ID format (should be 17-19 digits)';
        } elseif ($probot_credits < 500) {
            $error = 'Minimum 500 ProBot credits required';
        } elseif (empty($transfer_proof)) {
            $error = 'Transfer proof/description is required';
        } else {
            try {
                // Initialize database
                $db = initializeDatabase();
                
                // Calculate broadcast messages (500 ProBot credits = 1 broadcast message)
                $broadcast_messages = floor($probot_credits / 500);
                
                // Check if user exists, if not create them
                $stmt = $db->prepare("SELECT id, credits FROM users WHERE discord_id = ?");
                $stmt->execute([$sender_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    // Create new user
                    $stmt = $db->prepare("INSERT INTO users (discord_id, username, credits, created_at) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$sender_id, 'Unknown User', $broadcast_messages, date('Y-m-d H:i:s')]);
                    $user_id = $db->lastInsertId();
                    $old_credits = 0;
                } else {
                    // Update existing user
                    $user_id = $user['id'];
                    $old_credits = $user['credits'];
                    $new_credits = $old_credits + $broadcast_messages;
                    
                    $stmt = $db->prepare("UPDATE users SET credits = ?, updated_at = ? WHERE id = ?");
                    $stmt->execute([$new_credits, date('Y-m-d H:i:s'), $user_id]);
                }
                
                // Record transaction
                $description = "ProBot transfer: {$probot_credits} credits - {$transfer_proof}";
                $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount, probot_credits, description, created_at) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, 'credit', $broadcast_messages, $probot_credits, $description, date('Y-m-d H:i:s')]);
                
                // Record processed transfer to prevent duplicates
                $stmt = $db->prepare("INSERT INTO processed_transfers (discord_id, probot_credits, description, processed_at) VALUES (?, ?, ?, ?)");
                $stmt->execute([$sender_id, $probot_credits, $transfer_proof, date('Y-m-d H:i:s')]);
                
                $message = "✅ Payment processed successfully!<br>";
                $message .= "👤 User: {$sender_id}<br>";
                $message .= "💰 ProBot Credits: {$probot_credits}<br>";
                $message .= "📨 Broadcast Messages Added: {$broadcast_messages}<br>";
                $message .= "📝 Proof: {$transfer_proof}";
                
                // Clear form
                $_POST = [];
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get recent transactions for verification
try {
    $db = initializeDatabase();
    $stmt = $db->prepare("SELECT * FROM transactions WHERE type = 'credit' ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_transactions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Checker - Discord Broadcaster Pro</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .payment-form {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--input-bg);
            color: var(--text-primary);
            font-size: 14px;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        .payment-instructions {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .payment-instructions h4 {
            color: #1976d2;
            margin-bottom: 12px;
        }
        .recipient-info {
            background: #f3e5f5;
            border: 1px solid #9c27b0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            text-align: center;
        }
        .recipient-id {
            font-family: 'Courier New', monospace;
            font-size: 18px;
            font-weight: bold;
            color: #7b1fa2;
            background: white;
            padding: 8px 16px;
            border-radius: 6px;
            display: inline-block;
            margin: 8px 0;
        }
        .transaction-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .transaction-item {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .transaction-details h5 {
            margin: 0 0 4px 0;
            color: var(--text-primary);
        }
        .transaction-details p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 14px;
        }
        .transaction-amount {
            font-weight: bold;
            color: #4caf50;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="logo">
                    <i class="fab fa-discord"></i>
                    <h1>Payment Checker</h1>
                </div>
                <div class="user-section">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-home"></i>
                        Home
                    </a>
                    <a href="wallet.php" class="btn btn-info">
                        <i class="fas fa-wallet"></i>
                        Wallet
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Instructions -->
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-info-circle"></i> How to Process Payments</h2>
                </div>
                <div class="card-body">
                    <div class="payment-instructions">
                        <h4>📋 Instructions for Users:</h4>
                        <ol>
                            <li>Send ProBot credits to the recipient ID below</li>
                            <li>Take a screenshot of the successful transfer</li>
                            <li>Share the screenshot with server admin</li>
                            <li>Admin will process the payment using this form</li>
                        </ol>
                    </div>
                    
                    <div class="recipient-info">
                        <h4>💰 Payment Recipient</h4>
                        <p>Send ProBot credits to this Discord ID:</p>
                        <div class="recipient-id">675332512414695441</div>
                        <p><strong>Rate:</strong> 500 ProBot Credits = 1 Broadcast Message</p>
                    </div>
                </div>
            </section>

            <!-- Payment Processing Form -->
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-credit-card"></i> Process Payment</h2>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div><?php echo $message; ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                    <?php endif; ?>

                    <form method="POST" class="payment-form">
                        <input type="hidden" name="action" value="process_payment">
                        
                        <div class="form-group">
                            <label for="sender_id">
                                <i class="fab fa-discord"></i>
                                Sender Discord ID
                            </label>
                            <input type="text" id="sender_id" name="sender_id" 
                                   placeholder="123456789012345678" 
                                   value="<?php echo htmlspecialchars($_POST['sender_id'] ?? ''); ?>"
                                   required>
                            <small>The Discord ID of the person who sent the ProBot credits</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="probot_credits">
                                <i class="fas fa-coins"></i>
                                ProBot Credits Amount
                            </label>
                            <input type="number" id="probot_credits" name="probot_credits" 
                                   placeholder="5000" min="500" step="100"
                                   value="<?php echo htmlspecialchars($_POST['probot_credits'] ?? ''); ?>"
                                   required>
                            <small>Minimum 500 credits required (= 1 broadcast message)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="transfer_proof">
                                <i class="fas fa-file-alt"></i>
                                Transfer Proof/Description
                            </label>
                            <textarea id="transfer_proof" name="transfer_proof" 
                                      placeholder="Screenshot shows transfer of 5000 credits to 675332512414695441 at 10:20 AM"
                                      rows="3" required><?php echo htmlspecialchars($_POST['transfer_proof'] ?? ''); ?></textarea>
                            <small>Describe the proof you have (screenshot, message ID, etc.)</small>
                        </div>
                        
                        <button type="submit" class="btn btn-success btn-large">
                            <i class="fas fa-check"></i>
                            Process Payment
                        </button>
                    </form>
                </div>
            </section>

            <!-- Recent Transactions -->
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Recent Credit Additions</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_transactions)): ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <h3>No Recent Transactions</h3>
                        <p>Processed payments will appear here.</p>
                    </div>
                    <?php else: ?>
                    <div class="transaction-list">
                        <?php foreach ($recent_transactions as $transaction): ?>
                        <div class="transaction-item">
                            <div class="transaction-details">
                                <h5><?php echo htmlspecialchars($transaction['description']); ?></h5>
                                <p><?php echo date('M j, Y \a\t g:i A', strtotime($transaction['created_at'])); ?></p>
                            </div>
                            <div class="transaction-amount">
                                +<?php echo number_format($transaction['amount']); ?> messages
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script>
        // Auto-calculate broadcast messages
        document.getElementById('probot_credits').addEventListener('input', function() {
            const credits = parseInt(this.value) || 0;
            const messages = Math.floor(credits / 500);
            const small = this.parentNode.querySelector('small');
            if (messages > 0) {
                small.textContent = `Will give ${messages} broadcast message${messages !== 1 ? 's' : ''}`;
                small.style.color = '#4caf50';
            } else {
                small.textContent = 'Minimum 500 credits required (= 1 broadcast message)';
                small.style.color = '#f44336';
            }
        });
    </script>
</body>
</html>
