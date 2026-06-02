# Telegram-Forwarder

markdown
# Telegram Channel Forwarder Bot

A sophisticated PHP-based Telegram bot that provides intelligent message forwarding capabilities between channels with advanced features like duplicate detection, keyword filtering, reply threading, and automatic deletion synchronization.

## 🚀 Features

### Core Forwarding Features
- **Multi-Source to Multi-Target Forwarding**: Forward messages from multiple source channels to multiple target channels using flexible rule-based configuration
- **Reply Thread Preservation**: Maintains conversation context by correctly threading replies in target channels
- **Edit & Delete Synchronization**: Automatically updates or deletes forwarded messages when original messages are edited or deleted
- **Real-time Processing**: Instant message forwarding with minimal latency

### Duplicate Management
- **Source Duplicate Detection**: Prevents duplicate processing of identical messages within 10 seconds
- **Target Duplicate Protection**: Automatically detects and deletes duplicate messages in target channels
- **Intelligent Mapping**: Maintains proper message mapping even when duplicates are detected

### Keyword Filtering
- **Simple Keyword Matching**: Forward only messages containing specific keywords
- **Advanced Combo Keywords**: Create complex filters using bracket syntax like `[profit+cancelled]` to require multiple keywords
- **Case-Insensitive Matching**: All keyword matching is case-insensitive for better usability

### Administrative Interface
- **Private Bot Commands**: Manage forwarding rules through Telegram private chat
- **Dynamic Rule Management**: Add forwarding rules without modifying code
- **Real-time Rule Validation**: Automatic validation of channel IDs and rule syntax

### Data Persistence
- **JSON-based Storage**: All rules and mappings stored in JSON files for easy backup and modification
- **Message Mapping Database**: Maintains relationships between source and target messages
- **Automatic Cleanup**: Expired duplicate entries automatically removed after 10 seconds

### Logging & Monitoring
- **Comprehensive Logging**: All bot activities logged with timestamps
- **Error Handling**: Graceful error handling with detailed logging
- **Activity Tracking**: Forwarding actions, deletions, and edits all recorded

## 📋 Technical Specifications

### File Structure
telegram-forwarder-bot/
├── bot.php # Main bot script
├── forward_rules.json # Forwarding rules configuration
├── message_map.json # Message ID mappings
├── duplicates.json # Source message duplicate tracking
├── target_duplicates.json # Target message duplicate tracking
└── bot.log # Activity log file

text

### Requirements
- PHP 7.0 or higher
- PHP cURL extension
- Write permissions in bot directory
- Telegram Bot API access

## 🔧 Installation

### 1. Prerequisites Setup
```bash
# Install required PHP extensions
sudo apt-get install php php-curl

# Create bot directory
mkdir telegram-forwarder-bot
cd telegram-forwarder-bot
2. Bot Configuration
Create a new bot via @BotFather on Telegram

Copy your bot token

Update the bot token in bot.php:

php
$botToken = "YOUR_BOT_TOKEN_HERE";
3. Webhook Setup
Set up the webhook URL for your bot:

bash
https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook?url=<YOUR_WEBHOOK_URL>/bot.php
4. Permissions
Ensure the bot has:

Admin rights in all source and target channels

Post messages permission in target channels

Delete messages permission in target channels

Edit messages permission in target channels

📖 Usage Guide
Adding Forwarding Rules
Send commands to your bot in private chat using the following format:

Basic Rule (All Messages)
text
/forward -1001234567890 to -1009876543210
Keyword Filtering
text
/forward -1001234567890 to -1009876543210 crypto,bitcoin,blockchain
Multiple Sources & Targets
text
/forward -1001234567890,-100111222333 to -1009876543210,-100444555666
Advanced Combo Keywords
text
/forward -1001234567890 to -1009876543210 [profit+cancelled],[urgent+important]
Command Syntax Explained
text
/forward [source_ids] to [target_ids] [optional_keywords]
source_ids: Comma-separated channel IDs (must include -100 prefix)

target_ids: Comma-separated channel IDs (must include -100 prefix)

keywords: Optional comma-separated keywords for filtering

Keyword Types
Type	Format	Example	Behavior
Simple	keyword	urgent	Forward if message contains "urgent"
Combo	[keyword1+keyword2]	[profit+cancelled]	Forward only if message contains BOTH words
🔄 How It Works
Message Flow Process
Source Channel → Message posted

Duplicate Check → 10-second window to prevent duplicates

Rule Matching → Check all rules for source channel

Keyword Filtering → Apply keyword filters if specified

Forward to Targets → Send with source channel name prefix

Store Mapping → Save relationship for future edits/deletions

Edit & Delete Handling
Edits: Automatically update forwarded messages

Deletes: Remove corresponding forwarded messages

Reply Threads: Maintain proper threading hierarchy

⚙️ Configuration Options
Adjustable Parameters
php
// Duplicate detection window (seconds)
$duplicateWindow = 10;  // Modify in checkAndUpdateDuplicate()

// Log file name
$logFile = "bot.log";  // Change as needed

// Storage file paths
$duplicatesFile = "duplicates.json";
$targetDupFile = "target_duplicates.json";
$forwardRulesFile = "forward_rules.json";
$messageMapFile = "message_map.json";
Performance Tuning
Duplicate Window: Decrease for faster processing, increase for better duplicate prevention

File Storage: JSON files are suitable for up to 100,000 mappings

Memory Usage: Approximately 1MB per 10,000 stored mappings

🛡️ Security Best Practices
Bot Token Protection: Never commit bot token to version control

Webhook Validation: Implement secret token validation

Rate Limiting: Add rate limiting for production use

Channel Verification: Validate channel bot permissions before adding rules

🐛 Troubleshooting
Common Issues & Solutions
Issue	Solution
Bot not responding	Check webhook URL and bot token
Messages not forwarding	Verify bot admin rights in channels
Duplicate messages	Check duplicate detection window settings
Reply threading broken	Ensure target message mapping exists
High memory usage	Clear old entries from JSON files
Debug Mode
Enable detailed logging:

php
// Add to processUpdate() function
error_reporting(E_ALL);
ini_set('display_errors', 1);
📊 Monitoring & Maintenance
Log Analysis
bash
# View recent activity
tail -f bot.log

# Search for specific channel activity
grep "-1001234567890" bot.log
Regular Maintenance
Weekly: Archive old logs

Monthly: Clean up expired message mappings

As needed: Backup JSON configuration files

🚦 Limitations
Maximum 4096 characters per message (Telegram limit)

30 messages per second (Telegram API limit)

Channel IDs must use -100 prefix

No support for media captions (text-only forwarding)

🤝 Contributing
Feel free to submit issues, fork the repository, and create pull requests for improvements.

📄 License
MIT License - Feel free to use and modify for your needs.

Support
For issues or questions:

Check the troubleshooting guide

Review bot.log for errors

Verify bot permissions in channels

Ensure webhook is properly configured

Note: This bot requires proper Telegram API access and appropriate channel permissions. Always comply with Telegram's Terms of Service.

text

This README provides comprehensive documentation covering installation, configuration, usage, and troubleshooting. It categorizes all features clearly and provides practical examples for each major functionality of your bot.