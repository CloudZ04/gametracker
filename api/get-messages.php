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

// Get the user_id parameter
$other_user_id = $_GET['user_id'] ?? null;

if (!$other_user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

try {
    // Get messages between the current user and the other user
    $query = $conn->prepare("
        SELECT id, sender_id, receiver_id, message, created_at
        FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at ASC
    ");
    $query->bind_param("iiii", $_SESSION['user_id'], $other_user_id, $other_user_id, $_SESSION['user_id']);
    $query->execute();
    $result = $query->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'sender_id' => $row['sender_id'],
            'receiver_id' => $row['receiver_id'],
            'message' => $row['message'],
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading messages: ' . $e->getMessage()
    ]);
}
?> 