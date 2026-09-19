<?php
// Simple currency helpers shared by PHP endpoints.

$CURRENCY_RATES = [
    'USD' => 1.0,
    'BDT' => 122.30,
    'EUR' => 0.92,
    'GBP' => 0.79,
    'INR' => 83.00,
    'JPY' => 149.00,
    'CNY' => 7.24,
    'AUD' => 1.52,
    'CAD' => 1.36,
];

$DEFAULT_CURRENCY = 'BDT';

function normalize_currency(string $code): string {
    global $CURRENCY_RATES, $DEFAULT_CURRENCY;
    $upper = strtoupper(trim($code));
    return array_key_exists($upper, $CURRENCY_RATES) ? $upper : $DEFAULT_CURRENCY;
}

function convert_amount(float $amount, string $from, string $to): float {
    global $CURRENCY_RATES;
    $fromCode = normalize_currency($from);
    $toCode = normalize_currency($to);
    $fromRate = $CURRENCY_RATES[$fromCode] ?? 1.0;
    $toRate = $CURRENCY_RATES[$toCode] ?? 1.0;
    if ($fromRate <= 0) {
        $fromRate = 1.0;
    }
    $baseUsd = $amount / $fromRate;
    return $baseUsd * $toRate;
}

function allowed_currencies(): array {
    return ['USD','BDT','EUR','GBP','INR','JPY','CNY','AUD','CAD'];
}
