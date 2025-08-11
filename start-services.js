const { spawn } = require('child_process');
const path = require('path');

console.log('🚀 Starting Discord Broadcaster Pro Services...');

// Start PHP web server
const phpServer = spawn('php', ['-S', `0.0.0.0:${process.env.PORT || 8080}`, '-t', '.', 'index.php'], {
    stdio: 'inherit',
    cwd: __dirname
});

phpServer.on('error', (err) => {
    console.error('❌ PHP Server Error:', err);
});

phpServer.on('exit', (code) => {
    console.log(`⚠️ PHP Server exited with code ${code}`);
});

// Start Discord bot (only if token is provided)
if (process.env.DISCORD_BOT_TOKEN && process.env.DISCORD_BOT_TOKEN !== 'your_bot_token_here') {
    console.log('🤖 Starting Discord Bot...');
    
    const discordBot = spawn('node', ['discord-bot.js'], {
        stdio: 'inherit',
        cwd: __dirname,
        env: { ...process.env }
    });

    discordBot.on('error', (err) => {
        console.error('❌ Discord Bot Error:', err);
    });

    discordBot.on('exit', (code) => {
        console.log(`⚠️ Discord Bot exited with code ${code}`);
    });
} else {
    console.log('⚠️ Discord Bot Token not configured - Bot will not start');
    console.log('💡 Add DISCORD_BOT_TOKEN to Railway environment variables to enable bot');
}

// Handle graceful shutdown
process.on('SIGTERM', () => {
    console.log('🛑 Received SIGTERM, shutting down gracefully...');
    phpServer.kill('SIGTERM');
    if (discordBot) {
        discordBot.kill('SIGTERM');
    }
    process.exit(0);
});

process.on('SIGINT', () => {
    console.log('🛑 Received SIGINT, shutting down gracefully...');
    phpServer.kill('SIGINT');
    if (discordBot) {
        discordBot.kill('SIGINT');
    }
    process.exit(0);
});

console.log('✅ Services started successfully!');
console.log('🌐 Web server running on port', process.env.PORT || 8080);
console.log('🤖 Discord bot status: ' + (process.env.DISCORD_BOT_TOKEN && process.env.DISCORD_BOT_TOKEN !== 'your_bot_token_here' ? 'Starting...' : 'Disabled (no token)'));
