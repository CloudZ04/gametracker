<?php
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/steam_helpers.php';
session_start();

@set_time_limit(300);
@ini_set('memory_limit', '512M');

// Ensure clean output buffer
if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit();
}

try {
    $result = findAndUpdateSteamIds($conn, STEAM_API_KEY);

    $updated = (int)($result['updated'] ?? 0);
    $total = (int)($result['total'] ?? 0);
    $exact = (int)($result['exact'] ?? 0);
    $fuzzy = (int)($result['fuzzy'] ?? 0);
    $unmatchedCount = count($result['unmatched'] ?? []);

    echo json_encode([
        'success' => true,
        'message' => "Updated $updated of $total games ($exact exact, $fuzzy fuzzy, $unmatchedCount unmatched)",
        'updated' => $updated,
        'total' => $total,
        'exact' => $exact,
        'fuzzy' => $fuzzy,
        'unmatched' => $result['unmatched'] ?? [],
        'updated_titles' => $result['updated_titles'] ?? [],
    ]);
} catch (Throwable $e) {
    error_log("Error updating Steam IDs: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error updating Steam IDs: ' . $e->getMessage()
    ]);
}
exit();
