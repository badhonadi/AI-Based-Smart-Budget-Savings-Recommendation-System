<?php
session_start();
require_once "db.php";

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$sessionId = isset($_SESSION['login_session_id']) ? (string)$_SESSION['login_session_id'] : null;

if ($userId) {
    // Try updating by session_id first (preferred)
    if ($sessionId) {
        $stmt = $conn->prepare("UPDATE login_sessions SET logout_time = NOW() WHERE session_id = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $sessionId, $userId);
            $stmt->execute();
        }
    } else {
        // Fallback: Find the most recent session without logout_time
        $findStmt = $conn->prepare("SELECT session_id FROM login_sessions WHERE user_id = ? AND logout_time IS NULL ORDER BY login_time DESC LIMIT 1");
        if ($findStmt) {
            $findStmt->bind_param("i", $userId);
            $findStmt->execute();
            $result = $findStmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $sessionId = $row['session_id'];
                $updateStmt = $conn->prepare("UPDATE login_sessions SET logout_time = NOW() WHERE session_id = ? AND user_id = ?");
                if ($updateStmt) {
                    $updateStmt->bind_param("si", $sessionId, $userId);
                    $updateStmt->execute();
                }
            }
        }
    }
}

// Clear session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

// Redirect to login
header("Location: login.php");
exit;
