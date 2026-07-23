<?php
// Include database connection and start session
require_once '../includes/db.php';
session_start();

// Security check: Ensure user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../explore.php');
    exit();
}

// Get game ID from URL parameter
$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: manage-games.php');
    exit();
}

// Delete game from database using prepared statement
$stmt = $conn->prepare("DELETE FROM games WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

// Redirect back to game management page
header('Location: manage-games.php');
exit();
?>
