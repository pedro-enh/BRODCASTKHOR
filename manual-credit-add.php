<?php
/**
 * Manual Credit Addition Tool
 * For adding credits manually when automatic detection fails
 */

session_start();

// Load configuration and database
try {
    $config = require_once 'config.php';
    require_once 'database.php';
} catch (Exception $e) {
    die('Configuration or database error: ' . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['discord_user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['discord_user'];
$db = new Database();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_credits') {
        $discordId = $_POST['discord_id'] ?? '';
        $probotCredits = (int)($_POST['probot_credits'] ?? 0);
        $description = $_POST['description'] ?? '';
        
        if ($discordId && $probotCredits > 0) {
            try {
                // Calculate broadcast credits (500 ProBot credits = 1 broadcast message)
                $broadcastCredits = floor($probotCredits / 500);
                
                // Add credits to user account
                $db->addCredits(
                    $discordId,
                    $broadcastCredits,
                    $description ?: "Manual credit addition - {$probotCredits} ProBot credits",
                    'manual_' . time()
                );
                
                $success = "Successfully added {$broadcastCredits} broadcast credits for {$probotCredits} ProBot credits!";
            } catch (Exception $e) {
                $error = "Error adding credits: " . $e->getMessage();
            }
        } else {
            $error = "Please provide valid Discord ID and ProBot credits amount.";
        }
    }
}

// Get recent transactions for verification
$recentTransactions = $db->getAllTransactions(20);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Credit Addition - Discord Broadcaster Pro</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="app-container">
        <header class="header">
            <div class="header-content">
                <div class="logo">
                    <i class="fab fa-discord"></i>
                    <h1>Manual Credit Addition</h1>
                </div>
                <div class="user-section">
                    <a href="wallet.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Wallet
                    </a>
                </div>
            </div>
        </header>

        <main class="main-content">
            <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-times-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <!-- Manual Credit Addition Form -->
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-plus-circle"></i> Add Credits Manually</h2>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <h4>Emergency Credit Addition</h4>
                            <p>Use this tool when the automatic bot detection fails. Based on the screenshot, someone sent 5000 ProBot credits that weren't detected.</p>
                        </div>
                    </div>

                    <form method="POST" class="credit-form">
                        <input type="hidden" name="action" value="add_credits">
                        
                        <div class="form-group">
                            <label for="discord_id">Discord User ID</label>
                            <input type="text" id="discord_id" name="discord_id" 
                                   placeholder="e.g., 123456789012345678" required>
                            <small>The Discord ID of the user who sent the ProBot credits</small>
                        </div>

                        <div class="form-group">
                            <label for="probot_credits">ProBot Credits Sent</label>
                            <input type="number" id="probot_credits" name="probot_credits" 
                                   value="5000" min="500" step="500" required>
                            <small>Amount of ProBot credits that were sent (500 = 1 broadcast message)</small>
                        </div>

                        <div class="form-group">
                            <label for="description">Description (Optional)</label>
                            <input type="text" id="description" name="description" 
                                   placeholder="Manual addition for missed payment">
                            <small>Optional description for the transaction</small>
                        </div>

                        <div class="calculation-preview">
                            <h4>Credit Calculation:</h4>
                            <p><span id="probot-amount">5000</span> ProBot Credits = <span id="broadcast-amount">10</span> Broadcast Messages</p>
                        </div>

                        <button type="submit" class="btn btn-success btn-large">
                            <i class="fas fa-plus"></i>
                            Add Credits
                        </button>
                    </form>
                </div>
            </section>

            <!-- Quick Actions for Common Cases -->
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <button class="btn btn-primary" onclick="fillForm('<?php echo htmlspecialchars($user['id']); ?>', 5000)">
                            <i class="fas fa-user"></i>
                            Add 5000 Credits to My Account
                        </button>
                        
                        <button class="btn btn-info" onclick="fillForm('', 5000)">
                            <i class="fas fa-users"></i>
                            Add 5000 Credits to Another User
                        </button>
                        
                        <button class="btn btn-warning" onclick="fillForm('', 10000)">
                            <i class="fas fa-star"></i>
                            Add 10000 Credits (20 Messages)
                        </button>
                    </div>
                </div>
            </section>

            <!-- Recent Transactions -->
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Recent Transactions</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($recentTransactions)): ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <h3>No Transactions Yet</h3>
                        <p>Transaction history will appear here.</p>
                    </div>
                    <?php else: ?>
                    <div class="transaction-list">
                        <?php foreach ($recentTransactions as $transaction): ?>
                        <div class="transaction-item <?php echo $transaction['type']; ?>">
                            <div class="transaction-icon">
                                <?php if ($transaction['type'] === 'purchase'): ?>
                                    <i class="fas fa-plus-circle"></i>
                                <?php else: ?>
                                    <i class="fas fa-minus-circle"></i>
                                <?php endif; ?>
                            </div>
                            <div class="transaction-details">
                                <h4><?php echo htmlspecialchars($transaction['description']); ?></h4>
                                <p>User: <?php echo htmlspecialchars($transaction['discord_id']); ?></p>
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
        </main>
    </div>

    <script>
        // Update calculation preview
        document.getElementById('probot_credits').addEventListener('input', function() {
            const probotCredits = parseInt(this.value) || 0;
            const broadcastMessages = Math.floor(probotCredits / 500);
            
            document.getElementById('probot-amount').textContent = probotCredits.toLocaleString();
            document.getElementById('broadcast-amount').textContent = broadcastMessages;
        });

        // Quick action functions
        function fillForm(discordId, probotCredits) {
            document.getElementById('discord_id').value = discordId;
            document.getElementById('probot_credits').value = probotCredits;
            
            // Trigger calculation update
            document.getElementById('probot_credits').dispatchEvent(new Event('input'));
        }
    </script>
</body>
</html>
