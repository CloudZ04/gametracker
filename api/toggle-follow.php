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

// Can't follow yourself
if ($target_user_id == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Cannot follow yourself']);
    exit();
}

try {
    // Check if already following
    $checkQuery = $conn->prepare("SELECT id, status FROM user_relationships WHERE follower_id = ? AND following_id = ?");
    $checkQuery->bind_param("ii", $_SESSION['user_id'], $target_user_id);
    $checkQuery->execute();
    $result = $checkQuery->get_result();
    $existing = $result->fetch_assoc();

    if ($existing) {
        if ($existing['status'] === 'friends') {
            echo json_encode(['success' => false, 'message' => 'Cannot modify friend relationship here']);
            exit();
        }
        // Unfollow
        $deleteQuery = $conn->prepare("DELETE FROM user_relationships WHERE id = ?");
        $deleteQuery->bind_param("i", $existing['id']);
        $deleteQuery->execute();
        echo json_encode(['success' => true, 'action' => 'unfollowed']);
    } else {
        // Follow
        $insertQuery = $conn->prepare("INSERT INTO user_relationships (follower_id, following_id, status) VALUES (?, ?, 'following')");
        $insertQuery->bind_param("ii", $_SESSION['user_id'], $target_user_id);
        $insertQuery->execute();
        
        // Create notification for the followed user
        $notificationQuery = $conn->prepare("
            INSERT INTO notifications (user_id, from_user_id, type, message) 
            VALUES (?, ?, 'follow', ?)
        ");
        
        // Get the follower's username for the notification message
        $followerQuery = $conn->prepare("SELECT username, name FROM users WHERE id = ?");
        $followerQuery->bind_param("i", $_SESSION['user_id']);
        $followerQuery->execute();
        $follower = $followerQuery->get_result()->fetch_assoc();
        $followerName = $follower['name'] ?: $follower['username'];
        
        $message = "$followerName started following you";
        $notificationQuery->bind_param("iis", $target_user_id, $_SESSION['user_id'], $message);
        $notificationQuery->execute();
        
        echo json_encode(['success' => true, 'action' => 'followed']);
    }
} catch (Exception $e) {
    error_log("Error in toggle-follow.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?> 