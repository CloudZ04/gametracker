<?php
require_once '../../includes/db.php';
session_start();

// Security check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

// Set the status for this collection page
$status = 'Playing';
$collection_label = 'Currently Playing';
$empty_state_message = "You haven't started playing any games yet. Add some games to track your current gaming adventures!";

// Include common collection header logic
require_once 'includes/collection-header.php';

// Fetch games for this user and status using prepared statement
$stmt = $conn->prepare("
    SELECT g.*
    FROM user_game_status ugs
    JOIN games g ON ugs.game_id = g.id
    WHERE ugs.user_id = ? AND ugs.status = ?
    ORDER BY ugs.updated_at DESC
");
$stmt->bind_param('is', $user_id, $status);
$stmt->execute();
$result = $stmt->get_result();

$games = [];
while ($game = $result->fetch_assoc()) {
    $games[] = $game;
}
$total_games = count($games);

// Fetch random portrait images for hero section background
$heroImages = [];
$bgQuery = $conn->query("SELECT portrait_image_url FROM games WHERE portrait_image_url IS NOT NULL AND portrait_image_url != '' ORDER BY RAND() LIMIT 6");
while ($row = $bgQuery->fetch_assoc()) {
    $heroImages[] = $row['portrait_image_url'];
}

// Include the collection template
require_once 'includes/collection-template.php';

// Include footer
require_once '../../includes/footer.php';
?>

