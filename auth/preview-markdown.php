<?php
require_once '../includes/db.php';
require_once '../parsedown/Parsedown.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$content = $data['content'] ?? '';

if (empty($content)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No content provided']);
    exit();
}

try {
    $parsedown = new Parsedown();
    $parsedown->setSafeMode(true);
    $html = $parsedown->text($content);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error parsing Markdown: ' . $e->getMessage()
    ]);
}
?> 