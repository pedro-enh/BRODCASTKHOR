<?php
// Start broadcast worker in background
// This file should be called via web request to start the worker

// Prevent timeout
set_time_limit(0);
ignore_user_abort(true);

// Check if worker is already running
$lockFile = 'worker.lock';

if (file_exists($lockFile)) {
    $pid = file_get_contents($lockFile);
    if ($pid && posix_kill($pid, 0)) {
        echo json_encode(['success' => false, 'message' => 'Worker is already running']);
        exit;
    } else {
        // Remove stale lock file
        unlink($lockFile);
    }
}

// Start worker in background
if (PHP_OS_FAMILY === 'Windows') {
    // Windows
    $command = 'start /B php broadcast-worker.php > worker.log 2>&1';
    pclose(popen($command, 'r'));
} else {
    // Linux/Unix
    $command = 'php broadcast-worker.php > worker.log 2>&1 & echo $!';
    $pid = shell_exec($command);
    
    if ($pid) {
        file_put_contents($lockFile, trim($pid));
    }
}

echo json_encode(['success' => true, 'message' => 'Worker started successfully']);
?>
