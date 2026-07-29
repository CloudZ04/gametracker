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

$gameId     = (int)($body['game_id'] ?? 0);
$name       = trim((string)($body['name'] ?? ''));
$imageUrl   = trim((string)($body['image_url'] ?? ''));
$bio        = trim((string)($body['bio'] ?? ''));
$role       = trim((string)($body['role'] ?? 'supporting'));
$isPlayable = !empty($body['is_playable']) ? 1 : 0;
$isFeatured = !empty($body['is_featured']) ? 1 : 0;
$manualWeight = isset($body['manual_weight']) && $body['manual_weight'] !== ''
    ? (float)$body['manual_weight']
    : null;

$allowedRoles = ['main', 'supporting', 'antagonist', 'minor', 'cameo'];
if (!in_array($role, $allowedRoles, true)) {
    $role = 'supporting';
}

if ($gameId <= 0 || $name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Game and character name are required']);
    exit;
}

$check = $conn->prepare("SELECT id FROM games WHERE id = ?");
$check->bind_param("i", $gameId);
$check->execute();
if (!$check->get_result()->fetch_assoc()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Game not found']);
    exit;
}

$slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
$slug = trim($slug, '-');
if ($slug === '') {
    $slug = 'character-' . time();
}

$baseSlug = $slug;
$i = 1;
$slugCheck = $conn->prepare("SELECT id FROM characters WHERE slug = ?");
while (true) {
    $slugCheck->bind_param("s", $slug);
    $slugCheck->execute();
    if (!$slugCheck->get_result()->fetch_assoc()) break;
    $slug = $baseSlug . '-' . $i;
    $i++;
}

$img = $imageUrl !== '' ? $imageUrl : null;
$bioVal = $bio !== '' ? $bio : null;
$portrait = $img;
$sourceScore = 10.0;

try {
    $conn->begin_transaction();

    $ins = $conn->prepare("
        INSERT INTO characters (name, slug, giantbomb_id, image_url, portrait_image_url, bio)
        VALUES (?, ?, NULL, ?, ?, ?)
    ");
    $ins->bind_param("sssss", $name, $slug, $img, $portrait, $bioVal);
    $ins->execute();
    $characterId = (int)$conn->insert_id;

    if ($manualWeight === null) {
        $link = $conn->prepare("
            INSERT INTO game_characters (game_id, character_id, role, is_playable, is_featured, source_score)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $link->bind_param("iisiid", $gameId, $characterId, $role, $isPlayable, $isFeatured, $sourceScore);
    } else {
        $link = $conn->prepare("
            INSERT INTO game_characters (game_id, character_id, role, is_playable, is_featured, source_score, manual_weight)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $link->bind_param("iisiidd", $gameId, $characterId, $role, $isPlayable, $isFeatured, $sourceScore, $manualWeight);
    }
    $link->execute();

    if ($img) {
        $media = $conn->prepare("
            INSERT INTO character_media (character_id, game_id, kind, url, credit, is_primary)
            VALUES (?, ?, 'headshot', ?, 'Manual', 1)
        ");
        $media->bind_param("iis", $characterId, $gameId, $img);
        $media->execute();
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'character_id' => $characterId,
        'message' => "Added \"{$name}\" to the game.",
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save character: ' . $e->getMessage()]);
}
