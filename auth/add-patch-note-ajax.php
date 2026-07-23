<?php
require_once '../includes/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $version = trim($_POST['version'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $release_date = $_POST['release_date'] ?? '';
    $content = trim($_POST['content'] ?? '');
    
    // Validate inputs
    if (empty($version) || empty($title) || empty($release_date) || empty($content)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit();
    }
    
    // Check if version already exists
    $check_stmt = $conn->prepare("SELECT id FROM patch_notes WHERE version = ?");
    $check_stmt->bind_param("s", $version);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'A patch note with this version already exists.']);
        exit();
    }
    
    // Handle image upload
    $image_url = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../images/patch-notes/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $filename = 'patch_' . time() . '_' . uniqid() . '.' . $file_extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
                $image_url = 'images/patch-notes/' . $filename;
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Failed to upload image.']);
                exit();
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid image format. Allowed: JPG, PNG, GIF, WEBP']);
            exit();
        }
    }
    
    // Insert patch note
    $stmt = $conn->prepare("INSERT INTO patch_notes (version, title, release_date, image_url, content) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $version, $title, $release_date, $image_url, $content);
    
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Patch note added successfully!']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to add patch note.']);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?> 