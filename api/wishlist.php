<?php
// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../includes/db.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Input validation
if (!isset($_POST['game_id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Get and sanitize input parameters
$userId = $_SESSION['user_id'];
$gameId = (int)$_POST['game_id']; // Cast to integer for security
$action = $_POST['action'];

// Validate action type
if (!in_array($action, ['add', 'remove'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    if ($action === 'add') {
        // Check if game is already in user's wishlist
        $checkStmt = $conn->prepare("SELECT id FROM user_wishlist WHERE user_id = ? AND game_id = ?");
        $checkStmt->bind_param("ii", $userId, $gameId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        // Only add if not already in wishlist
        if ($result->num_rows === 0) {
            // Insert new wishlist entry
            $stmt = $conn->prepare("INSERT INTO user_wishlist (user_id, game_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $userId, $gameId);
            $stmt->execute();
        }
    } else {
        // Remove game from wishlist
        $stmt = $conn->prepare("DELETE FROM user_wishlist WHERE user_id = ? AND game_id = ?");
        $stmt->bind_param("ii", $userId, $gameId);
        $stmt->execute();
    }
    
    // Return success response
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Handle database errors
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

// Close database connection
$conn->close(); 