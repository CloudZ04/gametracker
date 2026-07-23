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

try {
    // Get user's friends (mutual relationships where status = 'friends')
    $query = $conn->prepare("
        SELECT DISTINCT u.id, u.username, u.name, u.profile_image
        FROM user_relationships ur
        JOIN users u ON (
            (ur.follower_id = ? AND ur.following_id = u.id) OR 
            (ur.following_id = ? AND ur.follower_id = u.id)
        )
        WHERE ur.status = 'friends'
        ORDER BY u.username ASC
    ");
    $query->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
    $query->execute();
    $result = $query->get_result();
    
    $friends = [];
    while ($row = $result->fetch_assoc()) {
        // Get follower count for this friend
        $followerQuery = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM user_relationships 
            WHERE following_id = ? AND status = 'following'
        ");
        $followerQuery->bind_param("i", $row['id']);
        $followerQuery->execute();
        $followerCount = $followerQuery->get_result()->fetch_assoc()['count'];
        
        // Get following count for this friend
        $followingQuery = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM user_relationships 
            WHERE follower_id = ? AND status = 'following'
        ");
        $followingQuery->bind_param("i", $row['id']);
        $followingQuery->execute();
        $followingCount = $followingQuery->get_result()->fetch_assoc()['count'];
        
        // Get friend count for this friend (count unique friendships)
        $friendQuery = $conn->prepare("
            SELECT COUNT(DISTINCT 
                CASE 
                    WHEN follower_id = ? THEN following_id 
                    ELSE follower_id 
                END
            ) as count 
            FROM user_relationships 
            WHERE (
                (follower_id = ? AND status = 'friends') OR 
                (following_id = ? AND status = 'friends')
            )
        ");
        $friendQuery->bind_param("iii", $row['id'], $row['id'], $row['id']);
        $friendQuery->execute();
        $friendCount = $friendQuery->get_result()->fetch_assoc()['count'];
        
        $friends[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'name' => $row['name'],
            'profile_image' => $row['profile_image'],
            'follower_count' => $followerCount,
            'following_count' => $followingCount,
            'friend_count' => $friendCount
        ];
    }
    
    echo json_encode([
        'success' => true,
        'friends' => $friends
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading friends: ' . $e->getMessage()
    ]);
}
?> 