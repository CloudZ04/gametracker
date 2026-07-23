<?php
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();

setcookie('remember_me', '', time() - 3600, '/', '', false, true);

echo json_encode(['success' => true]);
?>
