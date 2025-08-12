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
    <title>My Discord ID</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="app-container">
        <header class="header">
            <div class="header-content">
                <div class="logo">
                    <i class="fab fa-discord"></i>
                    <h1>Discord Broadcaster Pro</h1>
                </div>
                <div class="user-section">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-home"></i>
                        Back to Home
                    </a>
                </div>
            </div>
        </header>

        <main class="main-content">
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-id-card"></i> Your Discord Information</h2>
                </div>
                <div class="card-body">
                    <div class="user-info-display">
                        <div class="info-item">
                            <label>Username:</label>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user['username'] . '#' . $user['discriminator']); ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <label>Discord ID:</label>
                            <div class="info-value id-display">
                                <code><?php echo htmlspecialchars($user['id']); ?></code>
                                <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($user['id']); ?>')">
                                    <i class="fas fa-copy"></i>
                                    Copy
                                </button>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <h4>Need Admin Access?</h4>
                                <p>If you need admin privileges, send this Discord ID to the server administrator to add it to the admin list.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                // Change button text temporarily
                const button = event.target.closest('.copy-btn');
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i> Copied!';
                button.style.background = '#28a745';
                
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.style.background = '';
                }, 2000);
            }).catch(() => {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                
                const button = event.target.closest('.copy-btn');
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i> Copied!';
                button.style.background = '#28a745';
                
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.style.background = '';
                }, 2000);
            });
        }
    </script>

    <style>
        .user-info-display {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .info-item {
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .info-item label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #b9bbbe;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 16px;
            color: #ffffff;
        }
        
        .id-display {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .id-display code {
            background: rgba(0, 0, 0, 0.3);
            padding: 8px 12px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            flex: 1;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .copy-btn {
            background: #5865f2;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .copy-btn:hover {
            background: #4752c4;
            transform: translateY(-1px);
        }
        
        .copy-btn:active {
            transform: translateY(0);
        }
    </style>
</body>
</html>
