// Discord Broadcaster Pro - Frontend JavaScript
let currentBotToken = '';
let currentGuildId = '';
let currentMembers = [];
let broadcastInProgress = false;

// DOM Elements
const botTokenInput = document.getElementById('botToken');
const toggleTokenBtn = document.getElementById('toggleToken');
const connectBtn = document.getElementById('connectBtn');
const serverSection = document.getElementById('serverSection');
const serverSelect = document.getElementById('serverSelect');
const memberCount = document.getElementById('memberCount');
const memberCountText = document.getElementById('memberCountText');
const messageSection = document.getElementById('messageSection');
const messageText = document.getElementById('messageText');
const charCount = document.getElementById('charCount');
const delaySlider = document.getElementById('delaySlider');
const delayValue = document.getElementById('delayValue');
const previewBtn = document.getElementById('previewBtn');
const sendBtn = document.getElementById('sendBtn');
const resultsSection = document.getElementById('resultsSection');
const loadingOverlay = document.getElementById('loadingOverlay');
const loadingText = document.getElementById('loadingText');
const loadingSubtext = document.getElementById('loadingSubtext');

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    updateCharacterCount();
    updateDelayValue();
    startWorkerIfNeeded();
});

function initializeEventListeners() {
    // Bot token toggle
    toggleTokenBtn.addEventListener('click', toggleTokenVisibility);
    
    // Connect button
    connectBtn.addEventListener('click', connectBot);
    
    // Server selection
    serverSelect.addEventListener('change', onServerChange);
    
    // Message input
    messageText.addEventListener('input', updateCharacterCount);
    
    // Delay slider
    delaySlider.addEventListener('input', updateDelayValue);
    
    // Preview and send buttons
    previewBtn.addEventListener('click', previewMessage);
    sendBtn.addEventListener('click', sendBroadcast);
    
    // Enter key in bot token field
    botTokenInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            connectBot();
        }
    });
}

function toggleTokenVisibility() {
    const isPassword = botTokenInput.type === 'password';
    botTokenInput.type = isPassword ? 'text' : 'password';
    toggleTokenBtn.innerHTML = isPassword ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
}

async function connectBot() {
    const token = botTokenInput.value.trim();
    
    if (!token) {
        showToast('Please enter a bot token', 'error');
        return;
    }
    
    if (!token.startsWith('Bot ') && !token.match(/^[A-Za-z0-9._-]+$/)) {
        showToast('Invalid bot token format', 'error');
        return;
    }
    
    currentBotToken = token;
    
    showLoading('Connecting to Discord...', 'Validating bot token and fetching servers');
    
    try {
        const response = await fetch('broadcast.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_guilds',
                bot_token: currentBotToken
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            populateServerList(data.guilds);
            showServerSection();
            showToast('Bot connected successfully!', 'success');
        } else {
            showToast(`Failed to connect: ${data.error}`, 'error');
        }
    } catch (error) {
        showToast('Network error occurred', 'error');
        console.error('Error:', error);
    } finally {
        hideLoading();
    }
}

function populateServerList(guilds) {
    serverSelect.innerHTML = '<option value="">Choose a server...</option>';
    
    guilds.forEach(guild => {
        const option = document.createElement('option');
        option.value = guild.id;
        option.textContent = guild.name;
        serverSelect.appendChild(option);
    });
}

function showServerSection() {
    serverSection.style.display = 'block';
    serverSection.scrollIntoView({ behavior: 'smooth' });
}

async function onServerChange() {
    const guildId = serverSelect.value;
    
    if (!guildId) {
        memberCount.style.display = 'none';
        messageSection.style.display = 'none';
        return;
    }
    
    currentGuildId = guildId;
    memberCountText.textContent = 'Loading members...';
    memberCount.style.display = 'block';
    
    try {
        const response = await fetch('broadcast.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_members',
                guild_id: guildId,
                bot_token: currentBotToken
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentMembers = data.members;
            memberCountText.innerHTML = `<strong>${data.members.length}</strong> members found`;
            showMessageSection();
        } else {
            memberCountText.textContent = `Error: ${data.error}`;
            showToast(`Failed to load members: ${data.error}`, 'error');
        }
    } catch (error) {
        memberCountText.textContent = 'Error loading members';
        showToast('Network error occurred', 'error');
        console.error('Error:', error);
    }
}

