<?php
session_start();
require "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Backward-compatible: allow ?id=... to delete a bill_payment
if (isset($_GET['id'])) {
    $bill_payment_id = (int)$_GET['id'];
    if ($bill_payment_id > 0) {
        $stmt = $conn->prepare("DELETE FROM bill_payments WHERE bill_payment_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $bill_payment_id, $user_id);
        $stmt->execute();
    }
}

header("Location: bill.php?deleted=1");
exit;
        if ($stmt->affected_rows > 0) {
            echo "success";
        } else {
            echo "Error: Bill not found or you don't have permission";
        }
    } else {
        echo "Error: " . $conn->error;
    }

    $stmt->close();
    mysqli_close($conn);
} else {
    echo "Error: No ID provided";
}
?>