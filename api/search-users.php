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
$query = $data['query'] ?? '';

if (empty($query)) {
    echo json_encode(['success' => false, 'message' => 'Search query is required']);
    exit();
}

try {
    // Search users by username or display name
    $searchQuery = $conn->prepare("
        SELECT 
            u.id,
            u.username,
            u.name,
            u.about,
            u.profile_image,
            u.profile_visibility,
            (SELECT COUNT(*) FROM user_relationships WHERE following_id = u.id AND status = 'following') as followers,
            (SELECT COUNT(*) FROM user_relationships WHERE follower_id = u.id AND status = 'following') as following,
            (SELECT COUNT(DISTINCT 
                CASE 
                    WHEN follower_id = u.id THEN following_id 
                    ELSE follower_id 
                END
            ) FROM user_relationships WHERE (
                (follower_id = u.id AND status = 'friends') OR 
                (following_id = u.id AND status = 'friends')
            )) as friends,
            (SELECT status FROM user_relationships WHERE follower_id = ? AND following_id = u.id LIMIT 1) as relationship,
        (SELECT status FROM user_relationships WHERE follower_id = ? AND following_id = u.id AND status = 'friend_request' LIMIT 1) as friend_request_sent,
        (SELECT status FROM user_relationships WHERE follower_id = u.id AND following_id = ? AND status = 'friend_request' LIMIT 1) as friend_request_received
        FROM users u
        WHERE (u.username LIKE ? OR u.name LIKE ?)
        AND u.id != ?
        AND (u.profile_visibility = 'public' 
             OR (u.profile_visibility = 'friends' 
                 AND EXISTS (
                     SELECT 1 FROM user_relationships 
                     WHERE ((follower_id = ? AND following_id = u.id) 
                            OR (follower_id = u.id AND following_id = ?))
                     AND status = 'friends'
                 ))
            )
        LIMIT 20
    ");

    $searchTerm = "%$query%";
    $userId = $_SESSION['user_id'];
    $searchQuery->bind_param("iiissiii", $userId, $userId, $userId, $searchTerm, $searchTerm, $userId, $userId, $userId);
    $searchQuery->execute();
    $result = $searchQuery->get_result();

    $users = [];
    while ($user = $result->fetch_assoc()) {
        // Generate initials for avatar fallback
        $initials = strtoupper(preg_replace('/[^A-Z]/i', '', $user['username'][0] . ($user['username'][1] ?? '')));
        
        // Format profile image path
        if (!empty($user['profile_image'])) {
            $user['profile_image'] = strpos($user['profile_image'], 'uploads/profiles/') === 0 
                ? $user['profile_image'] 
                : 'uploads/profiles/' . $user['profile_image'];
        }

        // Determine the relationship status
        $relationship = 'none';
        if ($user['relationship'] === 'friends') {
            $relationship = 'friends';
        } elseif ($user['relationship'] === 'following') {
            $relationship = 'following';
        } elseif ($user['friend_request_sent']) {
            $relationship = 'friend_request_sent';
        } elseif ($user['friend_request_received']) {
            $relationship = 'friend_request_received';
        }
        
        $users[] = array_merge($user, [
            'initials' => $initials,
            'relationship' => $relationship
        ]);
    }

    $response = [
        'success' => true,
        'users' => $users
    ];
    
    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred while searching: ' . $e->getMessage()]);
}
?> 