function showMessageSection() {
    messageSection.style.display = 'block';
    messageSection.scrollIntoView({ behavior: 'smooth' });
}

function updateCharacterCount() {
    const count = messageText.value.length;
    charCount.textContent = count;
    
    if (count > 1800) {
        charCount.style.color = '#e74c3c';
    } else if (count > 1500) {
        charCount.style.color = '#f39c12';
    } else {
        charCount.style.color = '#27ae60';
    }
}

function updateDelayValue() {
    const value = delaySlider.value;
    let label = `${value}s`;
    
    if (value <= 2) {
        label += ' (Fast)';
    } else if (value <= 5) {
        label += ' (Recommended)';
    } else {
        label += ' (Safe)';
    }
    
    delayValue.textContent = label;
}

function previewMessage() {
    const message = messageText.value.trim();
    
    if (!message) {
        showToast('Please enter a message first', 'error');
        return;
    }
    
    const enableMentions = document.getElementById('enableMentions').checked;
    let previewContent = message;
    
    if (enableMentions) {
        previewContent = previewContent.replace(/{user}/g, '@YourUsername');
        previewContent = previewContent.replace(/{username}/g, 'YourUsername');
    }
    
    document.getElementById('previewContent').innerHTML = `
        <div class="message-preview">
            <div class="message-header">
                <strong>Preview:</strong>
            </div>
            <div class="message-body">
                ${previewContent.replace(/\n/g, '<br>')}
            </div>
        </div>
    `;
    
    document.getElementById('previewModal').style.display = 'flex';
}

function closePreview() {
    document.getElementById('previewModal').style.display = 'none';
}

async function sendBroadcast() {
    if (broadcastInProgress) {
        showToast('A broadcast is already in progress', 'warning');
        return;
    }
    
    const message = messageText.value.trim();
    const targetType = document.querySelector('input[name="targetType"]:checked').value;
    const delay = parseInt(delaySlider.value);
    const enableMentions = document.getElementById('enableMentions').checked;
    
    // Validation
    if (!currentBotToken) {
        showToast('Please connect your bot first', 'error');
        return;
    }
    
    if (!currentGuildId) {
        showToast('Please select a server', 'error');
        return;
    }
    
    if (!message) {
        showToast('Please enter a message', 'error');
        return;
    }
    
    if (message.length > 2000) {
        showToast('Message is too long (max 2000 characters)', 'error');
        return;
    }
    
    // Confirm broadcast
    const memberCount = currentMembers.length;
    const confirmMessage = `Are you sure you want to send this message to ${memberCount} members?\n\nThis action cannot be undone.`;
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    broadcastInProgress = true;
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Broadcasting...';
    
    showLoading('Starting broadcast...', 'Preparing to send messages');
    
    try {
        const response = await fetch('broadcast.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'send_broadcast',
                guild_id: currentGuildId,
                message: message,
                target_type: targetType,
                delay: delay,
                enable_mentions: enableMentions,
                bot_token: currentBotToken
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (data.broadcast_id) {
                // Queue-based broadcast
                showToast('Broadcast queued successfully!', 'success');
                monitorBroadcastProgress(data.broadcast_id);
            } else {
                // Direct broadcast results
                hideLoading();
                showBroadcastResults(data);
                showToast(data.message || `Broadcast completed! Sent ${data.sent_count} messages.`, 'success');
            }
        } else {
            hideLoading();
            showToast(`Broadcast failed: ${data.error}`, 'error');
            resetBroadcastButton();
        }
    } catch (error) {
        showToast('Network error occurred', 'error');
        console.error('Error:', error);
        resetBroadcastButton();
    }
}

async function monitorBroadcastProgress(broadcastId) {
    const checkInterval = setInterval(async () => {
        try {
            const response = await fetch('broadcast.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_broadcast_status',
                    broadcast_id: broadcastId
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.broadcast) {
                const broadcast = data.broadcast;
                
                updateLoadingProgress(broadcast);
                
                if (broadcast.status === 'completed' || broadcast.status === 'failed') {
                    clearInterval(checkInterval);
                    hideLoading();
                    
                    if (broadcast.status === 'completed') {
                        showBroadcastResults({
                            sent_count: broadcast.sent_count,
                            failed_count: broadcast.failed_count,
                            total_targeted: broadcast.total_members
                        });
                    } else {
                        showToast(`Broadcast failed: ${broadcast.error_message}`, 'error');
                        resetBroadcastButton();
                    }
                }
            }
        } catch (error) {
            console.error('Error checking broadcast status:', error);
        }
    }, 2000); // Check every 2 seconds
}

