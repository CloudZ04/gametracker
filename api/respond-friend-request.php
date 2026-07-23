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
$from_user_id = $data['user_id'] ?? null;
$action = $data['action'] ?? null; // 'accept' or 'decline'

if (!$from_user_id || !in_array($action, ['accept', 'decline'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request', 'debug' => $data]);
    exit();
}

try {
    // Check if friend request exists (try both directions and multiple possible status values)
    $checkQuery = $conn->prepare("
        SELECT id, status, follower_id, following_id
        FROM user_relationships 
        WHERE (
            (follower_id = ? AND following_id = ?) OR 
            (follower_id = ? AND following_id = ?)
        ) AND (status IN ('friend_request', 'pending', 'request') OR status = '')
    ");
    $checkQuery->bind_param("iiii", $from_user_id, $_SESSION['user_id'], $_SESSION['user_id'], $from_user_id);
    $checkQuery->execute();
    $result = $checkQuery->get_result();
    $request = $result->fetch_assoc();

    if (!$request) {
        // Debug: Let's see what friend requests exist for this user
        $debugQuery = $conn->prepare("
            SELECT follower_id, following_id, status 
            FROM user_relationships 
            WHERE following_id = ? OR follower_id = ?
        ");
        $debugQuery->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
        $debugQuery->execute();
        $debugResult = $debugQuery->get_result();
        $debugRelationships = [];
        while ($row = $debugResult->fetch_assoc()) {
            $debugRelationships[] = $row;
        }
        
        echo json_encode([
            'success' => false, 
            'message' => 'Friend request not found',
            'debug' => [
                'from_user_id' => $from_user_id,
                'current_user_id' => $_SESSION['user_id'],
                'existing_relationships' => $debugRelationships
            ]
        ]);
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    if ($action === 'accept') {
        // Delete the friend request (handle both directions and empty status strings)
        $deleteQuery = $conn->prepare("
            DELETE FROM user_relationships 
            WHERE (
                (follower_id = ? AND following_id = ?) OR 
                (follower_id = ? AND following_id = ?)
            ) AND (status = 'friend_request' OR status = '')
        ");
        $deleteQuery->bind_param("iiii", $from_user_id, $_SESSION['user_id'], $_SESSION['user_id'], $from_user_id);
        $deleteQuery->execute();

        // Create mutual friend relationship
        $insertQuery = $conn->prepare("
            INSERT INTO user_relationships (follower_id, following_id, status) 
            VALUES (?, ?, 'friends'), (?, ?, 'friends')
        ");
        $insertQuery->bind_param("iiii", $from_user_id, $_SESSION['user_id'], $_SESSION['user_id'], $from_user_id);
        $insertQuery->execute();

        // Create notification for the sender
        $notificationQuery = $conn->prepare("
            INSERT INTO notifications (user_id, from_user_id, type, message) 
            VALUES (?, ?, 'friend_accepted', ?)
        ");
        
        // Get the acceptor's username for the notification message
        $acceptorQuery = $conn->prepare("SELECT username, name FROM users WHERE id = ?");
        $acceptorQuery->bind_param("i", $_SESSION['user_id']);
        $acceptorQuery->execute();
        $acceptor = $acceptorQuery->get_result()->fetch_assoc();
        $acceptorName = $acceptor['name'] ?: $acceptor['username'];
        
        $message = "$acceptorName accepted your friend request";
        $notificationQuery->bind_param("iis", $from_user_id, $_SESSION['user_id'], $message);
        $notificationQuery->execute();

        $responseMessage = 'Friend request accepted';
    } else {
        // Delete the friend request (handle both directions and empty status strings)
        $deleteQuery = $conn->prepare("
            DELETE FROM user_relationships 
            WHERE (
                (follower_id = ? AND following_id = ?) OR 
                (follower_id = ? AND following_id = ?)
            ) AND (status = 'friend_request' OR status = '')
        ");
        $deleteQuery->bind_param("iiii", $from_user_id, $_SESSION['user_id'], $_SESSION['user_id'], $from_user_id);
        $deleteQuery->execute();

        // Create notification for the sender
        $notificationQuery = $conn->prepare("
            INSERT INTO notifications (user_id, from_user_id, type, message) 
            VALUES (?, ?, 'friend_declined', ?)
        ");
        
        // Get the decliner's username for the notification message
        $declinerQuery = $conn->prepare("SELECT username, name FROM users WHERE id = ?");
        $declinerQuery->bind_param("i", $_SESSION['user_id']);
        $declinerQuery->execute();
        $decliner = $declinerQuery->get_result()->fetch_assoc();
        $declinerName = $decliner['name'] ?: $decliner['username'];
        
        $message = "$declinerName declined your friend request";
        $notificationQuery->bind_param("iis", $from_user_id, $_SESSION['user_id'], $message);
        $notificationQuery->execute();

        $responseMessage = 'Friend request declined';
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => $responseMessage]);
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log("Error in respond-friend-request.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?> 