<?php
require_once 'includes/db.php';
session_start();

// Ensure only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Validate input
if (isset($_POST['id']) && isset($_POST['completed'])) {
    $id = (int)$_POST['id'];
    $completed = (int)$_POST['completed'];

    // Update roadmap item
    $stmt = $conn->prepare("UPDATE roadmap_items SET is_completed = ? WHERE id = ?");
    $stmt->bind_param("ii", $completed, $id);
    $stmt->execute();
}

header("Location: roadmap.php");
exit();
