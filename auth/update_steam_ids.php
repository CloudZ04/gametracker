<?php
require_once '../includes/db.php';
require_once '../includes/steam_helpers.php';
session_start();

// Ensure clean output buffer
if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit();
}

try {
    if (!defined('STEAM_API_KEY')) {
        define('STEAM_API_KEY', '4AC57954A37BD60630F8B7CD313B2338');
    }
    
    $count = findAndUpdateSteamIds($conn, STEAM_API_KEY);
    
    if ($count === false) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to update Steam IDs. Check steam_debug.log for details.'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => "Updated $count games with Steam App IDs"
        ]);
    }
} catch (Throwable $e) {
    error_log("Error updating Steam IDs: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error updating Steam IDs: ' . $e->getMessage()
    ]);
}
exit(); 