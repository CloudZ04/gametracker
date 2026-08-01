<?php
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/steam_helpers.php';
session_start();

// Ensure clean output buffer
if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');

@set_time_limit(180);
ignore_user_abort(true);

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
    $result = updateAllSteamAchievements($conn, $_SESSION['user_id']);

    if (!empty($result['success'])) {
        echo json_encode([
            'success' => true,
            'updated' => $result['updated'] ?? 0,
            'skipped' => $result['skipped'] ?? 0,
            'checked' => $result['checked'] ?? 0,
            'message' => $result['message'] ?? 'Achievements refreshed.',
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['message'] ?? 'Failed to update achievements.',
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
