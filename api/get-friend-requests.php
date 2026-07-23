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

try {
    // Get pending friend requests for the current user
    $query = $conn->prepare("
        SELECT u.id, u.username, u.name, u.profile_image
        FROM user_relationships ur
        JOIN users u ON ur.follower_id = u.id
        WHERE ur.following_id = ? AND ur.status = 'friend_request'
        ORDER BY ur.created_at DESC
    ");
    $query->bind_param("i", $_SESSION['user_id']);
    $query->execute();
    $result = $query->get_result();
    
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'requests' => $requests
    ]);
} catch (Exception $e) {
    error_log("Error in get-friend-requests.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?> 