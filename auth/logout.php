<?php
session_start();
require_once '../includes/db.php';

// Delete remember_me token from DB if one exists
if (isset($_COOKIE['remember_me'])) {
    $tokenHash = hash('sha256', $_COOKIE['remember_me']);
    $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE token = ?");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    setcookie('remember_me', '', time() - 3600, '/', '', false, true);
}

session_unset();
session_destroy();

header('Location: ../explore.php');
exit();
?>
