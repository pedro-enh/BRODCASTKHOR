<?php
require_once 'admin-helper.php';
require_once 'database.php';

session_start();

// Check if user is admin
if (!isAdmin()) {
    if (!isset($_SESSION['discord_user'])) {
        header('Location: index.php?error=login_required');
    } else {
        header('Location: index.php?error=access_denied');
    }
    exit;
}

$user = $_SESSION['discord_user'];

// Initialize database
$db = new Database();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'add_credits') {
        $targetUserId = trim($_POST['user_id'] ?? '');
        $creditsAmount = intval($_POST['credits'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Admin credit addition');
        
        // Validation
        if (empty($targetUserId)) {
            echo json_encode(['success' => false, 'message' => 'Discord ID is required']);
            exit;
        }
        
        if (!preg_match('/^\d{17,19}$/', $targetUserId)) {
            echo json_encode(['success' => false, 'message' => 'Invalid Discord ID format']);
            exit;
        }
        
        if ($creditsAmount < 1 || $creditsAmount > 10000) {
            echo json_encode(['success' => false, 'message' => 'Credits must be between 1 and 10,000']);
            exit;
        }
        
        try {
            $pdo = $db->getPdo();
            
            // Check if user exists
            $targetUser = $db->getUserByDiscordId($targetUserId);
            
            if ($targetUser) {
                // Update existing user
                $newBalance = $targetUser['credits'] + $creditsAmount;
                $stmt = $pdo->prepare("UPDATE users SET credits = ?, updated_at = CURRENT_TIMESTAMP WHERE discord_id = ?");
                $stmt->execute([$newBalance, $targetUserId]);
            } else {
                // Create new user with minimal data
                $stmt = $pdo->prepare("INSERT INTO users (discord_id, username, discriminator, credits, created_at, updated_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                $stmt->execute([$targetUserId, 'Unknown', '0000', $creditsAmount]);
                $newBalance = $creditsAmount;
                $targetUser = ['id' => $pdo->lastInsertId()];
            }
            
            // Record transaction
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, discord_id, type, amount, description, status, created_at) VALUES (?, ?, 'purchase', ?, ?, 'completed', CURRENT_TIMESTAMP)");
            $stmt->execute([$targetUser['id'], $targetUserId, $creditsAmount, "Admin: " . $reason]);
            
            echo json_encode([
                'success' => true, 
                'message' => "Successfully added {$creditsAmount} credits to user {$targetUserId}",
                'new_balance' => $newBalance
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'get_user_info') {
        $targetUserId = trim($_POST['user_id'] ?? '');
        
        if (empty($targetUserId) || !preg_match('/^\d{17,19}$/', $targetUserId)) {
            echo json_encode(['success' => false, 'message' => 'Invalid Discord ID']);
            exit;
        }
        
        try {
            $targetUser = $db->getUserByDiscordId($targetUserId);
            
            if ($targetUser) {
                echo json_encode([
                    'success' => true,
                    'user' => [
                        'discord_id' => $targetUser['discord_id'],
                        'credits' => $targetUser['credits'],
                        'created_at' => $targetUser['created_at'],
                        'updated_at' => $targetUser['updated_at']
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'user' => null,
                    'message' => 'User not found in database'
                ]);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
}

// Get recent transactions for admin view
try {
    $pdo = $db->getPdo();
    $stmt = $pdo->prepare("SELECT t.*, u.discord_id as user_discord_id FROM transactions t LEFT JOIN users u ON t.user_id = u.id WHERE t.type = 'purchase' AND t.description LIKE 'Admin:%' ORDER BY t.created_at DESC LIMIT 20");
    $stmt->execute();
    $recentTransactions = $stmt->fetchAll();
} catch (Exception $e) {
    $recentTransactions = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Access - Discord Broadcaster Pro</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #333;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .admin-header h1 {
            margin: 0;
            font-size: 2.5em;
        }
        
        .admin-header .admin-badge {
            background: #333;
            color: #ffd700;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            margin-top: 10px;
            display: inline-block;
        }
        
        .admin-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .admin-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 2px solid #ffd700;
        }
        
        .admin-card h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #ffd700;
        }
        
        .btn-admin {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #333;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        
        .btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
        }
        
        .btn-admin:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-top: 10px;
        }
        
        .user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
        }
        
        .user-info.show {
            display: block;
        }
        
        .transactions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .transactions-table th,
        .transactions-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .transactions-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.3s;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast.success {
            background: #28a745;
        }
        
        .toast.error {
            background: #dc3545;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #333;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .admin-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1><i class="fas fa-crown"></i> Admin Access Panel</h1>
            <div class="admin-badge">
                <i class="fas fa-user-shield"></i> 
                <?php echo htmlspecialchars($user['username'] . '#' . $user['discriminator']); ?>
            </div>
        </div>
        
        <div class="admin-grid">
            <!-- Add Credits Section -->
            <div class="admin-card">
                <h3><i class="fas fa-coins"></i> Add Credits to User</h3>
                
                <form id="addCreditsForm">
                    <div class="form-group">
                        <label for="targetUserId">Discord User ID:</label>
                        <input type="text" id="targetUserId" name="user_id" placeholder="Enter Discord ID (17-19 digits)" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="creditsAmount">Credits Amount:</label>
                        <input type="number" id="creditsAmount" name="credits" min="1" max="10000" placeholder="Enter credits amount" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reason">Reason (Optional):</label>
                        <textarea id="reason" name="reason" rows="3" placeholder="Enter reason for adding credits"></textarea>
                    </div>
                    
                    <button type="button" class="btn-admin btn-secondary" onclick="getUserInfo()">
                        <i class="fas fa-search"></i> Check User Info
                    </button>
                    
                    <button type="submit" class="btn-admin">
                        <i class="fas fa-plus"></i> Add Credits
                    </button>
                </form>
                
                <div id="userInfo" class="user-info">
                    <!-- User info will be displayed here -->
                </div>
            </div>
            
            <!-- Quick Actions Section -->
            <div class="admin-card">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                
                <button class="btn-admin" onclick="addQuickCredits(100)">
                    <i class="fas fa-plus"></i> Add 100 Credits
                </button>
                
                <button class="btn-admin" onclick="addQuickCredits(500)" style="margin-top: 10px;">
                    <i class="fas fa-plus"></i> Add 500 Credits
                </button>
                
                <button class="btn-admin" onclick="addQuickCredits(1000)" style="margin-top: 10px;">
                    <i class="fas fa-plus"></i> Add 1000 Credits
                </button>
                
                <button class="btn-admin btn-secondary" onclick="window.location.href='wallet.php'" style="margin-top: 20px;">
                    <i class="fas fa-wallet"></i> Go to Wallet
                </button>
                
                <button class="btn-admin btn-secondary" onclick="window.location.href='payment-checker.php'" style="margin-top: 10px;">
                    <i class="fas fa-chart-line"></i> Payment Checker
                </button>
            </div>
        </div>
        
        <!-- Recent Transactions -->
        <div class="admin-card">
            <h3><i class="fas fa-history"></i> Recent Admin Transactions</h3>
            
            <?php if (empty($recentTransactions)): ?>
                <p>No admin transactions found.</p>
            <?php else: ?>
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User ID</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTransactions as $transaction): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i', strtotime($transaction['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($transaction['discord_id']); ?></td>
                                <td>+<?php echo number_format($transaction['amount']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['description'] ?? 'Admin credit addition'); ?></td>
                                <td>Admin</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Add Credits Form Handler
        document.getElementById('addCreditsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const userId = document.getElementById('targetUserId').value.trim();
            const credits = parseInt(document.getElementById('creditsAmount').value);
            const reason = document.getElementById('reason').value.trim();
            
            if (!userId || !credits) {
                showToast('Please fill in all required fields', 'error');
                return;
            }
            
            if (!/^\d{17,19}$/.test(userId)) {
                showToast('Invalid Discord ID format', 'error');
                return;
            }
            
            if (credits < 1 || credits > 10000) {
                showToast('Credits must be between 1 and 10,000', 'error');
                return;
            }
            
            addCredits(userId, credits, reason);
        });
        
        // Add Credits Function
        function addCredits(userId, credits, reason) {
            const submitBtn = document.querySelector('#addCreditsForm button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading"></span> Adding Credits...';
            
            fetch('admin-access.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add_credits&user_id=${encodeURIComponent(userId)}&credits=${credits}&reason=${encodeURIComponent(reason)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    document.getElementById('addCreditsForm').reset();
                    document.getElementById('userInfo').classList.remove('show');
                    
                    // Refresh page after 2 seconds to show updated transactions
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Network error occurred', 'error');
                console.error('Error:', error);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        }
        
        // Quick Add Credits
        function addQuickCredits(amount) {
            const userId = document.getElementById('targetUserId').value.trim();
            
            if (!userId) {
                showToast('Please enter a Discord ID first', 'error');
                document.getElementById('targetUserId').focus();
                return;
            }
            
            if (!/^\d{17,19}$/.test(userId)) {
                showToast('Invalid Discord ID format', 'error');
                return;
            }
            
            addCredits(userId, amount, `Quick add ${amount} credits`);
        }
        
        // Get User Info
        function getUserInfo() {
            const userId = document.getElementById('targetUserId').value.trim();
            
            if (!userId) {
                showToast('Please enter a Discord ID', 'error');
                return;
            }
            
            if (!/^\d{17,19}$/.test(userId)) {
                showToast('Invalid Discord ID format', 'error');
                return;
            }
            
            fetch('admin-access.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_user_info&user_id=${encodeURIComponent(userId)}`
            })
            .then(response => response.json())
            .then(data => {
                const userInfoDiv = document.getElementById('userInfo');
                
                if (data.success) {
                    if (data.user) {
                        userInfoDiv.innerHTML = `
                            <h4><i class="fas fa-user"></i> User Information</h4>
                            <p><strong>Discord ID:</strong> ${data.user.discord_id}</p>
                            <p><strong>Current Credits:</strong> ${data.user.credits}</p>
                            <p><strong>Account Created:</strong> ${new Date(data.user.created_at).toLocaleString()}</p>
                            <p><strong>Last Updated:</strong> ${new Date(data.user.updated_at).toLocaleString()}</p>
                        `;
                    } else {
                        userInfoDiv.innerHTML = `
                            <h4><i class="fas fa-user-plus"></i> New User</h4>
                            <p>This Discord ID is not in the database yet. Adding credits will create a new account.</p>
                        `;
                    }
                    userInfoDiv.classList.add('show');
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Network error occurred', 'error');
                console.error('Error:', error);
            });
        }
        
        // Toast Notification
        function showToast(message, type) {
            // Remove existing toasts
            const existingToasts = document.querySelectorAll('.toast');
            existingToasts.forEach(toast => toast.remove());
            
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            // Show toast
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            // Hide toast after 4 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 4000);
        }
    </script>
</body>
</html>
