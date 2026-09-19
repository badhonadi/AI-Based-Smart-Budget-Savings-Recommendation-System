<?php
/**
 * SETUP INSTRUCTIONS - Google Gemini AI Integration
 * 
 * STEP 1: Get Free API Key
 * ========================
 * 1. Go to: https://ai.google.dev/
 * 2. Click "Get API Key" button
 * 3. Create a new Google Cloud project or select existing
 * 4. Enable the Generative Language API
 * 5. Copy your API key
 * 
 * STEP 2: Add to Configuration
 * ============================
 * Open: ai_config.php
 * 
 * Find these lines:
 *     define('ENABLE_GEMINI_AI', false);
 *     define('GEMINI_API_KEY', '');
 * 
 * Change to:
 *     define('ENABLE_GEMINI_AI', true);
 *     define('GEMINI_API_KEY', 'YOUR-API-KEY-HERE');
 * 
 * Example:
 *     define('GEMINI_API_KEY', 'your-gemini-api-key');
 * 
 * STEP 3: Test It
 * ===============
 * 1. Add a new expense transaction in Spendee
 * 2. Look for AI Suggestions popup
 * 3. You should see smart recommendations!
 * 
 * ---
 * 
 * TROUBLESHOOTING
 * ===============
 * 
 * Q: "Suggestions not showing"
 * A: Check PHP error log for issues with ai_suggestions.php
 *    Verify you added at least one expense
 * 
 * Q: "API key not valid"
 * A: Double-check the key is correctly copied
 *    Make sure ENABLE_GEMINI_AI is set to true
 *    Verify key has access to generativelanguage API
 * 
 * Q: "Gemini returning empty response"
 * A: Check API rate limits at https://ai.google.dev/
 *    May need to wait a minute before retrying
 * 
 * ---
 * 
 * FEATURES
 * ========
 * 
 * Without Gemini API (Basic Mode):
 * - Budget overspend alerts
 * - High spending detection
 * - Savings rate warnings
 * - Unusual expense alerts
 * - Exact savings calculations
 * 
 * With Gemini API (Enhanced Mode):
 * - All above features PLUS
 * - Natural language recommendations
 * - Context-aware suggestions
 * - Personalized financial insights
 * - Conversational tone
 * 
 * ---
 * 
 * SAFETY & PRIVACY
 * ================
 * 
 * ✅ User data stays on your server
 * ✅ Only spending summaries sent to Gemini API
 * ✅ No sensitive account details shared
 * ✅ API key never exposed to client
 * ✅ All processing server-side
 * 
 * ---
 * 
 * FILES CREATED
 * =============
 * 
 * 1. ai_suggestions.php
 *    → Core suggestion engine (PHP)
 *    → Analyzes finances & generates recommendations
 *    → No API key needed
 * 
 * 2. gemini_ai.php
 *    → Google Gemini API wrapper
 *    → Provides natural language suggestions
 *    → Optional - API key required
 * 
 * 3. ai_config.php
 *    → Configuration file
 *    → Store API keys here
 *    → Enable/disable features
 * 
 * 4. AI_SETUP_GUIDE.md
 *    → Complete documentation
 *    → Features explanation
 *    → Troubleshooting guide
 * 
 * ---
 * 
 * INTEGRATION POINTS
 * ==================
 * 
 * save_transaction.php:
 *     - Calls ai_suggestions.php after expense saved
 *     - Returns suggestions in JSON response
 * 
 * transaction.php (Frontend):
 *     - Displays suggestions modal
 *     - Shows financial summary
 *     - Lists prioritized recommendations
 * 
 * ---
 * 
 * READY TO GO!
 * ============
 * 
 * Your Spendee now has:
 * ✅ Smart savings suggestions after every expense
 * ✅ Budget goal tracking & alerts
 * ✅ Income/expense analysis
 * ✅ Optional AI-powered recommendations
 * 
 * Test it now by adding an expense! 🚀
 */

// Quick setup check
if (!file_exists('ai_config.php')) {
    echo "ERROR: ai_config.php not found!";
    exit;
}

require_once 'ai_config.php';

echo "Configuration Status:\n";
echo "====================\n\n";
echo "Basic AI Enabled: " . (ENABLE_BASIC_AI_SUGGESTIONS ? "✓ YES" : "✗ NO") . "\n";
echo "Gemini AI Enabled: " . (ENABLE_GEMINI_AI ? "✓ YES" : "✗ NO") . "\n";
echo "Gemini API Key Set: " . (!empty(GEMINI_API_KEY) ? "✓ YES" : "✗ NO") . "\n";
echo "\nIf you haven't set up Google Gemini yet:\n";
echo "1. Visit: https://ai.google.dev/\n";
echo "2. Get a free API key\n";
echo "3. Add it to ai_config.php\n";
echo "4. Set ENABLE_GEMINI_AI to true\n";
?>
