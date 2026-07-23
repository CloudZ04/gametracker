<?php
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $new_email = $_POST['new_email'] ?? '';
    $confirm_email = $_POST['confirm_email'] ?? '';

    // Validate emails match
    if ($new_email !== $confirm_email) {
        $_SESSION['error'] = "New email and confirmation email do not match.";
        header('Location: settings.php#account');
        exit();
    }

    // Validate email format
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
        header('Location: settings.php#account');
        exit();
    }

    // Check if email is already in use
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $new_email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "This email is already in use.";
        header('Location: settings.php#account');
        exit();
    }
    $stmt->close();

    try {
        $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->bind_param("si", $new_email, $user_id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Email updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update email.";
        }

        $stmt->close();
    } catch (Exception $e) {
        error_log($e->getMessage());
        $_SESSION['error'] = "An error occurred while updating email.";
    }

    header('Location: settings.php#account');
    exit();
}

header('Location: settings.php');
exit(); 