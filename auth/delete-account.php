<?php
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $delete_confirm = $_POST['delete_confirm'] ?? '';

    if ($delete_confirm !== 'DELETE') {
        $_SESSION['error'] = "Invalid confirmation text.";
        header('Location: settings.php#account');
        exit();
    }

    try {
        $conn->begin_transaction();

        // Delete user's reviews
        $stmt = $conn->prepare("DELETE FROM reviews WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Delete user's game statuses
        $stmt = $conn->prepare("DELETE FROM user_game_status WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Delete user's relationships (following/friends)
        $stmt = $conn->prepare("DELETE FROM user_relationships WHERE user_id = ? OR related_user_id = ?");
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $stmt->close();

        // Delete user's game requests
        $stmt = $conn->prepare("DELETE FROM game_requests WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Delete user's wishlist entries
        $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Finally, delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        // Clear session and redirect to home
        session_destroy();
        header('Location: ../index.php');
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        error_log($e->getMessage());
        $_SESSION['error'] = "An error occurred while deleting your account.";
        header('Location: settings.php#account');
        exit();
    }
}

header('Location: settings.php');
exit(); 