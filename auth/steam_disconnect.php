<?php
require_once '../includes/db.php';
session_start();

// Security check
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

try {
    // Begin transaction
    $conn->begin_transaction();

    // Remove Steam ID from user
    $stmt = $conn->prepare("UPDATE users SET steam_id = NULL WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();

    // Delete achievement data
    $stmt = $conn->prepare("DELETE FROM steam_achievements WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();

    // Delete achievement stats
    $stmt = $conn->prepare("DELETE FROM steam_achievement_stats WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();

    // Delete owned games
    $stmt = $conn->prepare("DELETE FROM steam_owned_games WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();

    // Don't remove from session anymore
    // unset($_SESSION['steam_id']);  // Remove this line

    // Commit transaction
    $conn->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 