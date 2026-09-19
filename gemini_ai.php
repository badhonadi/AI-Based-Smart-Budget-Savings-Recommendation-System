<?php
/**
 * Google Gemini AI Integration for Smart Suggestions
 * 
 * This file provides AI-powered recommendations using Google's Gemini API
 * To use: Add your GEMINI_API_KEY to your environment or config file
 */

class GeminiAIAssistant {
    private $apiKey;
    private $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';

    public function __construct($apiKey = '') {
        // Try to get API key from environment or config
        if ($apiKey) {
            $this->apiKey = $apiKey;
            return;
        }

        $envKey = getenv('GEMINI_API_KEY') ?: '';
        if (!empty($envKey)) {
            $this->apiKey = $envKey;
            return;
        }

        // Fallback to ai_config.php constant if present
        if (defined('GEMINI_API_KEY') && !empty((string)GEMINI_API_KEY)) {
            $this->apiKey = (string)GEMINI_API_KEY;
            return;
        }

        $this->apiKey = '';
    }

    /**
     * Generate content from a raw prompt.
     */
    public function generateFromPrompt(string $prompt): string {
        if (!$this->isConfigured()) {
            return '';
        }
        try {
            return $this->callAPI($prompt);
        } catch (Exception $e) {
            error_log('Gemini API error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Check if API is properly configured
     */
    public function isConfigured(): bool {
        return !empty($this->apiKey);
    }

    /**
     * Generate smart savings recommendations using Gemini AI
     */
    public function generateSmartRecommendations(array $financialData): string {
        if (!$this->isConfigured()) {
            return '';
        }

        try {
            // Optional: allow passing a custom prompt directly
            if (isset($financialData['custom_prompt']) && is_string($financialData['custom_prompt']) && trim($financialData['custom_prompt']) !== '') {
                $prompt = (string)$financialData['custom_prompt'];
            } else {
                $prompt = $this->buildPrompt($financialData);
            }
            $response = $this->callAPI($prompt);
            return $response;
        } catch (Exception $e) {
            error_log("Gemini API error: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Build intelligent prompt for Gemini
     */
    private function buildPrompt(array $data): string {
        $currentIncome = $data['current_income'] ?? 0;
        $currentExpenses = $data['current_expenses'] ?? 0;
        $remaining = $data['remaining'] ?? 0;
        $savingsRate = $data['savings_rate'] ?? 0;
        $categoryBreakdown = $data['category_breakdown'] ?? [];
        $suggestions = $data['suggestions'] ?? [];

        $categoryText = '';
        foreach ($categoryBreakdown as $cat) {
            $percentage = $currentIncome > 0 ? ($cat['total'] / $currentIncome) * 100 : 0;
            $categoryText .= "- {$cat['name']}: {$cat['total']} BDT ({$percentage}% of income, {$cat['count']} transactions)\n";
        }

        $prompt = <<<PROMPT
You are a smart personal finance assistant. A user has the following financial data for this month:

Monthly Income: {$currentIncome} BDT
Current Expenses: {$currentExpenses} BDT
Remaining Balance: {$remaining} BDT
Savings Rate: {$savingsRate}%

Expense Breakdown:
{$categoryText}

Based on this data, provide 2-3 specific, actionable recommendations to help them:
1. Save more money
2. Reach their budget goals
3. Improve their financial health

Be concise, specific with amounts, and motivating. Format your response as bullet points.
PROMPT;

        return $prompt;
    }

    /**
     * Call Google Gemini API
     */
    private function callAPI(string $prompt): string {
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '?key=' . urlencode($this->apiKey));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            return $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } else {
            throw new Exception("Gemini API returned status: $httpCode");
        }
    }
}

// Return instance if file is included
return new GeminiAIAssistant();
