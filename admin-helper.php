<?php
/**
 * Admin Helper Functions
 * Functions to check admin permissions and manage admin access
 */

/**
 * Check if current logged-in user is an admin
 * @return bool
 */
function isAdmin() {
    if (!isset($_SESSION['discord_user'])) {
        return false;
    }
    
    $config = require_once 'config.php';
    $adminIds = $config['ADMIN_DISCORD_IDS'] ?? [];
    $currentUserId = $_SESSION['discord_user']['id'] ?? '';
    
    return in_array($currentUserId, $adminIds);
}

/**
 * Check if a specific Discord ID is an admin
 * @param string $discordId
 * @return bool
 */
function isAdminById($discordId) {
    $config = require_once 'config.php';
    $adminIds = $config['ADMIN_DISCORD_IDS'] ?? [];
    
    return in_array($discordId, $adminIds);
}

/**
 * Require admin access or redirect to login
 * @param string $redirectUrl Where to redirect if not admin
 */
function requireAdmin($redirectUrl = 'index.php') {
    if (!isAdmin()) {
        if (!isset($_SESSION['discord_user'])) {
            // Not logged in
            header('Location: login.php?error=admin_required');
        } else {
            // Logged in but not admin
            header('Location: ' . $redirectUrl . '?error=access_denied');
        }
        exit;
    }
}

/**
 * Get admin Discord IDs from config
 * @return array
 */
function getAdminIds() {
    $config = require_once 'config.php';
    return $config['ADMIN_DISCORD_IDS'] ?? [];
}

/**
 * Add a new admin Discord ID to config
 * @param string $discordId
 * @return bool
 */
function addAdmin($discordId) {
    // Note: This function would need to modify the config file
    // For security, it's better to manually add admin IDs to config.php
    return false;
}

/**
 * Get current user's admin status info
 * @return array
 */
function getAdminStatus() {
    $isAdmin = isAdmin();
    $user = $_SESSION['discord_user'] ?? null;
    
    return [
        'is_admin' => $isAdmin,
        'user_id' => $user['id'] ?? '',
        'username' => $user['username'] ?? '',
        'admin_ids' => getAdminIds()
    ];
}

/**
 * Display admin badge in UI
 * @return string HTML for admin badge
 */
function getAdminBadge() {
    if (!isAdmin()) {
        return '';
    }
    
    return '<span class="admin-badge"><i class="fas fa-crown"></i> Admin</span>';
}
?>
