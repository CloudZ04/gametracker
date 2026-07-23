<?php
require_once '../includes/db.php';
require_once '../includes/steam_helpers.php';
session_start();

// Ensure clean output buffer
if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Get user's Steam ID from database
$steamQuery = $conn->prepare("SELECT steam_id FROM users WHERE id = ?");
$steamQuery->bind_param("i", $_SESSION['user_id']);
$steamQuery->execute();
$steamResult = $steamQuery->get_result();
$steamId = $steamResult->fetch_assoc()['steam_id'] ?? null;

if (empty($steamId)) {
    echo json_encode(['success' => false, 'error' => 'Steam account not connected']);
    exit();
}

try {
    if (!defined('STEAM_API_KEY')) {
        define('STEAM_API_KEY', '4AC57954A37BD60630F8B7CD313B2338');
    }

    // Update achievements for all games
    $success = updateAllSteamAchievements($conn, $_SESSION['user_id']);

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to update achievements. Check steam_debug.log for details.'
        ]);
    }
} catch (Throwable $e) {
    error_log("Error refreshing achievements: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error refreshing achievements: ' . $e->getMessage()
    ]);
}
exit(); 