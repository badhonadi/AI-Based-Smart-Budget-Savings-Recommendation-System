<?php
session_start();
header('Content-Type: application/json');

$rawCode = $_POST['currency'] ?? $_GET['currency'] ?? '';
$code = strtoupper(trim((string)$rawCode));

$allowed = ['USD','BDT','EUR','GBP','INR','JPY','CNY','AUD','CAD'];
if (!in_array($code, $allowed, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid currency']);
    exit;
}

$_SESSION['currency'] = $code;

echo json_encode(['success' => true, 'currency' => $code]);
