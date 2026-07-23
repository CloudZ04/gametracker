<?php
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    
    // Get privacy settings
    $profile_visibility = $_POST['profile_visibility'] ?? 'private';
    $show_collections = isset($_POST['show_collections']) && ($_POST['show_collections'] === '1' || $_POST['show_collections'] === 'on') ? 1 : 0;
    $show_activity = isset($_POST['show_activity']) && ($_POST['show_activity'] === '1' || $_POST['show_activity'] === 'on') ? 1 : 0;
    $show_reviews = isset($_POST['show_reviews']) && ($_POST['show_reviews'] === '1' || $_POST['show_reviews'] === 'on') ? 1 : 0;
    $show_achievements = isset($_POST['show_achievements']) && ($_POST['show_achievements'] === '1' || $_POST['show_achievements'] === 'on') ? 1 : 0;

    try {
        $stmt = $conn->prepare("
            UPDATE users 
            SET profile_visibility = ?,
                show_collections = ?,
                show_activity = ?,
                show_reviews = ?,
                show_achievements = ?
            WHERE id = ?
        ");
        
        $stmt->bind_param("siiiii", 
            $profile_visibility,
            $show_collections,
            $show_activity,
            $show_reviews,
            $show_achievements,
            $user_id
        );

        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to update privacy settings']);
        }

        $stmt->close();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'An error occurred while updating privacy settings']);
    }
    exit();
}

header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request method']);
exit(); 