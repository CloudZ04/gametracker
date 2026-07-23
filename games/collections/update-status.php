<?php
// Include database connection and start session
require_once '../../includes/db.php';
session_start();

// Security check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Get game IDs and new status from JSON data
$game_ids = $input['game_ids'] ?? null;
$new_status = $input['new_status'] ?? null;

// Validate required data
if (!$game_ids || !$new_status) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

// Ensure game_ids is an array
if (!is_array($game_ids)) {
    $game_ids = [$game_ids];
}

try {
    // Start transaction
    $conn->begin_transaction();

    if ($new_status === 'remove') {
        // Remove games from collection entirely
        $stmt = $conn->prepare('DELETE FROM user_game_status WHERE user_id = ? AND game_id = ?');
        
        foreach ($game_ids as $game_id) {
            $stmt->bind_param('ii', $user_id, $game_id);
            $stmt->execute();
        }
    } else {
        // Update games to new status
        // First remove old status
        $delete_stmt = $conn->prepare('DELETE FROM user_game_status WHERE user_id = ? AND game_id = ?');
        
        // Then add new status
        $insert_stmt = $conn->prepare('INSERT INTO user_game_status (user_id, game_id, status, updated_at) VALUES (?, ?, ?, NOW())');
        
        foreach ($game_ids as $game_id) {
            // Remove old status
            $delete_stmt->bind_param('ii', $user_id, $game_id);
            $delete_stmt->execute();
            
            // Add new status
            $insert_stmt->bind_param('iis', $user_id, $game_id, $new_status);
            $insert_stmt->execute();
        }
    }

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
