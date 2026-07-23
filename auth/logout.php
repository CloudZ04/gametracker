<?php
// Start the session to access session data
session_start();

// Include database connection (though not used in logout, included for consistency)
require_once '../includes/db.php';

// Clear all session variables
session_unset();

// Destroy the session completely
session_destroy();

// Redirect user back to the explore page after logout
header('Location: ../explore.php');
exit();
?>
