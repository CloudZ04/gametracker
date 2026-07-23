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
    // Get recent notifications for the user
    $query = $conn->prepare("
        SELECT n.*, u.username, u.name, u.profile_image
        FROM notifications n
        JOIN users u ON n.from_user_id = u.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 5
    ");
    $query->bind_param("i", $_SESSION['user_id']);
    $query->execute();
    $result = $query->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'],
            'type' => $row['type'],
            'message' => $row['message'],
            'is_read' => $row['is_read'],
            'created_at' => $row['created_at'],
            'from_user' => [
                'id' => $row['from_user_id'],
                'username' => $row['username'],
                'name' => $row['name'],
                'profile_image' => $row['profile_image']
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading notifications: ' . $e->getMessage()
    ]);
}
?> 