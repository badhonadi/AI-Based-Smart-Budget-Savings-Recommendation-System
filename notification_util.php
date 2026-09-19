<?php

function ensure_notifications_table(mysqli $conn): void {
    // Check if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'notifications'");
    
    if ($checkTable && $checkTable->num_rows > 0) {
        // Table exists, check if it has all required columns
        $columns = $conn->query("SHOW COLUMNS FROM notifications");
        $existingColumns = [];
        while ($col = $columns->fetch_assoc()) {
            $existingColumns[] = $col['Field'];
        }
        
        // Add missing columns if needed
        if (!in_array('type', $existingColumns)) {
            $conn->query("ALTER TABLE notifications ADD COLUMN type VARCHAR(40) NOT NULL DEFAULT 'general' AFTER message");
            $conn->query("CREATE INDEX idx_notifications_type ON notifications(type)");
        }
        if (!in_array('is_read', $existingColumns)) {
            $conn->query("ALTER TABLE notifications ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER type");
        }
        if (!in_array('created_at', $existingColumns)) {
            $conn->query("ALTER TABLE notifications ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_read");
        }
        
        return;
    }

    // Create table from scratch
    $conn->query(
        "CREATE TABLE IF NOT EXISTS notifications (
            notification_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(40) NOT NULL DEFAULT 'general',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notifications_user (user_id),
            INDEX idx_notifications_user_read (user_id, is_read),
            INDEX idx_notifications_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function add_notification(mysqli $conn, int $user_id, string $title, string $message, string $type = 'general'): void {
    try {
        ensure_notifications_table($conn);
        $safeTitle = substr($title, 0, 150);
        $safeType = substr($type, 0, 40);
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            error_log("Notification prepare failed: " . $conn->error);
            return;
        }
        $stmt->bind_param('isss', $user_id, $safeTitle, $message, $safeType);
        if (!$stmt->execute()) {
            error_log("Notification execute failed: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
    }
}

function fetch_notifications(mysqli $conn, int $user_id, int $limit = 50): array {
    ensure_notifications_table($conn);
    $stmt = $conn->prepare("SELECT notification_id AS id, title, message, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ii', $user_id, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}
