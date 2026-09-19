<?php
require_once __DIR__ . '/ai_config.php';

$hasKey = getenv('GEMINI_API_KEY') ? true : false;
$cfgKey = (defined('GEMINI_API_KEY') && trim((string)GEMINI_API_KEY) !== '') ? true : false;

header('Content-Type: text/plain');
echo "ENABLE_GEMINI_AI=" . ((defined('ENABLE_GEMINI_AI') && ENABLE_GEMINI_AI) ? 'true' : 'false') . "\n";
echo "env(GEMINI_API_KEY)=" . ($hasKey ? 'set' : 'not set') . "\n";
echo "config(GEMINI_API_KEY)=" . ($cfgKey ? 'set' : 'not set') . "\n";
