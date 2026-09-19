<?php
session_start();
require "db.php";
require_once "schema.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (isset($_POST['update_btn'])) {
    $user_id = (int)$_SESSION['user_id'];
    $first_name = trim((string)($_POST['first_name'] ?? ''));
    $last_name = trim((string)($_POST['last_name'] ?? ''));
    $birth_date = trim((string)($_POST['birth_date'] ?? ($_POST['birthday'] ?? '')));

    $current_password = (string)($_POST['current_password'] ?? '');
    $new_password = (string)($_POST['new_password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');

    if ($first_name === '' || $last_name === '') {
        header('Location: dashboard.php?profile_error=1');
        exit;
    }

    // Basic birth_date validation (allow empty)
    if ($birth_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        header('Location: dashboard.php?profile_error=1');
        exit;
    }

    $changingPassword = trim($new_password) !== '' || trim($confirm_password) !== '' || trim($current_password) !== '';
    if ($changingPassword) {
        if (trim($new_password) === '' || trim($confirm_password) === '' || trim($current_password) === '') {
            header('Location: dashboard.php?password_error=1');
            exit;
        }
        if ($new_password !== $confirm_password) {
            header('Location: dashboard.php?password_mismatch=1');
            exit;
        }
        if (strlen($new_password) < 6) {
            header('Location: dashboard.php?password_weak=1');
            exit;
        }

        $sel = $conn->prepare('SELECT password_hash FROM users WHERE user_id = ? LIMIT 1');
        $sel->bind_param('i', $user_id);
        $sel->execute();
        $res = $sel->get_result();
        if (!$res || $res->num_rows !== 1) {
            header('Location: dashboard.php?profile_error=1');
            exit;
        }
        $row = $res->fetch_assoc();
        $hash = (string)($row['password_hash'] ?? '');
        if ($hash === '' || !password_verify($current_password, $hash)) {
            header('Location: dashboard.php?password_error=1');
            exit;
        }
    }

    // Ensure membership column exists if possible, but do not allow updating it from this form.
    ensure_membership_column($conn);

    if (!$changingPassword) {
        $sql = 'UPDATE users SET first_name = ?, last_name = ?, birth_date = ? WHERE user_id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssi', $first_name, $last_name, $birth_date, $user_id);
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = 'UPDATE users SET first_name = ?, last_name = ?, birth_date = ?, password_hash = ? WHERE user_id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssi', $first_name, $last_name, $birth_date, $hashed_password, $user_id);
    }

    if ($stmt->execute()) {
        header('Location: dashboard.php?profile_updated=1');
        exit;
    }

    header('Location: dashboard.php?profile_error=1');
    exit;
}
?>