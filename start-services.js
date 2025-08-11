const { spawn } = require('child_process');

console.log('🚀 Starting Discord Broadcaster Pro...');

const PORT = process.env.PORT || 8080;

// Start PHP web server
console.log(`🌐 Starting web server on port ${PORT}...`);
const phpServer = spawn('php', ['-S', `0.0.0.0:${PORT}`, 'index.php'], {
    stdio: 'inherit'
});

phpServer.on('error', (err) => {
    console.error('❌ PHP Server Error:', err);
    process.exit(1);
});

// Start Discord bot if token is available
const botToken = process.env.DISCORD_BOT_TOKEN;
if (botToken && botToken !== 'your_bot_token_here') {
    console.log('🤖 Starting Discord Bot...');
    
    setTimeout(() => {
        const botProcess = spawn('node', ['discord-bot.js'], {
            stdio: 'inherit',
            env: process.env
        });
        
        botProcess.on('error', (err) => {
            console.error('❌ Bot Error:', err);
        });
    }, 2000); // Start bot after 2 seconds
} else {
    console.log('⚠️ Discord Bot disabled (no token configured)');
    console.log('💡 Add DISCORD_BOT_TOKEN to environment variables to enable bot');
}

// Handle shutdown
process.on('SIGTERM', () => {
    console.log('🛑 Shutting down...');
    phpServer.kill();
    process.exit(0);
});

console.log('✅ Services started!');
