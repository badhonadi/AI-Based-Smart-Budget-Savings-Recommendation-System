<?php
session_start();
header('Content-Type: application/json');
require 'db.php';
require_once 'notification_util.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$summaryOnly = isset($_GET['summary']) && $_GET['summary'] === '1';
$notifications = fetch_notifications($conn, $user_id, 100);
$unread = array_values(array_filter($notifications, fn($n) => (int)$n['is_read'] === 0));

if ($summaryOnly) {
    echo json_encode([
        'success' => true,
        'unread_count' => count($unread)
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'unread_count' => count($unread),
    'notifications' => array_map(function($n) {
        return [
            'id' => (int)$n['id'],
            'title' => $n['title'],
            'message' => $n['message'],
            'type' => $n['type'],
            'is_read' => (int)$n['is_read'] === 1,
            'created_at' => $n['created_at']
        ];
    }, $notifications)
]);
