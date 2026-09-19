# 🤖 AI Savings Assistant - Setup Guide

## Overview
Your Spendee application now includes intelligent AI-powered savings suggestions that analyze user spending patterns and provide personalized recommendations to reach budget goals.

## Features Implemented

### 1. **Automatic Savings Suggestions**
- Triggered automatically after every expense transaction
- Analyzes income, expenses, and budget goals
- Provides 5 prioritized recommendations
- Shows exact amount that can be saved

### 2. **Smart Analysis**
The AI analyzes:
- ✅ Budget compliance warnings (if over-budget)
- ✅ High spending categories (>30% of income)
- ✅ Low savings rate detection (<10%)
- ✅ Unusual expense detection
- ✅ Total savings potential calculation

### 3. **Beautiful UI**
- Modal popup with financial summary
- Color-coded priority badges (High/Medium/Low)
- Detailed recommendations for each suggestion
- Real-time analysis after each transaction

---

## How to Enable Google Gemini AI (Optional)

### Step 1: Get Free Gemini API Key
1. Visit: https://ai.google.dev/
2. Click "Get API Key"
3. Sign in with your Google account
4. Create a new project or use existing
5. Copy your API key

### Step 2: Add API Key to Your Config
Edit `ai_config.php` in your Spendee root:

```php
define('ENABLE_GEMINI_AI', true);  // Set to true
define('GEMINI_API_KEY', 'your-api-key-here');  // Paste your key here
```

Or set as environment variable:
```bash
export GEMINI_API_KEY='your-api-key-here'
```

### Step 3: Done!
The AI assistant will now generate even smarter, conversational recommendations using Google's Gemini model.

---

## How It Works

### When User Adds Expense:
1. Transaction is saved to database
2. AI engine analyzes:
   - Current month income
   - Current month expenses by category
   - Active budget goals
   - Spending patterns
3. Generates 5 key recommendations
4. Displays beautiful modal with suggestions

### Example:

**User adds:**
- Expense: 500 BDT on Food

**AI Suggests:**
- "You are behind by 2,500 BDT versus the planned pace."
- "Reduce Food spending by 500 BDT to save 2,000 BDT this month."
- "Your savings rate is only 5% - target 15% for financial health."
- "Total potential savings: 8,500 BDT"

---

## API Files

### `ai_suggestions.php`
- Main suggestion engine
- Analyzes finances and generates recommendations
- No API key required (works with basic logic)

### `gemini_ai.php`
- Optional Google Gemini API integration
- Generates natural language recommendations
- Requires API key

### `ai_config.php`
- Configuration file for AI features
- Set API keys and preferences here

---

## Features Without Gemini API

Even without Google Gemini, the system provides:
- ✅ Budget overspend alerts
- ✅ High spending category detection
- ✅ Low savings rate warnings
- ✅ Unusual expense detection
- ✅ Exact savings amount calculations

---

## Tech Stack

- **Backend**: PHP with MySQLi
- **Frontend**: Vanilla JavaScript
- **Optional AI**: Google Gemini API
- **Data**: Real-time from database

---

## Security Notes

- API keys should never be hardcoded in production
- Use environment variables or secure config files
- Keep `ai_config.php` out of version control
- Add `.env` to `.gitignore`

---

## Troubleshooting

**Q: Suggestions not showing?**
- Check browser console for errors
- Verify user has transactions
- Check that expense transaction was saved

**Q: API key not working?**
- Verify key is correct in `ai_config.php`
- Check API quota at https://ai.google.dev/
- Ensure ENABLE_GEMINI_AI is set to `true`

**Q: Want to use a different AI?**
- You can modify `gemini_ai.php` to use OpenAI, Claude, etc.
- Just change the API endpoint and payload structure

---

## Future Enhancements

Potential features to add:
- 📈 Spending trend analysis
- 📊 Budget forecasting
- 🎯 Automatic goal optimization
- 💬 Chat-based financial advisor
- 🔔 Smart notifications
- 📱 Mobile-friendly AI chat

---

## Support

For issues or questions:
1. Check PHP error logs
2. Verify database connectivity
3. Test API key separately
4. Check browser DevTools console

---

**Enjoy smarter budgeting with AI! 🚀**
