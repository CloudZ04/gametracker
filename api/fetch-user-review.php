<?php
require_once '../includes/db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];
$gameId = $_GET['game_id'] ?? null;
if (!$gameId) {
    echo json_encode(['success' => false, 'message' => 'No game_id provided']);
    exit;
}

$stmt = $conn->prepare("SELECT rating, review_title, review_text, is_public FROM reviews WHERE user_id = ? AND game_id = ? LIMIT 1");
$stmt->bind_param('ii', $userId, $gameId);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    echo json_encode(['success' => true, 'review' => $row]);
} else {
    echo json_encode(['success' => true, 'review' => null]);
}
$stmt->close();
$conn->close(); 