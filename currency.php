<?php

declare(strict_types=1);

// Base currency is USD. All amounts in DB are assumed stored in USD.

function currency_rates_usd_base(): array {
    // 1 USD = rate in target currency
    return [
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
}

function currency_symbols(): array {
    return [
        'USD' => '$',
        'EUR' => '€',
        'BDT' => '৳',
        'INR' => '₹',
        'GBP' => '£',
        'JPY' => '¥',
        'CNY' => '¥',
        'AUD' => '$',
        'CAD' => '$',
    ];
}

function get_currency_code(): string {
    $code = strtoupper((string)($_SESSION['currency'] ?? 'USD'));
    $rates = currency_rates_usd_base();
    return array_key_exists($code, $rates) ? $code : 'USD';
}

function convert_usd_to_currency(float $amountUsd, ?string $currencyCode = null): float {
    $code = $currencyCode ? strtoupper($currencyCode) : get_currency_code();
    $rates = currency_rates_usd_base();
    $rate = (float)($rates[$code] ?? 1.0);
    return $amountUsd * $rate;
}

function format_money_usd(float $amountUsd, ?string $currencyCode = null): string {
    $code = $currencyCode ? strtoupper($currencyCode) : get_currency_code();
    $symbols = currency_symbols();
    $symbol = (string)($symbols[$code] ?? '$');

    $converted = convert_usd_to_currency($amountUsd, $code);
    $formatted = number_format(abs($converted), 2, '.', ',');
    return $symbol . $formatted;
}
