#!/bin/bash

echo "🚀 Deploying Admin Features to GitHub..."

# Add all changes
git add .

# Commit with descriptive message
git commit -m "✨ Add Admin Features: Manual Credit Management

- Add Admin Tools section in wallet.php (visible only to admins)
- Add Credits functionality with validation
- Admin modal for adding credits to users
- JavaScript functions for admin operations
- CSS styling for admin interface
- Updated config.php with correct admin Discord ID
- Debug pages for testing admin access
- NEW: admin-access.php - Dedicated admin panel for credit management
- Complete security and validation"

# Push to GitHub
git push origin main

echo "✅ Changes pushed to GitHub!"
echo "⏳ Railway will auto-deploy in 2-3 minutes..."
echo "🔗 Check your site: https://discord-brodcast.up.railway.app/"
