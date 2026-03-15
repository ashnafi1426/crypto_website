#!/bin/bash

# This script removes OAuth secrets from git history

echo "=== Fixing Git History - Removing OAuth Secrets ==="
echo ""
echo "This will rewrite git history to remove secrets from previous commits."
echo "Press Ctrl+C to cancel, or Enter to continue..."
read

# Reset to the last good commit (before secrets were added)
echo "Resetting to origin/main..."
git reset --soft origin/main

# Now commit all changes with secrets removed
echo "Creating new clean commit..."
git add .
git commit -m "Add OAuth implementation with email login fixes

- Implemented Google and Apple OAuth authentication
- Fixed email/password login issues
- Added OTP verification system
- Updated frontend OAuth integration
- Removed OAuth secrets from documentation (using placeholders)
- All credentials stored securely in .env file only"

echo ""
echo "✅ Git history cleaned!"
echo ""
echo "Now you can push with:"
echo "git push -f origin main"
echo ""
echo "⚠️  WARNING: This uses force push. Only do this if you're the only one working on this branch!"
