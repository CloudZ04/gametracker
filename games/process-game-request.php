<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

require_once '../includes/db.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to submit a request']);
    exit;
}

// Initialize response array
$response = ['success' => false, 'message' => ''];

try {
    // Validate required fields
    if (empty($_POST['gameTitle']) || empty($_POST['platforms']) || empty($_POST['description'])) {
        throw new Exception('Please fill in all required fields');
    }

    $userId = $_SESSION['user_id'];
    $gameTitle = trim($_POST['gameTitle']);
    $releaseYear = !empty($_POST['releaseYear']) ? intval($_POST['releaseYear']) : null;
    $platforms = trim($_POST['platforms']);
    $description = trim($_POST['description']);
    $additionalInfo = !empty($_POST['additionalInfo']) ? trim($_POST['additionalInfo']) : null;
    $imagePath = null;
    $status = 'pending';

    // Handle image upload if provided
    if (isset($_FILES['gameImage']) && $_FILES['gameImage']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB

        // Validate file type and size
        if (!in_array($_FILES['gameImage']['type'], $allowedTypes)) {
            throw new Exception('Invalid file type. Please upload a JPEG, PNG, or WebP image.');
        }

        if ($_FILES['gameImage']['size'] > $maxFileSize) {
            throw new Exception('File is too large. Maximum size is 5MB.');
        }

        // Generate unique filename
        $extension = pathinfo($_FILES['gameImage']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('game_request_') . '.' . $extension;
        $uploadDir = '../uploads/game_requests/';

        // Create upload directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Move uploaded file
        if (move_uploaded_file($_FILES['gameImage']['tmp_name'], $uploadDir . $filename)) {
            $imagePath = 'uploads/game_requests/' . $filename;
        } else {
            throw new Exception('Failed to upload image');
        }
    }

    // Insert request into database
    $stmt = $conn->prepare("
        INSERT INTO game_requests (
            user_id, game_title, release_year, platforms, 
            image_path, description, additional_info, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "isisssss", 
        $userId, $gameTitle, $releaseYear, $platforms,
        $imagePath, $description, $additionalInfo, $status
    );

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $response['success'] = true;
    $response['message'] = 'Game request submitted successfully!';

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    
    // Delete uploaded image if request failed
    if (isset($imagePath) && file_exists('../' . $imagePath)) {
        unlink('../' . $imagePath);
    }
}

// Clear any output buffers and send JSON response
while (ob_get_level()) {
    ob_end_clean();
}
echo json_encode($response); 