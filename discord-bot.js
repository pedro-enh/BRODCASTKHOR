const { Client, GatewayIntentBits, EmbedBuilder } = require('discord.js');
const sqlite3 = require('sqlite3').verbose();
const path = require('path');

// Load environment variables
require('dotenv').config();

class DiscordBroadcasterBot {
    constructor() {
        this.client = new Client({
            intents: [
                GatewayIntentBits.Guilds,
                GatewayIntentBits.GuildMessages,
                GatewayIntentBits.MessageContent,
                GatewayIntentBits.DirectMessages
            ]
        });

        this.channelId = '1319029928825589780'; // ProBot credit channel
        this.recipientId = '675332512414695441'; // Your service recipient ID
        this.probotId = '282859044593598464'; // ProBot ID
        
        this.db = new sqlite3.Database('broadcaster.db');
        this.setupDatabase();
        this.setupEventHandlers();
    }

    setupDatabase() {
        // Create tables if they don't exist
        this.db.serialize(() => {
            this.db.run(`
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    discord_id TEXT UNIQUE NOT NULL,
                    username TEXT NOT NULL,
                    discriminator TEXT NOT NULL,
                    avatar TEXT,
                    email TEXT,
                    credits INTEGER DEFAULT 0,
                    total_spent INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            `);

            this.db.run(`
                CREATE TABLE IF NOT EXISTS transactions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    discord_id TEXT NOT NULL,
                    type TEXT NOT NULL,
                    amount INTEGER NOT NULL,
                    description TEXT,
                    probot_transaction_id TEXT,
                    status TEXT DEFAULT 'pending',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            `);

            this.db.run(`
                CREATE TABLE IF NOT EXISTS processed_transfers (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    message_id TEXT UNIQUE NOT NULL,
                    discord_id TEXT NOT NULL,
                    amount INTEGER NOT NULL,
                    processed_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            `);
        });
    }

    setupEventHandlers() {
        this.client.once('ready', () => {
            console.log(`🤖 Bot is online! Logged in as ${this.client.user.tag}`);
            console.log(`📡 Monitoring channel: ${this.channelId}`);
            console.log(`💰 Recipient ID: ${this.recipientId}`);
            
            // Set bot status
            this.client.user.setActivity('ProBot transfers', { type: 'WATCHING' });
        });

        this.client.on('messageCreate', async (message) => {
            try {
                await this.handleMessage(message);
            } catch (error) {
                console.error('❌ Error handling message:', error);
            }
        });

        this.client.on('error', (error) => {
            console.error('❌ Discord client error:', error);
        });

        this.client.on('disconnect', () => {
            console.log('⚠️ Bot disconnected');
        });

        this.client.on('reconnecting', () => {
            console.log('🔄 Bot reconnecting...');
        });
    }

    async handleMessage(message) {
        // Skip if message is from bot itself
        if (message.author.bot && message.author.id === this.client.user.id) {
            return;
        }

        // Handle commands
        if (message.content.startsWith('!addcredits')) {
            await this.handleAddCreditsCommand(message);
            return;
        }

        // Handle ProBot transfers
        if (message.author.id === this.probotId && message.channel.id === this.channelId) {
            await this.handleProbotTransfer(message);
        }
    }

    async handleAddCreditsCommand(message) {
        console.log(`🔧 Processing !addcredits command from ${message.author.tag}`);

        // Check if user is server owner
        const guild = message.guild;
        if (!guild || guild.ownerId !== message.author.id) {
            await message.reply({
                content: "❌ **Access Denied**\nOnly the server owner can use this command."
            });
            return;
        }

        // Parse command: !addcredits <discord_id> <probot_credits> [description]
        const args = message.content.split(' ');
        
        if (args.length < 3) {
            await message.reply({
                content: "❌ **Invalid Usage**\n\n**Correct format:**\n`!addcredits <discord_id> <probot_credits> [description]`\n\n**Example:**\n`!addcredits 123456789012345678 5000 Manual payment addition`"
            });
            return;
        }

        const targetDiscordId = args[1];
        const probotCredits = parseInt(args[2]);
        const description = args.slice(3).join(' ') || "Manual credit addition by server owner";

        // Validate inputs
        if (!/^\d{17,19}$/.test(targetDiscordId)) {
            await message.reply({
                content: "❌ **Invalid Discord ID**\nPlease provide a valid Discord user ID (17-19 digits)."
            });
            return;
        }

        if (probotCredits < 500 || probotCredits % 500 !== 0) {
            await message.reply({
                content: "❌ **Invalid Amount**\nProBot credits must be at least 500 and divisible by 500.\n\n**Valid amounts:** 500, 1000, 1500, 2000, 2500, 5000, 10000, etc."
            });
            return;
        }

        try {
            // Calculate broadcast credits
            const broadcastCredits = Math.floor(probotCredits / 500);
            
            // Add credits to user account
            await this.addCreditsToUser(targetDiscordId, broadcastCredits, description, `manual_${Date.now()}_${message.author.id}`);
            
            // Send success message
            const embed = new EmbedBuilder()
                .setTitle('✅ Credits Added Successfully')
                .setDescription(`**Target User:** <@${targetDiscordId}>\n**ProBot Credits:** ${probotCredits.toLocaleString()}\n**Broadcast Messages:** ${broadcastCredits}\n**Description:** ${description}`)
                .setColor(0x00ff00)
                .setFooter({ text: `Added by server owner • ${new Date().toLocaleString()}` });

            await message.reply({ embeds: [embed] });
            
            // Send DM to target user
            try {
                const targetUser = await this.client.users.fetch(targetDiscordId);
                await targetUser.send({
                    content: `✅ **Credits Added to Your Account!**\n\n💰 **Received:** ${probotCredits.toLocaleString()} ProBot Credits\n📨 **Added:** ${broadcastCredits} Broadcast Messages\n\n🎉 Your credits have been added by the server owner!\n🌐 Visit: https://discord-brodcast.up.railway.app/wallet.php`
                });
            } catch (dmError) {
                console.log(`⚠️ Could not send DM to user ${targetDiscordId}:`, dmError.message);
            }
            
            console.log(`✅ Successfully added ${broadcastCredits} credits for user ${targetDiscordId}`);
            
        } catch (error) {
            console.error('❌ Failed to add credits:', error);
            
            await message.reply({
                content: `❌ **Error Adding Credits**\n\n\`\`\`\n${error.message}\n\`\`\`\n\nPlease make sure the user has registered on the website first.`
            });
        }
    }

