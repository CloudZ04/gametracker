<?php
require_once '../includes/db.php';
session_start();

// Set response type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$target_user_id = $data['user_id'] ?? null;

if (!$target_user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

// Can't friend yourself
if ($target_user_id == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Cannot send friend request to yourself']);
    exit();
}

try {
    // Check existing relationship
    $checkQuery = $conn->prepare("
        SELECT id, status 
        FROM user_relationships 
        WHERE (follower_id = ? AND following_id = ?) 
           OR (follower_id = ? AND following_id = ?)
    ");
    $checkQuery->bind_param("iiii", $_SESSION['user_id'], $target_user_id, $target_user_id, $_SESSION['user_id']);
    $checkQuery->execute();
    $result = $checkQuery->get_result();
    $existing = $result->fetch_assoc();

    if ($existing) {
        if ($existing['status'] === 'friends') {
            echo json_encode(['success' => false, 'message' => 'Already friends']);
            exit();
        } elseif ($existing['status'] === 'friend_request') {
            echo json_encode(['success' => false, 'message' => 'Friend request already sent']);
            exit();
        }
    }

    // Start transaction
    $conn->begin_transaction();

    // Create friend request relationship (only one direction)
    $insertQuery = $conn->prepare("
        INSERT INTO user_relationships (follower_id, following_id, status) 
        VALUES (?, ?, 'friend_request')
    ");
    $insertQuery->bind_param("ii", $_SESSION['user_id'], $target_user_id);
    $insertQuery->execute();

    // Create notification for the target user
    $notificationQuery = $conn->prepare("
        INSERT INTO notifications (user_id, from_user_id, type, message) 
        VALUES (?, ?, 'friend_request', ?)
    ");
    
    // Get the sender's username for the notification message
    $senderQuery = $conn->prepare("SELECT username, name FROM users WHERE id = ?");
    $senderQuery->bind_param("i", $_SESSION['user_id']);
    $senderQuery->execute();
    $sender = $senderQuery->get_result()->fetch_assoc();
    $senderName = $sender['name'] ?: $sender['username'];
    
    $message = "$senderName sent you a friend request";
    $notificationQuery->bind_param("iis", $target_user_id, $_SESSION['user_id'], $message);
    $notificationQuery->execute();

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Friend request sent']);
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log("Error in send-friend-request.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?> 