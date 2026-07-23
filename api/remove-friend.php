<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Include database connection
require_once '../includes/db.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$friend_user_id = $data['user_id'] ?? null;

if (!$friend_user_id) {
    echo json_encode(['success' => false, 'message' => 'Friend user ID is required']);
    exit();
}

try {
    // Check if the relationship exists and is a friendship
    $checkQuery = $conn->prepare("
        SELECT id, status 
        FROM user_relationships 
        WHERE (
            (follower_id = ? AND following_id = ?) OR 
            (follower_id = ? AND following_id = ?)
        ) AND status = 'friends'
    ");
    $checkQuery->bind_param("iiii", $_SESSION['user_id'], $friend_user_id, $friend_user_id, $_SESSION['user_id']);
    $checkQuery->execute();
    $result = $checkQuery->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Friendship not found']);
        exit();
    }
    
    // Remove the friendship (delete the relationship)
    $deleteQuery = $conn->prepare("
        DELETE FROM user_relationships 
        WHERE (
            (follower_id = ? AND following_id = ?) OR 
            (follower_id = ? AND following_id = ?)
        ) AND status = 'friends'
    ");
    $deleteQuery->bind_param("iiii", $_SESSION['user_id'], $friend_user_id, $friend_user_id, $_SESSION['user_id']);
    
    if ($deleteQuery->execute()) {
        echo json_encode(['success' => true, 'message' => 'Friend removed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove friend']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error removing friend: ' . $e->getMessage()
    ]);
}
?> 