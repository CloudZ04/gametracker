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
$receiver_id = $data['receiver_id'] ?? null;
$message = $data['message'] ?? null;

if (!$receiver_id || !$message) {
    echo json_encode(['success' => false, 'message' => 'Receiver ID and message are required']);
    exit();
}

// Validate that the receiver exists
$userQuery = $conn->prepare("SELECT id FROM users WHERE id = ?");
$userQuery->bind_param("i", $receiver_id);
$userQuery->execute();
$userResult = $userQuery->get_result();

if ($userResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

try {
    // Insert the message
    $insertQuery = $conn->prepare("
        INSERT INTO messages (sender_id, receiver_id, message) 
        VALUES (?, ?, ?)
    ");
    $insertQuery->bind_param("iis", $_SESSION['user_id'], $receiver_id, $message);
    
    if ($insertQuery->execute()) {
        echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error sending message: ' . $e->getMessage()
    ]);
}
?> 