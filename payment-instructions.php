<?php
/**
 * Payment Instructions Page
 * Simple page showing users how to pay for credits
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>How to Pay - Discord Broadcaster Pro</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .payment-step {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border-left: 4px solid var(--primary-color);
        }
        .step-number {
            background: var(--primary-color);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 16px;
        }
        .command-box {
            background: #2d3748;
            color: #e2e8f0;
            padding: 16px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            margin: 12px 0;
            position: relative;
        }
        .copy-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .copy-btn:hover {
            background: var(--primary-hover);
        }
        .recipient-highlight {
            background: #f3e5f5;
            border: 2px solid #9c27b0;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            margin: 24px 0;
        }
        .recipient-id {
            font-family: 'Courier New', monospace;
            font-size: 24px;
            font-weight: bold;
            color: #7b1fa2;
            background: white;
            padding: 12px 24px;
            border-radius: 8px;
            display: inline-block;
            margin: 12px 0;
            border: 2px solid #9c27b0;
        }
        .pricing-table {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 24px 0;
        }
        .pricing-card {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .pricing-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-4px);
        }
        .pricing-card.popular {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, var(--card-bg) 0%, rgba(var(--primary-rgb), 0.1) 100%);
        }
        .pricing-card h3 {
            color: var(--primary-color);
            margin-bottom: 16px;
        }
        .price {
            font-size: 32px;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        .price-unit {
            color: var(--text-secondary);
            font-size: 14px;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 16px;
            margin: 24px 0;
        }
        .warning-box h4 {
            color: #856404;
            margin-bottom: 8px;
        }
        .warning-box p {
            color: #856404;
            margin: 0;
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
                    <h1>Payment Instructions</h1>
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
            <!-- Payment Recipient -->
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-credit-card"></i> Payment Information</h2>
                </div>
                <div class="card-body">
                    <div class="recipient-highlight">
                        <h3>💰 Send ProBot Credits To:</h3>
                        <div class="recipient-id">675332512414695441</div>
                        <p><strong>Rate:</strong> 500 ProBot Credits = 1 Broadcast Message</p>
                    </div>
                </div>
            </section>

            <!-- Pricing -->
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-tags"></i> Pricing Packages</h2>
                </div>
                <div class="card-body">
                    <div class="pricing-table">
                        <div class="pricing-card">
                            <h3>Starter</h3>
                            <div class="price">2,500</div>
                            <div class="price-unit">ProBot Credits</div>
                            <hr>
                            <p><strong>5 Broadcast Messages</strong></p>
                            <p>Perfect for small announcements</p>
                        </div>
                        
                        <div class="pricing-card popular">
                            <h3>Popular</h3>
                            <div class="price">5,000</div>
                            <div class="price-unit">ProBot Credits</div>
                            <hr>
                            <p><strong>10 Broadcast Messages</strong></p>
                            <p>Most popular choice</p>
                        </div>
                        
                        <div class="pricing-card">
                            <h3>Pro</h3>
                            <div class="price">10,000</div>
                            <div class="price-unit">ProBot Credits</div>
                            <hr>
                            <p><strong>20 Broadcast Messages</strong></p>
                            <p>For heavy broadcasters</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Step by Step Instructions -->
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-list-ol"></i> How to Pay (Step by Step)</h2>
                </div>
                <div class="card-body">
                    <div class="payment-step">
                        <div class="step-number">1</div>
                        <h3>Open Discord</h3>
                        <p>Go to any Discord server that has ProBot installed.</p>
                    </div>

                    <div class="payment-step">
                        <div class="step-number">2</div>
                        <h3>Use the Credits Command</h3>
                        <p>Type the following command in any channel:</p>
                        <div class="command-box">
                            #credits 675332512414695441 5000
                            <button class="copy-btn" onclick="copyCommand('#credits 675332512414695441 5000')">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                        <p><small>Replace "5000" with the amount you want to send</small></p>
                    </div>

                    <div class="payment-step">
                        <div class="step-number">3</div>
                        <h3>Confirm the Transfer</h3>
                        <p>ProBot will ask you to confirm the transfer. Click "Yes" or type "yes".</p>
                    </div>

                    <div class="payment-step">
                        <div class="step-number">4</div>
                        <h3>Wait for Processing</h3>
                        <p>Your credits will be added to your wallet automatically within 1-2 minutes.</p>
                        <p>If not processed automatically, contact the server admin with a screenshot.</p>
                    </div>
                </div>
            </section>

            <!-- Important Notes -->
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-exclamation-triangle"></i> Important Notes</h2>
                </div>
                <div class="card-body">
                    <div class="warning-box">
                        <h4>⚠️ Bot Currently Offline</h4>
                        <p>Our Discord bot is temporarily offline. Payments will be processed manually by server admins. Please take a screenshot of your successful transfer and contact an admin.</p>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <h4>Payment Processing</h4>
                            <ul>
                                <li>Minimum payment: 500 ProBot Credits (= 1 broadcast message)</li>
                                <li>Credits are non-refundable once processed</li>
                                <li>Only send to the exact ID: <code>675332512414695441</code></li>
                                <li>Keep a screenshot of your transfer for verification</li>
                            </ul>
                        </div>
                    </div>

                    <div class="alert alert-success">
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <h4>Safe & Secure</h4>
                            <p>All payments are processed through Discord's official ProBot system. Your Discord account and credits are completely safe.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Contact Support -->
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-headset"></i> Need Help?</h2>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-question-circle"></i>
                        <div>
                            <h4>Payment Issues?</h4>
                            <p>If your payment isn't processed automatically:</p>
                            <ol>
                                <li>Take a screenshot of the successful transfer</li>
                                <li>Contact a server admin</li>
                                <li>Provide your Discord ID and transfer amount</li>
                                <li>Admin will process your payment manually</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        function copyCommand(command) {
            navigator.clipboard.writeText(command).then(function() {
                // Show success message
                const btn = event.target.closest('.copy-btn');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                btn.style.background = '#4caf50';
                
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.background = '';
                }, 2000);
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
                alert('Command: ' + command);
            });
        }

        // Add click handlers to all copy buttons
        document.addEventListener('DOMContentLoaded', function() {
            const copyBtns = document.querySelectorAll('.copy-btn');
            copyBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const commandBox = this.parentNode;
                    const command = commandBox.textContent.trim().replace('Copy', '').trim();
                    copyCommand(command);
                });
            });
        });
    </script>
</body>
</html>
