<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['discord_user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['discord_user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Discord ID - Discord Broadcaster Pro</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .id-display {
            background: #f8f9fa;
            border: 2px solid #5865f2;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            margin: 2rem 0;
        }
        .discord-id {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 1.5rem;
            font-weight: bold;
            color: #5865f2;
            background: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            display: inline-block;
            margin: 1rem 0;
            border: 2px solid #5865f2;
            user-select: all;
        }
        .copy-btn {
            background: #5865f2;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            margin: 0.5rem;
            transition: all 0.3s ease;
        }
        .copy-btn:hover {
            background: #4752c4;
            transform: translateY(-1px);
        }
        .user-info {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid #5865f2;
        }
        .user-details h2 {
            margin: 0;
            color: #495057;
        }
        .user-details p {
            margin: 0.5rem 0 0 0;
            color: #6c757d;
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
                    <h1>My Discord ID</h1>
                </div>
                <div class="user-section">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-home"></i>
                        Home
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <section class="card">
                <div class="card-header">
                    <h2><i class="fab fa-discord"></i> Your Discord Information</h2>
                </div>
                <div class="card-body">
                    <div class="user-info">
                        <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="User Avatar" class="user-avatar">
                        <div class="user-details">
                            <h2><?php echo htmlspecialchars($user['username'] . '#' . $user['discriminator']); ?></h2>
                            <p>Discord User</p>
                        </div>
                    </div>
                    
                    <div class="id-display">
                        <h3>Your Discord ID:</h3>
                        <div class="discord-id" id="discordId"><?php echo htmlspecialchars($user['id']); ?></div>
                        <br>
                        <button class="copy-btn" onclick="copyId()">
                            <i class="fas fa-copy"></i>
                            Copy ID
                        </button>
                        <button class="copy-btn" onclick="copyConfigFormat()">
                            <i class="fas fa-code"></i>
                            Copy for Config
                        </button>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <h4>How to Use This ID</h4>
                            <p>To make yourself an admin, add this Discord ID to the <code>config.php</code> file:</p>
                            <pre><code>'ADMIN_DISCORD_IDS' => [
    '<?php echo htmlspecialchars($user['id']); ?>',  // Your ID
],</code></pre>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <h4>Admin Access</h4>
                            <p>Currently, you <?php 
                                require_once 'admin-helper.php';
                                echo isAdmin() ? '<strong style="color: #28a745;">HAVE</strong>' : '<strong style="color: #dc3545;">DO NOT HAVE</strong>';
                            ?> admin access.</p>
                            <?php if (!isAdmin()): ?>
                            <p>To get admin access, add your Discord ID to the config file and redeploy the application.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        function copyId() {
            const id = document.getElementById('discordId').textContent;
            navigator.clipboard.writeText(id).then(function() {
                showSuccess('Discord ID copied to clipboard!');
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
                alert('ID: ' + id);
            });
        }
        
        function copyConfigFormat() {
            const id = document.getElementById('discordId').textContent;
            const configText = `'${id}',  // Your Discord ID`;
            navigator.clipboard.writeText(configText).then(function() {
                showSuccess('Config format copied to clipboard!');
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
                alert('Config format: ' + configText);
            });
        }
        
        function showSuccess(message) {
            // Create toast notification
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #28a745;
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                font-weight: 500;
            `;
            toast.innerHTML = `<i class="fas fa-check"></i> ${message}`;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 3000);
        }
    </script>
</body>
</html>