    async handleProbotTransfer(message) {
        console.log('📨 Processing ProBot message...');
        
        const transferData = this.parseTransferMessage(message);
        
        if (transferData) {
            console.log(`💰 Found transfer: ${transferData.amount} credits to ${transferData.recipientId}`);
            await this.processTransfer(transferData, message);
        }
    }

    parseTransferMessage(message) {
        const content = message.content;
        const embeds = message.embeds;
        
        // ProBot transfer patterns
        const patterns = [
            /✅.*transferred\s+(\d+)\s+credits?\s+to\s+<@(\d+)>/i,
            /successfully\s+transferred\s+(\d+)\s+credits?\s+to\s+<@(\d+)>/i,
            /تم\s+تحويل\s+(\d+)\s+.*إلى\s+<@(\d+)>/i,
            /<@(\d+)>\s+has\s+transferred\s+(\d+)\s+credits?\s+to\s+<@(\d+)>/i
        ];
        
        // Check message content
        for (const pattern of patterns) {
            const match = content.match(pattern);
            if (match) {
                return {
                    amount: parseInt(match[1] || match[2]),
                    recipientId: match[2] || match[3],
                    senderId: this.extractSenderId(content, message)
                };
            }
        }
        
        // Check embeds
        for (const embed of embeds) {
            const description = embed.description || '';
            for (const pattern of patterns) {
                const match = description.match(pattern);
                if (match) {
                    return {
                        amount: parseInt(match[1] || match[2]),
                        recipientId: match[2] || match[3],
                        senderId: this.extractSenderId(description, message)
                    };
                }
            }
        }
        
        return null;
    }

    extractSenderId(content, message) {
        // Try to find sender mention in the message
        const senderMatch = content.match(/<@(\d+)>.*transferred|من\s+<@(\d+)>/);
        if (senderMatch) {
            return senderMatch[1] || senderMatch[2];
        }
        
        // If not found, try to get from message context
        return null;
    }

    async processTransfer(transferData, message) {
        const { amount, recipientId, senderId } = transferData;
        const messageId = message.id;
        
        // Check if recipient is our service
        if (recipientId !== this.recipientId) {
            console.log(`ℹ️ Transfer not for our service: recipient ${recipientId}`);
            return;
        }
        
        // Check if transfer was already processed
        if (await this.isTransferProcessed(messageId)) {
            console.log(`⚠️ Transfer already processed: ${messageId}`);
            return;
        }
        
        if (!senderId) {
            console.log('⚠️ Could not determine sender ID');
            return;
        }
        
        try {
            // Calculate credits to add (500 ProBot credits = 1 broadcast message)
            const broadcastCredits = Math.floor(amount / 500);
            
            // Add credits to user account
            await this.addCreditsToUser(
                senderId,
                broadcastCredits,
                `ProBot credit transfer - ${amount} ProBot credits`,
                messageId
            );
            
            // Mark transfer as processed
            await this.markTransferProcessed(messageId, senderId, amount);
            
            // Send confirmation message to user
            await this.sendConfirmationMessage(senderId, amount, broadcastCredits);
            
            console.log(`✅ Successfully processed transfer: ${amount} ProBot credits → ${broadcastCredits} broadcast messages for user ${senderId}`);
            
        } catch (error) {
            console.error('❌ Failed to process transfer:', error);
            await this.sendErrorMessage(senderId, error.message);
        }
    }

