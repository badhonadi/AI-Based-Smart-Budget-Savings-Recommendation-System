<?php
session_start();
require 'db.php';
require_once 'notification_util.php';

if (!isset($_SESSION['user_id'])) {
    die("Please login first");
}

$user_id = (int)$_SESSION['user_id'];

echo "<h2>Testing Notification System</h2>";

// Test 1: Check if table exists
echo "<h3>Test 1: Check if notifications table exists</h3>";
$result = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($result && $result->num_rows > 0) {
    echo "✅ Table exists<br>";
} else {
    echo "❌ Table does not exist. Creating...<br>";
    ensure_notifications_table($conn);
    $result = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Table created successfully<br>";
    } else {
        echo "❌ Failed to create table<br>";
    }
}

// Test 2: Try to add a notification
echo "<h3>Test 2: Add a test notification</h3>";
try {
    add_notification($conn, $user_id, "Test Notification", "This is a test message at " . date('Y-m-d H:i:s'), "general");
    echo "✅ Notification added successfully<br>";
} catch (Exception $e) {
    echo "❌ Failed to add notification: " . $e->getMessage() . "<br>";
}

// Test 3: Fetch notifications
echo "<h3>Test 3: Fetch notifications</h3>";
try {
    $notifications = fetch_notifications($conn, $user_id, 10);
    echo "Found " . count($notifications) . " notifications:<br><br>";
    if (count($notifications) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Title</th><th>Message</th><th>Type</th><th>Read</th><th>Created</th></tr>";
        foreach ($notifications as $n) {
            echo "<tr>";
            echo "<td>" . $n['id'] . "</td>";
            echo "<td>" . htmlspecialchars($n['title']) . "</td>";
            echo "<td>" . htmlspecialchars($n['message']) . "</td>";
            echo "<td>" . htmlspecialchars($n['type']) . "</td>";
            echo "<td>" . ($n['is_read'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . $n['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No notifications found.";
    }
} catch (Exception $e) {
    echo "❌ Failed to fetch notifications: " . $e->getMessage() . "<br>";
}

echo "<br><br><a href='dashboard.php'>Back to Dashboard</a> | <a href='budget.php'>Go to Budget</a>";
?>
