<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = $_POST;
}

$gameId = (int)($body['game_id'] ?? 0);
$characterId = (int)($body['character_id'] ?? 0);

if ($gameId <= 0 || $characterId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'game_id and character_id are required']);
    exit;
}

try {
    $conn->begin_transaction();

    $media = $conn->prepare("DELETE FROM character_media WHERE game_id = ? AND character_id = ?");
    $media->bind_param("ii", $gameId, $characterId);
    $media->execute();

    $link = $conn->prepare("DELETE FROM game_characters WHERE game_id = ? AND character_id = ?");
    $link->bind_param("ii", $gameId, $characterId);
    $link->execute();
    $removed = $link->affected_rows;

    $conn->commit();

    echo json_encode([
        'success' => true,
        'removed' => $removed,
        'message' => $removed ? 'Character unlinked from this game.' : 'No link found.',
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
