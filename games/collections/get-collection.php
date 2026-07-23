<?php
require_once '../../includes/db.php';
session_start();

// Security check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// Get status from query parameter
$status = isset($_GET['status']) ? $_GET['status'] : null;

if (!$status) {
    http_response_code(400);
    echo json_encode(['error' => 'Status parameter is required']);
    exit();
}

// Fetch games for the logged-in user and specified status
$stmt = $conn->prepare("
    SELECT g.*
    FROM user_game_status ugs
    JOIN games g ON ugs.game_id = g.id
    WHERE ugs.user_id = ? AND ugs.status = ?
    ORDER BY ugs.updated_at DESC
");

$stmt->bind_param('is', $_SESSION['user_id'], $status);
$stmt->execute();
$result = $stmt->get_result();

$games = [];
while ($game = $result->fetch_assoc()) {
    // Only include necessary fields
    $games[] = [
        'id' => $game['id'],
        'title' => $game['title'],
        'image_url' => $game['image_url'],
        'portrait_image_url' => $game['portrait_image_url']
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['games' => $games]);
?> 