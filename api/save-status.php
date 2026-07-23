<?php
// Add this at the very beginning of your save-status.php
error_log("Starting save-status.php");
ob_start();


require_once '../includes/db.php';
session_start();

// Clean any output that might have been generated
ob_clean();

// Set content type to JSON
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];
$gameId = $_POST['game_id'] ?? null;
$status = $_POST['status'] ?? '';

$valid_statuses = ['Want to Play', 'Playing', 'Beaten', 'Completed', 'Shelved', 'Abandoned', 'Clear'];

if (!$gameId || !in_array($status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit();
}

try {
    if ($status === 'Clear') {
        // Remove status
        $delete = $conn->prepare("DELETE FROM user_game_status WHERE user_id = ? AND game_id = ?");
        $delete->bind_param("ii", $userId, $gameId);
        $delete->execute();
        $delete->close();
        
        echo json_encode(['success' => true, 'status' => 'cleared']);
        exit();
    }

    // Insert/update status
    $stmt = $conn->prepare("SELECT id FROM user_game_status WHERE user_id = ? AND game_id = ?");
    $stmt->bind_param("ii", $userId, $gameId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $update = $conn->prepare("UPDATE user_game_status SET status = ?, updated_at = NOW() WHERE user_id = ? AND game_id = ?");
        $update->bind_param("sii", $status, $userId, $gameId);
        $update->execute();
        $update->close();
    } else {
        $insert = $conn->prepare("INSERT INTO user_game_status (user_id, game_id, status) VALUES (?, ?, ?)");
        $insert->bind_param("iis", $userId, $gameId, $status);
        $insert->execute();
        $insert->close();
    }

    // Log activity (optional - skip if table doesn't exist)
    try {
        $log = $conn->prepare("INSERT INTO activity_log (user_id, game_id, status) VALUES (?, ?, ?)");
        $log->bind_param("iis", $userId, $gameId, $status);
        $log->execute();
        $log->close();
    } catch (Exception $e) {
        // Ignore activity log errors - table might not exist
        error_log("Activity log failed: " . $e->getMessage());
    }

    $stmt->close();
    $conn->close();
    
    echo json_encode(['success' => true, 'status' => $status]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

// End output buffering and send clean output
ob_end_flush();
?>