<?php
/**
 * AI Configuration File
 * 
 * To enable Google Gemini AI features:
 * 1. Get a free API key from https://ai.google.dev/
 * 2. Add your API key below
 * 3. Set ENABLE_GEMINI_AI to true
 */

// Optional local override (recommended for personal/local setups)
// Create ai_config.local.php to keep secrets out of shared files.
if (file_exists(__DIR__ . '/ai_config.local.php')) {
	require_once __DIR__ . '/ai_config.local.php';
}

// Enable/disable Gemini AI features
// Note: Even if enabled, Gemini runs only when an API key is available.
if (!defined('ENABLE_GEMINI_AI')) {
	define('ENABLE_GEMINI_AI', true);
}

// Your Google Gemini API Key
// Recommended: set as environment variable GEMINI_API_KEY (safer than hardcoding).
// This constant is a fallback for local/dev only.
if (!defined('GEMINI_API_KEY')) {
	define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
}

// Fallback to basic AI suggestions if Gemini is not available
if (!defined('ENABLE_BASIC_AI_SUGGESTIONS')) {
	define('ENABLE_BASIC_AI_SUGGESTIONS', true);
}

// Suggestion refresh interval (in seconds)
if (!defined('SUGGESTION_CACHE_TIME')) {
	define('SUGGESTION_CACHE_TIME', 300); // 5 minutes
}

/**
 * Retrieve Gemini API key with environment-first precedence.
 */
function get_gemini_api_key(): string {
	$env = getenv('GEMINI_API_KEY');
	if (is_string($env) && trim($env) !== '') {
		return trim($env);
	}
	if (defined('GEMINI_API_KEY') && is_string(GEMINI_API_KEY) && trim(GEMINI_API_KEY) !== '') {
		return trim((string)GEMINI_API_KEY);
	}
	return '';
}

/**
 * Returns true when Gemini is enabled AND a key is present.
 */
function is_gemini_enabled(): bool {
	return (defined('ENABLE_GEMINI_AI') && ENABLE_GEMINI_AI) && get_gemini_api_key() !== '';
}

/**
 * Setup instructions for Google Gemini:
 * 
 * 1. Visit https://ai.google.dev/
 * 2. Click "Get API Key"
 * 3. Create a new API key or use existing one
 * 4. Copy your API key and paste it in GEMINI_API_KEY above
 * 5. Set ENABLE_GEMINI_AI to true
 * 
 * Alternative: Use environment variables
 * - Set GEMINI_API_KEY in your .env or server environment
 * 
 * Features enabled with Gemini:
 * - Smart, conversational savings recommendations
 * - Personalized financial insights
 * - Real-time optimization suggestions
 */
?>
