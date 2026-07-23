<?php
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    
    // Debug: Log received values
    $debug_data = [
        'notify_friend_requests' => $_POST['notify_friend_requests'] ?? 'NOT_SET',
        'notify_new_followers' => $_POST['notify_new_followers'] ?? 'NOT_SET',
        'notify_achievements' => $_POST['notify_achievements'] ?? 'NOT_SET',
        'notify_followed_games' => $_POST['notify_followed_games'] ?? 'NOT_SET',
        'notify_game_achievements' => $_POST['notify_game_achievements'] ?? 'NOT_SET',
        'notify_reviews' => $_POST['notify_reviews'] ?? 'NOT_SET',
        'notify_activity' => $_POST['notify_activity'] ?? 'NOT_SET'
    ];
    
    // Get notification preferences
    $notify_friend_requests = ($_POST['notify_friend_requests'] ?? '0') === '1' ? 1 : 0;
    $notify_new_followers = ($_POST['notify_new_followers'] ?? '0') === '1' ? 1 : 0;
    $notify_achievements = ($_POST['notify_achievements'] ?? '0') === '1' ? 1 : 0;
    $notify_followed_games = ($_POST['notify_followed_games'] ?? '0') === '1' ? 1 : 0;
    $notify_game_achievements = ($_POST['notify_game_achievements'] ?? '0') === '1' ? 1 : 0;
    $notify_reviews = ($_POST['notify_reviews'] ?? '0') === '1' ? 1 : 0;
    $notify_activity = ($_POST['notify_activity'] ?? '0') === '1' ? 1 : 0;

    try {
        $stmt = $conn->prepare("
            UPDATE users 
            SET notify_friend_requests = ?,
                notify_new_followers = ?,
                notify_achievements = ?,
                notify_followed_games = ?,
                notify_game_achievements = ?,
                notify_reviews = ?,
                notify_activity = ?
            WHERE id = ?
        ");
        
        if (!$stmt) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'SQL prepare failed: ' . $conn->error]);
            exit();
        }
        
        $stmt->bind_param("iiiiiiii", 
            $notify_friend_requests,
            $notify_new_followers,
            $notify_achievements,
            $notify_followed_games,
            $notify_game_achievements,
            $notify_reviews,
            $notify_activity,
            $user_id
        );

        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Notification preferences updated successfully!',
                'debug' => $debug_data
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to update notification preferences: ' . $stmt->error]);
        }

        $stmt->close();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'An error occurred while updating notification preferences: ' . $e->getMessage()]);
    }
    exit();
}

header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request method']);
exit(); 