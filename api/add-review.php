<?php
require_once '../includes/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];
$gameId = $_POST['game_id'] ?? null;
$rating = isset($_POST['rating']) ? floatval($_POST['rating']) : null;
$reviewTitle = $_POST['review_title'] ?? '';
$reviewText = $_POST['review_text'] ?? '';
$isPublic = isset($_POST['is_public']) ? (bool)$_POST['is_public'] : true;

// Validate inputs
function logReviewError($msg) {
    file_put_contents(__DIR__ . '/review_error.log', date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

if (!$gameId || !$rating || $rating < 0.5 || $rating > 5.0) {
    logReviewError('Invalid input: gameId=' . $gameId . ', rating=' . $rating);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input: rating must be 0.5 to 5.0']);
    exit();
}

try {
    // Check if user already has a review for this game
    $checkStmt = $conn->prepare("SELECT id FROM reviews WHERE user_id = ? AND game_id = ?");
    $checkStmt->bind_param("ii", $userId, $gameId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing review
        $updateStmt = $conn->prepare("UPDATE reviews SET rating = ?, review_title = ?, review_text = ?, is_public = ?, updated_at = NOW() WHERE user_id = ? AND game_id = ?");
        if (!$updateStmt) { logReviewError('Prepare failed: ' . $conn->error); }
        $updateStmt->bind_param("dssiii", $rating, $reviewTitle, $reviewText, $isPublic, $userId, $gameId);
        $success = $updateStmt->execute();
        if (!$success) { logReviewError('Update failed: ' . $updateStmt->error); }
        $updateStmt->close();
    } else {
        // Insert new review
        $insertStmt = $conn->prepare("INSERT INTO reviews (user_id, game_id, rating, review_title, review_text, is_public) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$insertStmt) { logReviewError('Prepare failed: ' . $conn->error); }
        $insertStmt->bind_param("iidssi", $userId, $gameId, $rating, $reviewTitle, $reviewText, $isPublic);
        $success = $insertStmt->execute();
        if (!$success) { logReviewError('Insert failed: ' . $insertStmt->error); }
        $insertStmt->close();
    }
    
    if ($success) {
        // Update game's average rating
        $avgStmt = $conn->prepare("
            UPDATE games g 
            SET avg_rating = (
                SELECT AVG(rating) 
                FROM reviews 
                WHERE game_id = ? AND is_public = 1
            ),
            total_reviews = (
                SELECT COUNT(*) 
                FROM reviews 
                WHERE game_id = ? AND is_public = 1
            )
            WHERE g.id = ?
        ");
        if (!$avgStmt) { logReviewError('Prepare failed (avg): ' . $conn->error); }
        $avgStmt->bind_param("iii", $gameId, $gameId, $gameId);
        $avgStmt->execute();
        if ($avgStmt->error) { logReviewError('Avg update failed: ' . $avgStmt->error); }
        $avgStmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Review saved successfully']);
    } else {
        logReviewError('Failed to save review: ' . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Failed to save review: ' . $conn->error]);
    }
    
} catch (Exception $e) {
    logReviewError('Exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?> 