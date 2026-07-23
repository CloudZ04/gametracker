<?php
// Include database connection and start session
require_once '../includes/db.php';
session_start();

// Authentication check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Set response type to JSON
header('Content-Type: application/json');

// Get user ID from session
$userId = $_SESSION['user_id'];

// Sanitize user input data
$name = $conn->real_escape_string($_POST['name'] ?? '');
$about = $_POST['about'] ?? ''; // Don't escape the about field to preserve line breaks
$profileImage = '';  // Default empty value for profile image

// Get privacy settings
$profile_visibility = $conn->real_escape_string($_POST['profile_visibility'] ?? 'public');
$show_achievements = isset($_POST['show_achievements']) ? 1 : 0;
$show_reviews = isset($_POST['show_reviews']) ? 1 : 0;
$show_collections = isset($_POST['show_collections']) ? 1 : 0;
$show_activity = isset($_POST['show_activity']) ? 1 : 0;

// Handle profile image upload if provided
if (!empty($_FILES['profile_image']['name'])) {
    // Create uploads directory if it doesn't exist
    $targetDir = "uploads/profiles/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    // Generate unique filename using timestamp
    $filename = time() . "_" . basename($_FILES['profile_image']['name']);
    $targetFile = $targetDir . $filename;

    // Move uploaded file to target directory
    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFile)) {
        $profileImage = $conn->real_escape_string($targetFile);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
        exit();
    }
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Build dynamic SQL query based on provided data
    $sql = "UPDATE users SET 
            name = ?, 
            about = ?,
            profile_visibility = ?,
            show_achievements = ?,
            show_reviews = ?,
            show_collections = ?,
            show_activity = ?";
    $params = [$name, $about, $profile_visibility, $show_achievements, $show_reviews, $show_collections, $show_activity];
    $types = "sssssss";

    // Add profile image to query if one was uploaded
    if (!empty($profileImage)) {
        $sql .= ", profile_image = ?";
        $params[] = $profileImage;
        $types .= "s";
    }

    // Add WHERE clause for user ID
    $sql .= " WHERE id = ?";
    $params[] = $userId;
    $types .= "i";

    // Prepare and execute the update query
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    // Keep session in sync so nav shows the new image on every page
    if (!empty($profileImage)) {
        $_SESSION['profile_image'] = $profileImage;
    }

    // Prepare response data
    $response = [
        'success' => true,
        'message' => 'Profile updated successfully',
        'name' => $name,
        'about' => str_replace(['\r\n', '\r', '\n'], ["\n", "\n", "\n"], $about) // Normalize line breaks
    ];

    // Add profile image path to response if updated
    if (!empty($profileImage)) {
        $response['profile_image'] = $profileImage;
    }

    echo json_encode($response);
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log("Error in update-profile.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating profile']);
}
?>
