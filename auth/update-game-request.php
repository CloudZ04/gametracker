<?php
require_once '../includes/db.php';
session_start();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Initialize response array
$response = ['success' => false, 'message' => ''];

try {
    // Validate input
    if (!isset($_POST['request_id']) || !isset($_POST['status'])) {
        throw new Exception('Missing required parameters');
    }

    $requestId = intval($_POST['request_id']);
    $status = $_POST['status'];

    // Validate status
    if (!in_array($status, ['approved', 'rejected'])) {
        throw new Exception('Invalid status');
    }

    // Update request status
    $stmt = $conn->prepare("UPDATE game_requests SET status = ? WHERE request_id = ?");
    $stmt->bind_param('si', $status, $requestId);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Request status updated successfully';
    } else {
        throw new Exception('Failed to update request status');
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response); 