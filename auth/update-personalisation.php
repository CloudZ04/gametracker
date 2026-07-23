<?php
require_once '../includes/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

try {
    // Get the selected theme
    $theme = $_POST['theme'] ?? '';
    
    // Validate theme
    $valid_themes = ['default', 'dark', 'jedi', 'cyberpunk', 'retro', 'steampunk', 'neon', 'forest', 'space', 'candy', 'matrix'];
    
    if (!in_array($theme, $valid_themes)) {
        throw new Exception('Invalid theme selected');
    }
    
    // Update the user's theme in the database
    $stmt = $conn->prepare("UPDATE users SET theme = ? WHERE id = ?");
    $stmt->bind_param("si", $theme, $user_id);
    
    if ($stmt->execute()) {
        $response = [
            'success' => true, 
            'message' => 'Theme updated successfully!',
            'theme' => $theme
        ];
    } else {
        throw new Exception('Failed to update theme');
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $response = [
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?> 