    async addCreditsToUser(discordId, amount, description, transactionId) {
        const self = this;
        return new Promise((resolve, reject) => {
            self.db.serialize(() => {
                self.db.run('BEGIN TRANSACTION');
                
                // Get or create user
                self.db.get('SELECT * FROM users WHERE discord_id = ?', [discordId], (err, user) => {
                    if (err) {
                        self.db.run('ROLLBACK');
                        return reject(err);
                    }
                    
                    if (!user) {
                        // Create basic user record
                        self.db.run(
                            'INSERT INTO users (discord_id, username, discriminator) VALUES (?, ?, ?)',
                            [discordId, `User_${discordId.slice(-4)}`, '0000'],
                            function(err) {
                                if (err) {
                                    self.db.run('ROLLBACK');
                                    return reject(err);
                                }
                                
                                const userId = this.lastID;
                                addCreditsAndTransaction(userId);
                            }
                        );
                    } else {
                        addCreditsAndTransaction(user.id);
                    }
                });
                
                const addCreditsAndTransaction = (userId) => {
                    // Update user credits
                    self.db.run(
                        'UPDATE users SET credits = credits + ?, updated_at = CURRENT_TIMESTAMP WHERE discord_id = ?',
                        [amount, discordId],
                        (err) => {
                            if (err) {
                                self.db.run('ROLLBACK');
                                return reject(err);
                            }
                            
                            // Record transaction
                            self.db.run(
                                'INSERT INTO transactions (user_id, discord_id, type, amount, description, probot_transaction_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)',
                                [userId, discordId, 'purchase', amount, description, transactionId, 'completed'],
                                (err) => {
                                    if (err) {
                                        self.db.run('ROLLBACK');
                                        return reject(err);
                                    }
                                    
                                    self.db.run('COMMIT', (err) => {
                                        if (err) {
                                            return reject(err);
                                        }
                                        resolve();
                                    });
                                }
                            );
                        }
                    );
                };
            });
        });
    }

    async sendConfirmationMessage(userId, probotCredits, broadcastCredits) {
        try {
            const user = await this.client.users.fetch(userId);
            await user.send({
                content: `✅ **Payment Successful!**\n\n💰 **Received:** ${probotCredits.toLocaleString()} ProBot Credits\n📨 **Added:** ${broadcastCredits} Broadcast Messages\n\n🎉 Your credits have been added to your wallet!\n🌐 Visit: https://discord-brodcast.up.railway.app/wallet.php`
            });
            console.log(`📤 Sent confirmation message to user ${userId}`);
        } catch (error) {
            console.log(`⚠️ Could not send confirmation to user ${userId}:`, error.message);
        }
    }

    async sendErrorMessage(userId, error) {
        try {
            const user = await this.client.users.fetch(userId);
            await user.send({
                content: `❌ **Payment Processing Error**\n\nThere was an issue processing your payment:\n\`${error}\`\n\nPlease contact support or try again.`
            });
        } catch (dmError) {
            console.log(`⚠️ Could not send error message to user ${userId}:`, dmError.message);
        }
    }

    async isTransferProcessed(messageId) {
        const self = this;
        return new Promise((resolve, reject) => {
            self.db.get(
                'SELECT id FROM processed_transfers WHERE message_id = ?',
                [messageId],
                (err, row) => {
                    if (err) return reject(err);
                    resolve(!!row);
                }
            );
        });
    }

    async markTransferProcessed(messageId, discordId, amount) {
        const self = this;
        return new Promise((resolve, reject) => {
            self.db.run(
                'INSERT INTO processed_transfers (message_id, discord_id, amount) VALUES (?, ?, ?)',
                [messageId, discordId, amount],
                (err) => {
                    if (err) return reject(err);
                    resolve();
                }
            );
        });
    }

    async start() {
        const token = process.env.DISCORD_BOT_TOKEN;
        
        if (!token) {
            console.error('❌ DISCORD_BOT_TOKEN is required in .env file');
            process.exit(1);
        }
        
        try {
            await this.client.login(token);
        } catch (error) {
            console.error('❌ Failed to login:', error);
            process.exit(1);
        }
    }

    async stop() {
        console.log('🛑 Shutting down bot...');
        this.db.close();
        await this.client.destroy();
    }
}

// Handle graceful shutdown
let bot;

process.on('SIGINT', async () => {
    console.log('\n🛑 Received SIGINT, shutting down gracefully...');
    if (bot) {
        await bot.stop();
    }
    process.exit(0);
});

process.on('SIGTERM', async () => {
    console.log('\n🛑 Received SIGTERM, shutting down gracefully...');
    if (bot) {
        await bot.stop();
    }
    process.exit(0);
});

// Start the bot
bot = new DiscordBroadcasterBot();
bot.start().catch(console.error);

module.exports = DiscordBroadcasterBot;
