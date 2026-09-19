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
ensure_notifications_table($conn);

// Support both form-encoded POST and JSON bodies
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }
}

$all = isset($input['all']) && (string)$input['all'] === '1';
$id = $input['id'] ?? null;

try {
    if ($all) {
        $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        echo json_encode(['success' => true, 'updated' => $affected]);
        exit;
    }

    $idStr = $id === null ? '' : (string)$id;
    if ($idStr === '' || !preg_match('/^\d+$/', $idStr) || $idStr === '0') {
        echo json_encode(['success' => false, 'error' => 'Missing or invalid id']);
        exit;
    }

    $notification_id = $idStr;

    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?');
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('si', $notification_id, $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    echo json_encode(['success' => true, 'updated' => $affected]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
