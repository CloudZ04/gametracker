<?php
// Security check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

// Check if viewing another user's collection
$viewing_username = isset($_GET['user']) ? $_GET['user'] : null;
$is_own_profile = true;
$profile_user = null;

if ($viewing_username) {
    // Fetch the profile user's information
    $query = $conn->prepare("
        SELECT u.* 
        FROM users u 
        WHERE u.username = ?
    ");
    $query->bind_param("s", $viewing_username);
    $query->execute();
    $result = $query->get_result();
    $profile_user = $result->fetch_assoc();

    if (!$profile_user) {
        $_SESSION['error'] = 'User not found';
        header('Location: ../../index.php');
        exit();
    }

    $is_own_profile = ($_SESSION['user_id'] == $profile_user['id']);

    // Check relationship status if not own profile
    if (!$is_own_profile) {
        $relationshipQuery = $conn->prepare("
            SELECT status 
            FROM user_relationships 
            WHERE follower_id = ? AND following_id = ?
        ");
        $relationshipQuery->bind_param("ii", $_SESSION['user_id'], $profile_user['id']);
        $relationshipQuery->execute();
        $relationshipResult = $relationshipQuery->get_result();
        $relationship = $relationshipResult->fetch_assoc();
        $relationship_status = $relationship ? $relationship['status'] : null;

        // Check if profile is visible
        $can_view_profile = $profile_user['profile_visibility'] === 'public' ||
                          ($profile_user['profile_visibility'] === 'friends' && $relationship_status === 'friends');

        // Check if collections are visible
        if (!$can_view_profile || !$profile_user['show_collections']) {
            $_SESSION['error'] = 'This profile is private';
            header('Location: ../../index.php');
            exit();
        }

        // Check individual collection visibility
        $collection_visibility = [
            'Want to Play' => $profile_user['show_want_to_play'] ?? 1,
            'Playing' => $profile_user['show_playing'] ?? 1,
            'Beaten' => $profile_user['show_beaten'] ?? 1,
            'Completed' => $profile_user['show_completed'] ?? 1,
            'Shelved' => $profile_user['show_shelved'] ?? 1,
            'Abandoned' => $profile_user['show_abandoned'] ?? 1
        ];

        if (!isset($collection_visibility[$status]) || !$collection_visibility[$status]) {
            $_SESSION['error'] = 'This collection is private';
            header('Location: ../../index.php');
            exit();
        }
    }
}

// Use the appropriate user ID based on whose collection we're viewing
$user_id = $viewing_username ? $profile_user['id'] : $_SESSION['user_id'];
?> 