function updateLoadingProgress(broadcast) {
    const progress = broadcast.total_members > 0 ? 
        Math.round((broadcast.sent_count + broadcast.failed_count) / broadcast.total_members * 100) : 0;
    
    loadingText.textContent = `Broadcasting... (${progress}%)`;
    loadingSubtext.textContent = `Sent: ${broadcast.sent_count} | Failed: ${broadcast.failed_count} | Total: ${broadcast.total_members}`;
    
    const progressBar = document.getElementById('progressBar');
    const progressFill = document.getElementById('progressFill');
    
    if (progressBar && progressFill) {
        progressBar.style.display = 'block';
        progressFill.style.width = `${progress}%`;
    }
}

function showBroadcastResults(results) {
    document.getElementById('sentCount').textContent = results.sent_count || 0;
    document.getElementById('failedCount').textContent = results.failed_count || 0;
    document.getElementById('totalCount').textContent = results.total_targeted || 0;
    
    // Show failed users if any
    if (results.failed_users && results.failed_users.length > 0) {
        const failedUsersList = document.getElementById('failedUsersList');
        const failedUsers = document.getElementById('failedUsers');
        
        failedUsers.innerHTML = '';
        results.failed_users.forEach(failure => {
            const item = document.createElement('div');
            item.className = 'failed-user-item';
            item.innerHTML = `
                <strong>${failure.user}</strong>
                <span class="failure-reason">${failure.reason}</span>
            `;
            failedUsers.appendChild(item);
        });
        
        failedUsersList.style.display = 'block';
    }
    
    resultsSection.style.display = 'block';
    resultsSection.scrollIntoView({ behavior: 'smooth' });
    
    resetBroadcastButton();
    broadcastInProgress = false;
    
    showToast(`Broadcast completed! Sent ${results.sent_count} messages.`, 'success');
}

function resetBroadcastButton() {
    sendBtn.disabled = false;
    sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Start Broadcasting';
}

function resetBroadcast() {
    // Reset form
    messageText.value = '';
    updateCharacterCount();
    
    // Reset selections
    document.querySelector('input[name="targetType"][value="all"]').checked = true;
    document.getElementById('enableMentions').checked = false;
    delaySlider.value = 2;
    updateDelayValue();
    
    // Hide sections
    resultsSection.style.display = 'none';
    
    // Scroll to message section
    messageSection.scrollIntoView({ behavior: 'smooth' });
    
    broadcastInProgress = false;
    resetBroadcastButton();
}

function showLoading(title, subtitle) {
    loadingText.textContent = title;
    loadingSubtext.textContent = subtitle;
    loadingOverlay.style.display = 'flex';
    
    // Reset progress bar
    const progressBar = document.getElementById('progressBar');
    const progressFill = document.getElementById('progressFill');
    
    if (progressBar && progressFill) {
        progressBar.style.display = 'none';
        progressFill.style.width = '0%';
    }
}

function hideLoading() {
    loadingOverlay.style.display = 'none';
}

function showToast(message, type = 'info') {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.toast');
    existingToasts.forEach(toast => toast.remove());
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const icon = type === 'success' ? 'check-circle' : 
                 type === 'error' ? 'times-circle' : 
                 type === 'warning' ? 'exclamation-triangle' : 'info-circle';
    
    toast.innerHTML = `
        <i class="fas fa-${icon}"></i>
        <span>${message}</span>
    `;
    
    document.getElementById('toastContainer').appendChild(toast);
    
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

// Utility functions
function formatNumber(num) {
    return new Intl.NumberFormat().format(num);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Start worker if needed
async function startWorkerIfNeeded() {
    try {
        const response = await fetch('start-worker.php');
        const data = await response.json();
        
        if (data.success) {
            console.log('Background worker started successfully');
        } else {
            console.log('Worker already running or failed to start');
        }
    } catch (error) {
        console.error('Failed to start worker:', error);
    }
}

// Export functions for global access
window.closePreview = closePreview;
window.resetBroadcast = resetBroadcast;
