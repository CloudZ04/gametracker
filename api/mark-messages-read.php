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
$sender_id = $data['sender_id'] ?? null;

if (!$sender_id) {
    echo json_encode(['success' => false, 'message' => 'Sender ID is required']);
    exit();
}

try {
    // Mark messages as read (update read_at timestamp)
    $updateQuery = $conn->prepare("
        UPDATE messages 
        SET read_at = CURRENT_TIMESTAMP 
        WHERE sender_id = ? AND receiver_id = ? AND read_at IS NULL
    ");
    $updateQuery->bind_param("ii", $sender_id, $_SESSION['user_id']);
    
    if ($updateQuery->execute()) {
        echo json_encode(['success' => true, 'message' => 'Messages marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark messages as read']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error marking messages as read: ' . $e->getMessage()
    ]);
}
?> 