<?php
require_once '../includes/db.php';
session_start();

// Get username from URL parameter and sanitize it
$username = isset($_GET['username']) ? htmlspecialchars($_GET['username']) : null;

if (!$username) {
    header('Location: ../index.php');
    exit();
}

// Fetch user information
$query = $conn->prepare("
    SELECT u.* 
    FROM users u 
    WHERE u.username = ?
");
$query->bind_param("s", $username);
$query->execute();
$result = $query->get_result();
$profile_user = $result->fetch_assoc();

if (!$profile_user) {
    $_SESSION['error'] = 'User not found';
    header('Location: ../index.php');
    exit();
}

// Check if logged-in user is viewing their own profile
$is_own_profile = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_user['id'];

// Check relationship status if not own profile
$relationship_status = null;
if (!$is_own_profile && isset($_SESSION['user_id'])) {
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
}

// Check if profile is visible
$can_view_profile = $is_own_profile || 
                   $profile_user['profile_visibility'] === 'public' ||
                   ($profile_user['profile_visibility'] === 'friends' && $relationship_status === 'friends');

if (!$can_view_profile) {
    $_SESSION['error'] = 'This profile is private';
    header('Location: ../index.php');
    exit();
}

// Generate initials from the viewed profile's username (use $profile_initials to avoid nav.php overwriting $initials)
$profile_initials = strtoupper(preg_replace('/[^A-Z]/i', '', $profile_user['username'][0] . ($profile_user['username'][1] ?? '')));

// Define game collection statuses
$collections = ['Want to Play', 'Playing', 'Beaten', 'Completed', 'Shelved', 'Abandoned'];
$collection_games = [];

// Fetch a random game for each collection status if collections are visible
if ($can_view_profile && ($is_own_profile || $profile_user['show_collections'])) {
    foreach ($collections as $status) {
        $sql = "SELECT g.*, ugs.status 
                FROM user_game_status ugs
                JOIN games g ON ugs.game_id = g.id
                WHERE ugs.user_id = ? AND ugs.status = ?
                ORDER BY RAND() LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $profile_user['id'], $status);
        $stmt->execute();
        $result = $stmt->get_result();
        $collection_games[$status] = $result->fetch_assoc();
    }
}

// Fetch random portrait images for hero section background
$heroImages = [];
$bgQuery = $conn->query("SELECT portrait_image_url FROM games WHERE portrait_image_url IS NOT NULL AND portrait_image_url != '' ORDER BY RAND() LIMIT 6");
while ($row = $bgQuery->fetch_assoc()) {
    $heroImages[] = $row['portrait_image_url'];
}

// Get follower and following counts
$followerQuery = $conn->prepare("SELECT COUNT(*) as count FROM user_relationships WHERE following_id = ? AND status = 'following'");
$followerQuery->bind_param("i", $profile_user['id']);
$followerQuery->execute();
$followerCount = $followerQuery->get_result()->fetch_assoc()['count'];

$followingQuery = $conn->prepare("SELECT COUNT(*) as count FROM user_relationships WHERE follower_id = ? AND status = 'following'");
$followingQuery->bind_param("i", $profile_user['id']);
$followingQuery->execute();
$followingCount = $followingQuery->get_result()->fetch_assoc()['count'];

// Get friend count (count unique friendships)
$friendQuery = $conn->prepare("
    SELECT COUNT(DISTINCT 
        CASE 
            WHEN follower_id = ? THEN following_id 
            ELSE follower_id 
        END
    ) as count 
    FROM user_relationships 
    WHERE (
        (follower_id = ? AND status = 'friends') OR 
        (following_id = ? AND status = 'friends')
    )
");
$friendQuery->bind_param("iii", $profile_user['id'], $profile_user['id'], $profile_user['id']);
$friendQuery->execute();
$friendCount = $friendQuery->get_result()->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($profile_user['name'] ?: $profile_user['username']) ?>'s Profile | GameTracker.gg</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/styles.css">
    <style>
        .profile-header {
            background: #1e1e2f;
            border-radius: 16px;
            margin: 2rem 0;
            padding: 1.5rem;
            border: 1px solid rgba(127, 0, 255, 0.1);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), transparent);
            border-radius: 1rem 1rem 0 0;
        }

        .profile-content {
            display: flex;
            gap: 1.5rem;
        }

        .profile-avatar-container {
            width: 90px;
            height: 90px;
        }

        .profile-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: none;
            box-shadow: 0 4px 15px rgba(127, 0, 255, 0.2);
        }

        .profile-avatar-initials {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: #b200ff;
            color: white;
            font-weight: 600;
            font-size: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            box-shadow: 0 4px 15px rgba(127, 0, 255, 0.2);
        }

        .profile-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .profile-main-row {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .profile-name {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.25rem;
            margin: 0;
            color: #fff;
        }

        .profile-username {
            color: #a8a8b3;
            font-size: 0.9rem;
        }

        .profile-about {
            color: #a8a8b3;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            max-width: 600px;
        }

        .profile-stats {
            display: flex;
            gap: 1rem;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.75rem;
            background: #15151e;
            border-radius: 6px;
            border: 1px solid rgba(127, 0, 255, 0.1);
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            border-color: rgba(127, 0, 255, 0.3);
            transform: translateY(-2px);
        }

        .stat-value {
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
        }

        .stat-label {
            color: #a8a8b3;
            font-size: 0.85rem;
        }

        .profile-actions {
            display: flex;
            gap: 0.75rem;
            margin-left: auto;
        }

        .btn-action {
            padding: 0.4rem 0.75rem;
            font-family: 'Exo 2', sans-serif;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-follow {
            background: rgba(127, 0, 255, 0.1);
            border: 1px solid rgba(127, 0, 255, 0.2);
            color: #fff;
        }

        .btn-follow:hover {
            background: rgba(127, 0, 255, 0.2);
            border-color: rgba(127, 0, 255, 0.4);
            transform: translateY(-2px);
        }

        .btn-follow.following {
            background: #b200ff;
            border-color: #b200ff;
            color: white;
        }

        .btn-friend {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid rgba(76, 175, 80, 0.2);
            color: #fff;
        }

        .btn-friend:hover {
            background: rgba(76, 175, 80, 0.2);
            border-color: rgba(76, 175, 80, 0.4);
            transform: translateY(-2px);
        }

        .btn-friend.friends {
            background: #4CAF50;
            border-color: #4CAF50;
            color: white;
        }

        .btn-friend.pending {
            background: #6c757d;
            border-color: #6c757d;
            color: white;
            opacity: 0.7;
            cursor: not-allowed;
        }

        .profile-section {
            background: var(--card-bg);
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            padding: 2rem;
            margin-top: 2rem;
        }

        .section-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--primary-color);
            font-size: 1.25em;
        }

        .private-content {
            text-align: center;
            padding: 3rem;
            background: rgba(30, 30, 47, 0.5);
            border-radius: 12px;
            border: 1px solid rgba(127, 0, 255, 0.1);
        }

        .private-content i {
            font-size: 3rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .private-content h4 {
            color: var(--text-light);
            margin-bottom: 0.5rem;
            font-family: 'Orbitron', sans-serif;
        }

        .private-content p {
            color: var(--text-muted);
            margin-bottom: 0;
            font-size: 0.95rem;
        }

        .achievement-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .achievement-card {
            background: rgba(30, 30, 47, 0.5);
            border: 1px solid rgba(127, 0, 255, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .achievement-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary-color);
            box-shadow: 0 4px 20px rgba(127, 0, 255, 0.2);
        }

        .game-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }

        .game-card {
            position: relative;
            background: #15151e;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            aspect-ratio: 3/4;
        }

        .game-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(127, 0, 255, 0.2);
        }

        .game-card:hover .game-image {
            transform: scale(1.05);
        }

        .game-card:hover .game-overlay {
            opacity: 1;
        }

        .game-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .game-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to top, rgba(21, 21, 30, 0.95) 0%, rgba(21, 21, 30, 0.7) 50%, rgba(21, 21, 30, 0.3) 100%);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 1rem;
            opacity: 0.9;
            transition: opacity 0.3s ease;
        }

        .game-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-playing {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid rgba(76, 175, 80, 0.3);
            color: #4CAF50;
        }

        .status-completed {
            background: rgba(255, 193, 7, 0.2);
            border: 1px solid rgba(255, 193, 7, 0.3);
            color: #FFC107;
        }

        .status-beaten {
            background: rgba(156, 39, 176, 0.2);
            border: 1px solid rgba(156, 39, 176, 0.3);
            color: #9C27B0;
        }

        .status-abandoned {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid rgba(244, 67, 54, 0.3);
            color: #F44336;
        }

        .status-shelved {
            background: rgba(255, 152, 0, 0.2);
            border: 1px solid rgba(255, 152, 0, 0.3);
            color: #FF9800;
        }

        .status-want-to-play {
            background: rgba(33, 150, 243, 0.2);
            border: 1px solid rgba(33, 150, 243, 0.3);
            color: #2196F3;
        }

        .game-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 1rem;
            color: #fff;
            margin-bottom: 0.5rem;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .game-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.8rem;
            color: #a8a8b3;
        }

        .game-meta-item {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .game-meta-item i {
            font-size: 1rem;
            opacity: 0.7;
        }

        .review-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .review-card {
            background: rgba(30, 30, 47, 0.5);
            border: 1px solid rgba(127, 0, 255, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .review-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary-color);
            box-shadow: 0 4px 20px rgba(127, 0, 255, 0.2);
        }

        .activity-card {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .activity-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .activity-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 12px;
        }

        .review-game {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: white;
        }

        .review-rating {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .review-text {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .review-game-info {
            position: relative;
            margin-bottom: 1rem;
        }

        .review-game-image {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .review-game-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
            padding: 1rem;
            border-radius: 0 0 8px 8px;
        }

        .review-game-title {
            color: white;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .review-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .review-rating i {
            color: var(--primary-color);
            font-size: 0.9rem;
        }

        .rating-text {
            color: white;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .review-title {
            color: white;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .review-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }

        .review-date {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .btn-review-link {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .btn-review-link:hover {
            color: white;
        }

        .ratings-overview-card {
            background: rgba(30, 30, 47, 0.5);
            border: 1px solid rgba(127, 0, 255, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
        }

        .ratings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .total-reviews {
            text-align: center;
        }

        .review-count {
            display: block;
            font-size: 2rem;
            font-weight: bold;
            color: white;
        }

        .review-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .average-rating {
            text-align: center;
        }

        .big-rating {
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
        }

        .rating-stars {
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .rating-bars {
            margin-top: 1rem;
        }

        .rating-bar-row {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            gap: 0.5rem;
        }

        .rating-label {
            color: white;
            font-size: 0.9rem;
            min-width: 40px;
        }

        .rating-bar-container {
            flex: 1;
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
        }

        .rating-bar {
            height: 100%;
            background: var(--primary-color);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .rating-count {
            color: var(--text-muted);
            font-size: 0.8rem;
            min-width: 20px;
            text-align: right;
        }

        .achievements-overview-card {
            background: rgba(30, 30, 47, 0.5);
            border: 1px solid rgba(127, 0, 255, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
        }

        .achievements-header {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .achievements-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            display: block;
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            font-family: 'Orbitron', sans-serif;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .achievements-details {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin: 1rem 0;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(127, 0, 255, 0.1);
            gap: 1rem;
        }

        .detail-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .detail-value {
            color: white;
            font-weight: 600;
        }

        .completion-bar {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .completion-progress {
            height: 100%;
            background: var(--primary-color);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .btn-view-details {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.3s ease;
            text-align: center;
            padding: 0.5rem;
            border: 1px solid var(--primary-color);
            border-radius: 6px;
            display: inline-block;
        }

        .btn-view-details:hover {
            color: white;
            background: var(--primary-color);
        }

        .recent-achievements-card {
            background: rgba(30, 30, 47, 0.5);
            border: 1px solid rgba(127, 0, 255, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
        }

        .recent-achievements-card h4 {
            color: white;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .recent-achievements-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .achievement-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .achievement-item:hover {
            background: rgba(127, 0, 255, 0.1);
        }

        .achievement-game {
            flex-shrink: 0;
        }

        .achievement-game-image {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 6px;
        }

        .achievement-info {
            flex: 1;
            min-width: 0;
        }

        .achievement-name {
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .achievement-game-name {
            color: var(--primary-color);
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .achievement-date {
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        .hero-section {
            position: relative;
            padding: 4rem 0 2rem;
            background: linear-gradient(to right, rgba(21, 21, 30, 0.9), rgba(21, 21, 30, 0.7));
            border-bottom: 1px solid rgba(127, 0, 255, 0.3);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .hero-image-stack {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
            opacity: 0.1;
            pointer-events: none;
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .hero-image-stack img {
            height: 300px;
            object-fit: cover;
            transform: rotate(-15deg) translateY(-10%);
            border-radius: 8px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.4);
            filter: brightness(0.7) contrast(1.2);
        }

        .collection-flex {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 1.5rem;
            width: 100%;
        }

        .collection-flex a {
            text-decoration: none !important;
            display: block;
            width: 100%;
        }

        .collection-card {
            background-color: var(--card-bg);
            border-radius: 0.75rem;
            overflow: hidden;
            position: relative;
            height: 280px;
            transition: all 0.3s ease;
            border: 1px solid rgba(127, 0, 255, 0.1);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            width: 100%;
        }

        .collection-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.3), 0 0 20px rgba(127, 0, 255, 0.3);
            border-color: rgba(127, 0, 255, 0.3);
        }

        .collection-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.7);
        }

        /* Status banner styles */
        .status-banner {
            position: absolute;
            top: 50%;
            left: 0;
            width: 100%;
            transform: translateY(-50%);
            color: #fff;
            font-family: 'Orbitron', sans-serif;
            font-size: 1.1rem;
            font-weight: bold;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0,0,0,0.25);
            padding: 0.7rem 0;
            z-index: 3;
            opacity: 0.97;
        }

        .game-count {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(30,30,47,0.85);
            color: #fff;
            font-family: 'Exo 2', sans-serif;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            z-index: 2;
            text-align: center;
        }

        @media (max-width: 992px) {
            .profile-main-row {
                flex-wrap: wrap;
                gap: 1rem;
            }

            .profile-stats {
                order: 2;
                width: 100%;
                justify-content: space-between;
            }

            .profile-actions {
                order: 1;
                margin-left: auto;
            }
        }

        @media (max-width: 768px) {
            .profile-content {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 1rem;
            }

            .profile-avatar,
            .profile-avatar-initials {
                width: 80px;
                height: 80px;
                font-size: 1.75rem;
            }

            .profile-main-row {
                justify-content: center;
            }

            .profile-actions {
                width: 100%;
                justify-content: center;
                margin-left: 0;
            }

            .btn-action {
                flex: 1;
                justify-content: center;
            }

            .stat-item {
                flex: 1;
                justify-content: center;
                padding: 0.35rem;
            }
            .hero-section {
                padding: 3rem 0 1.5rem;
            }

            .hero-image-stack img {
                height: 200px;
            }

            .profile-info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .profile-stats {
                margin-left: 0;
                width: 100%;
                justify-content: space-between;
            }

            .stat-item {
                flex: 1;
                justify-content: center;
            }
        }
        .collection-flex {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 1rem;
            width: 100%;
        }

        .collection-link {
            text-decoration: none !important;
            color: white;
        }

        .collection-card {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            aspect-ratio: 3/4;
            transition: transform 0.2s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .collection-card:hover {
            transform: translateY(-4px);
        }

        .game-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            opacity: 0.5;
        }

        .status-banner {
            position: absolute;
            left: 0;
            width: 100%;
            top: 50%;
            transform: translateY(-50%);
            text-align: center;
            padding: 1rem;
            background: inherit;
        }

        .status-name {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.2rem;
            color: white;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .game-count {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
            font-family: 'Exo 2', sans-serif;
        }

        @media (max-width: 1400px) {
            .collection-flex {
                grid-template-columns: repeat(5, 1fr);
            }
        }

        @media (max-width: 1200px) {
            .collection-flex {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 992px) {
            .collection-flex {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .collection-flex {
                grid-template-columns: repeat(2, 1fr);
            }
            .status-name {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 576px) {
            .collection-flex {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php include '../includes/nav.php'; ?>

<div class="container">
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-content">
            <div class="profile-avatar-container">
                <?php if (!empty($profile_user['profile_image'])): ?>
                    <img src="<?= strpos($profile_user['profile_image'], 'uploads/profiles/') === 0 ? htmlspecialchars($profile_user['profile_image']) : 'uploads/profiles/' . htmlspecialchars($profile_user['profile_image']) ?>" alt="Profile Image" class="profile-avatar">
                <?php else: ?>
                    <div class="profile-avatar-initials"><?= $profile_initials ?></div>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <div class="profile-main-row">
                    <h2 class="profile-name"><?= htmlspecialchars($profile_user['name'] ?: $profile_user['username']) ?></h2>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?= $followerCount ?></div>
                            <div class="stat-label">Followers</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $followingCount ?></div>
                            <div class="stat-label">Following</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $friendCount ?></div>
                            <div class="stat-label">Friends</div>
                        </div>
                    </div>

                    <?php if (!$is_own_profile && isset($_SESSION['user_id'])): ?>
                        <div class="profile-actions">
                            <button class="btn btn-action btn-follow <?= $relationship_status === 'following' ? 'following' : '' ?>" 
                                    onclick="toggleFollow(<?= $profile_user['id'] ?>)">
                                <i class="bi <?= $relationship_status === 'following' ? 'bi-person-check-fill' : 'bi-person-plus' ?>"></i>
                                <?= $relationship_status === 'following' ? 'Following' : 'Follow' ?>
                            </button>
                            <?php if ($relationship_status === 'friends'): ?>
                                <button class="btn btn-action btn-friend friends" onclick="removeFriend(<?= $profile_user['id'] ?>)">
                                    <i class="bi bi-people-fill"></i>
                                    Friends
                                </button>
                            <?php elseif ($relationship_status === 'friend_request'): ?>
                                <button class="btn btn-action btn-friend pending" disabled>
                                    <i class="bi bi-clock"></i>
                                    Request Sent
                                </button>
                            <?php else: ?>
                                <button class="btn btn-action btn-friend" onclick="sendFriendRequest(<?= $profile_user['id'] ?>)">
                                    <i class="bi bi-people"></i>
                                    Add Friend
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="profile-username">@<?= htmlspecialchars($profile_user['username']) ?></div>
                
                <?php if (!empty($profile_user['about'])): ?>
                    <p class="profile-about"><?= nl2br(htmlspecialchars($profile_user['about'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Collections Section -->
    <div class="my-5">
        <h3 class="section-title mb-4"><?= $profile_user['username'] ?>'s Collections</h3>
        <?php if ($is_own_profile || ($profile_user && isset($profile_user['show_collections']) && $profile_user['show_collections'])): ?>
            <div class="collection-flex">
                <?php
                $collection_statuses = [
                    'Want to Play' => 'linear-gradient(to right, #0a9999,rgb(82, 138, 221))',
                    'Playing' => 'linear-gradient(to right, #2e8b57,rgb(133, 211, 88))',
                    'Beaten' => 'linear-gradient(to right, #5f2c82,rgb(105, 89, 197))',
                    'Completed' => 'linear-gradient(to right,rgba(255, 217, 0, 0.85),rgb(255, 174, 0))',
                    'Shelved' => 'linear-gradient(to right, #c96a25,rgb(221, 138, 82))',
                    'Abandoned' => 'linear-gradient(to right, #c0392b,rgb(170, 83, 83))'
                ];

                $profile_user_id = $profile_user['id'];

                foreach ($collection_statuses as $status => $banner_color) {
                    // Fetch a random game for this status
                    $sql = "SELECT g.* FROM user_game_status ugs JOIN games g ON ugs.game_id = g.id WHERE ugs.user_id = ? AND ugs.status = ? ORDER BY RAND() LIMIT 1";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('is', $profile_user_id, $status);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $game = $result->fetch_assoc();
                    $image = null;
                    if ($game) {
                        $image = !empty($game['portrait_image_url']) ? $game['portrait_image_url'] : (!empty($game['image_url']) ? $game['image_url'] : null);
                    }
                    // Count total games in this collection
                    $count_sql = "SELECT COUNT(*) FROM user_game_status WHERE user_id = ? AND status = ?";
                    $count_stmt = $conn->prepare($count_sql);
                    $count_stmt->bind_param('is', $profile_user_id, $status);
                    $count_stmt->execute();
                    $count_stmt->bind_result($game_count);
                    $count_stmt->fetch();
                    $count_stmt->close();
                    $slug = strtolower(str_replace(' ', '-', $status));
                ?>
                <?php $collection_url = "../games/collections/" . $slug . ".php?user=" . urlencode($profile_user['username']); ?>
                <a href="<?php echo htmlspecialchars($collection_url); ?>" class="text-decoration-none">
                    <div class="collection-card">
                        <div class="position-relative overflow-hidden" style="width: 100%; height: 280px; margin: 0;">
                            <?php if ($image): ?>
                                <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($status) ?>" style="width: 100%; height: 100%; object-fit: cover; filter: brightness(0.7); position: absolute; top: 0; left: 0; z-index: 1;">
                            <?php endif; ?>
                            <!-- Status Banner -->
                            <div class="status-banner" style="background: <?= strpos($banner_color, 'gradient') !== false ? $banner_color : $banner_color ?>;">
                                <?= htmlspecialchars($status) ?>
                            </div>
                            <!-- Game Count -->
                            <div class="game-count">
                                <?= $game_count > 0 ? $game_count . ' game' . ($game_count > 1 ? 's' : '') : 'No games yet' ?>
                            </div>
                        </div>
                    </div>
                </a>
                <?php } ?>
            </div>
        <?php else: ?>
            <div class="private-content">
                <i class="bi bi-lock"></i>
                <h4>Collections are Private</h4>
                <p>This user has chosen to keep their game collections private.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Activity -->
    <?php if ($is_own_profile || ($profile_user && isset($profile_user['show_activity']) && $profile_user['show_activity'])): ?>
        <div class="profile-section">
            <h3 class="section-title">
                <i class="bi bi-activity"></i>
                Recent Activity
            </h3>
            <?php
            $activityQuery = $conn->prepare("
                SELECT games.id, games.title, games.image_url, games.portrait_image_url, user_game_status.status 
                FROM user_game_status 
                JOIN games ON user_game_status.game_id = games.id 
                WHERE user_game_status.user_id = ? 
                ORDER BY user_game_status.updated_at DESC 
                LIMIT 12
            ");
            $activityQuery->bind_param("i", $profile_user['id']);
            $activityQuery->execute();
            $activityResult = $activityQuery->get_result();

            $status_colors = [
                'Want to Play' => 'linear-gradient(to right, #0a9999,rgb(82, 138, 221))',
                'Playing' => 'linear-gradient(to right, #2e8b57,rgb(133, 211, 88))',
                'Beaten' => 'linear-gradient(to right, #5f2c82,rgb(105, 89, 197))',
                'Completed' => 'linear-gradient(to right,rgba(255, 217, 0, 0.85),rgb(255, 174, 0))',
                'Shelved' => 'linear-gradient(to right, #c96a25,rgb(221, 138, 82))',
                'Abandoned' => 'linear-gradient(to right, #c0392b,rgb(170, 83, 83))'
            ];
            ?>
            
            <?php if ($activityResult->num_rows > 0): ?>
                <div class="row g-4">
                    <?php while ($activity = $activityResult->fetch_assoc()): ?>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                            <a href="../games/game-detail.php?id=<?= $activity['id'] ?>" class="text-decoration-none">
                                <div class="activity-card" style="--activity-color: <?= $status_colors[$activity['status']] ?? '#7f00ff' ?>;">
                                    <?php
                                        $image = !empty($activity['portrait_image_url']) ? $activity['portrait_image_url'] : $activity['image_url'];
                                    ?>
                                    <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($activity['title']) ?>" class="img-fluid shadow">
                                    <div style="position: absolute; bottom: 0; left: 0; width: 100%; height: 4px; background: var(--activity-color);"></div>
                                </div>
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center p-5 bg-dark rounded">
                    <i class="bi bi-controller" style="font-size: 3rem; color: var(--primary-color);"></i>
                    <h4 class="mt-3">No activity yet</h4>
                    <p class="text">This user hasn't added any games to their collections yet.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="profile-section">
            <div class="private-content">
                <i class="bi bi-lock"></i>
                <h4>Activity is Private</h4>
                <p>This user has chosen to keep their activity private.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Reviews -->
    <?php if ($is_own_profile || ($profile_user && isset($profile_user['show_reviews']) && $profile_user['show_reviews'])): ?>
        <div class="profile-section">
            <h3 class="section-title">
                <i class="bi bi-star"></i>
                Reviews
            </h3>
            <?php
            // Get rating distribution
            $ratingDistQuery = $conn->prepare("
                SELECT 
                    rating, 
                    COUNT(*) as count 
                FROM reviews 
                WHERE user_id = ? 
                GROUP BY rating 
                ORDER BY rating DESC
            ");
            $ratingDistQuery->bind_param("i", $profile_user['id']);
            $ratingDistQuery->execute();
            $ratingDistResult = $ratingDistQuery->get_result();
            
            // Initialize counts for all ratings (1-5)
            $ratingDist = array_fill(1, 5, 0);
            $totalReviews = 0;
            $ratingSum = 0;
            
            // Fill in actual counts
            while($row = $ratingDistResult->fetch_assoc()) {
                $rating = (int)$row['rating'];
                $count = (int)$row['count'];
                $ratingDist[$rating] = $count;
                $totalReviews += $count;
                $ratingSum += ($rating * $count);
            }
            
            $avgRating = $totalReviews > 0 ? round($ratingSum / $totalReviews, 1) : 0;

            // Get recent reviews for display
            $reviewsQuery = $conn->prepare("
                SELECT r.*, g.title as game_title, g.image_url, g.portrait_image_url, g.id as game_id
                FROM reviews r 
                JOIN games g ON r.game_id = g.id 
                WHERE r.user_id = ? 
                ORDER BY r.created_at DESC 
                LIMIT 6
            ");
            $reviewsQuery->bind_param("i", $profile_user['id']);
            $reviewsQuery->execute();
            $reviewsResult = $reviewsQuery->get_result();
            ?>
            
            <div class="row g-4">
                <!-- Reviews Column (70%) -->
                <div class="col-lg-8">
                    <?php if ($reviewsResult->num_rows > 0): ?>
                        <div class="row g-4">
                            <?php while ($review = $reviewsResult->fetch_assoc()): ?>
                                <div class="col-md-6">
                                    <div class="review-card">
                                        <div class="review-game-info">
                                            <?php
                                                $gameImage = !empty($review['portrait_image_url']) ? $review['portrait_image_url'] : $review['image_url'];
                                            ?>
                                            <img src="<?= htmlspecialchars($gameImage) ?>" alt="<?= htmlspecialchars($review['game_title']) ?>" class="review-game-image">
                                            <div class="review-game-overlay">
                                                <h4 class="review-game-title"><?= htmlspecialchars($review['game_title']) ?></h4>
                                                <div class="review-rating">
                                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                                        <?php if ($i <= $review['rating']): ?>
                                                            <i class="bi bi-star-fill" style="color: #7f00ff;"></i>
                                                        <?php elseif ($i - 0.5 <= $review['rating']): ?>
                                                            <i class="bi bi-star-half" style="color: #7f00ff;"></i>
                                                        <?php else: ?>
                                                            <i class="bi bi-star" style="color: #7f00ff;"></i>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                    <span class="rating-text"><?= number_format($review['rating'], 1) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="review-content">
                                            <?php if (!empty($review['review_title'])): ?>
                                                <h5 class="review-title"><?= htmlspecialchars($review['review_title']) ?></h5>
                                            <?php endif; ?>
                                            <p class="review-text"><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                                            <div class="review-footer">
                                                <span class="review-date"><?= date('M j, Y', strtotime($review['created_at'])) ?></span>
                                                <a href="../games/game-detail.php?id=<?= $review['game_id'] ?>" class="btn-review-link">
                                                    View Game <i class="bi bi-arrow-right-short"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-5 bg-dark rounded">
                            <i class="bi bi-pencil-square" style="font-size: 3rem; color: var(--primary-color);"></i>
                            <h4 class="mt-3">No reviews yet</h4>
                            <p class="text">This user hasn't written any reviews yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Ratings Distribution Column (30%) -->
                <div class="col-lg-4">
                    <div class="ratings-overview-card">
                        <div class="ratings-header">
                            <div class="total-reviews">
                                <span class="review-count"><?= $totalReviews ?></span>
                                <span class="review-label">Total Ratings</span>
                            </div>
                            <?php if ($totalReviews > 0): ?>
                            <div class="average-rating">
                                <div class="big-rating"><?= number_format($avgRating, 1) ?></div>
                                <div class="rating-stars">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $avgRating): ?>
                                            <i class="bi bi-star-fill" style="color: #7f00ff;"></i>
                                        <?php elseif ($i - 0.5 <= $avgRating): ?>
                                            <i class="bi bi-star-half" style="color: #7f00ff;"></i>
                                        <?php else: ?>
                                            <i class="bi bi-star" style="color: #7f00ff;"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="rating-bars">
                            <?php for($i = 5; $i >= 1; $i--): ?>
                                <div class="rating-bar-row">
                                    <div class="rating-label"><?= $i ?> <i class="bi bi-star-fill" style="color: #7f00ff;"></i></div>
                                    <div class="rating-bar-container">
                                        <div class="rating-bar" style="width: <?= $totalReviews > 0 ? ($ratingDist[$i] / $totalReviews) * 100 : 0 ?>%; background: #7f00ff;"></div>
                                    </div>
                                    <div class="rating-count"><?= $ratingDist[$i] ?></div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="profile-section">
            <div class="private-content">
                <i class="bi bi-lock"></i>
                <h4>Reviews are Private</h4>
                <p>This user has chosen to keep their reviews private.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Achievements -->
    <?php if ($is_own_profile || ($profile_user && isset($profile_user['show_achievements']) && $profile_user['show_achievements'])): ?>
        <div class="profile-section">
            <h3 class="section-title">
                <i class="bi bi-trophy"></i>
                Achievements
            </h3>
            <?php
            // Get achievement statistics
            $achievementQuery = $conn->prepare("
                SELECT 
                    SUM(total_achievements) as total_achievements,
                    SUM(unlocked_achievements) as unlocked_achievements
                FROM steam_achievement_stats 
                WHERE user_id = ?
            ");
            $achievementQuery->bind_param("i", $profile_user['id']);
            $achievementQuery->execute();
            $achievementResult = $achievementQuery->get_result();
            $achievementStats = $achievementResult->fetch_assoc();
            
            $totalAchievements = $achievementStats['total_achievements'] ?? 0;
            $unlockedAchievements = $achievementStats['unlocked_achievements'] ?? 0;
            $completionPercentage = $totalAchievements > 0 ? round(($unlockedAchievements / $totalAchievements) * 100, 1) : 0;
            
            // Get games with achievements
            $gamesWithAchievementsQuery = $conn->prepare("
                SELECT COUNT(DISTINCT game_id) as games_with_achievements
                FROM steam_achievement_stats 
                WHERE user_id = ?
            ");
            $gamesWithAchievementsQuery->bind_param("i", $profile_user['id']);
            $gamesWithAchievementsQuery->execute();
            $gamesWithAchievementsResult = $gamesWithAchievementsQuery->get_result();
            $gamesWithAchievements = $gamesWithAchievementsResult->fetch_assoc()['games_with_achievements'] ?? 0;
            ?>
            
            <div class="row g-4">
                <!-- Achievements Overview -->
                <div class="col-lg-8">
                    <div class="achievements-overview-card">
                        <div class="achievements-header">
                            <div class="achievements-stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?= number_format($unlockedAchievements) ?></span>
                                    <span class="stat-label">Achievements unlocked</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?= $completionPercentage ?>%</span>
                                    <span class="stat-label">Completion</span>
                                </div>
                            </div>
                            <div class="achievements-details">
                                <div class="detail-item">
                                    <span class="detail-label">Total Achievements</span>
                                    <span class="detail-value"><?= number_format($totalAchievements) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Games with Achievements</span>
                                    <span class="detail-value"><?= number_format($gamesWithAchievements) ?></span>
                                </div>
                            </div>
                            <div class="completion-bar">
                                <div class="completion-progress" style="width: <?= $completionPercentage ?>%; background: #7f00ff;"></div>
                            </div>
                            <a href="../games/achievements.php" class="btn-view-details">
                                View Details <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Achievements -->
                <div class="col-lg-4">
                    <div class="recent-achievements-card">
                        <h4>Recent Achievements</h4>
                        <?php
                        $recentAchievementsQuery = $conn->prepare("
                            SELECT sa.*, g.title as game_title, g.image_url, g.portrait_image_url
                            FROM steam_achievements sa
                            JOIN games g ON sa.game_id = g.id
                            WHERE sa.user_id = ? AND sa.unlocked = 1
                            ORDER BY sa.unlock_time DESC
                            LIMIT 5
                        ");
                        $recentAchievementsQuery->bind_param("i", $profile_user['id']);
                        $recentAchievementsQuery->execute();
                        $recentAchievementsResult = $recentAchievementsQuery->get_result();
                        ?>
                        
                        <?php if ($recentAchievementsResult->num_rows > 0): ?>
                            <div class="recent-achievements-list">
                                <?php while ($achievement = $recentAchievementsResult->fetch_assoc()): ?>
                                    <div class="achievement-item">
                                        <div class="achievement-game">
                                            <?php
                                                $gameImage = !empty($achievement['portrait_image_url']) ? $achievement['portrait_image_url'] : $achievement['image_url'];
                                            ?>
                                            <img src="<?= htmlspecialchars($gameImage) ?>" alt="<?= htmlspecialchars($achievement['game_title']) ?>" class="achievement-game-image">
                                        </div>
                                        <div class="achievement-info">
                                            <div class="achievement-name"><?= htmlspecialchars($achievement['achievement_name']) ?></div>
                                            <div class="achievement-game-name"><?= htmlspecialchars($achievement['game_title']) ?></div>
                                            <div class="achievement-date"><?= date('M j, Y', strtotime($achievement['unlock_time'])) ?></div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-3">
                                <i class="bi bi-trophy" style="font-size: 2rem; color: var(--primary-color);"></i>
                                <p class="text mt-2">No achievements unlocked yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="profile-section">
            <div class="private-content">
                <i class="bi bi-lock"></i>
                <h4>Achievements are Private</h4>
                <p>This user has chosen to keep their achievements private.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleFollow(userId) {
    const button = event.target.closest('.btn-follow');
    const icon = button.querySelector('i');
    const isFollowing = button.classList.contains('following');
    
    fetch('../api/toggle-follow.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ user_id: userId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (isFollowing) {
                // Unfollow
                button.classList.remove('following');
                icon.className = 'bi bi-person-plus';
                button.innerHTML = '<i class="bi bi-person-plus"></i>Follow';
            } else {
                // Follow
                button.classList.add('following');
                icon.className = 'bi bi-person-check-fill';
                button.innerHTML = '<i class="bi bi-person-check-fill"></i>Following';
            }
        } else {
            showToast(data.message || 'Failed to update follow status', 'error');
        }
    })
    .catch(() => showToast('An error occurred', 'error'));
}

function sendFriendRequest(userId) {
    const button = event.target.closest('.btn-friend');
    fetch('../api/send-friend-request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.innerHTML = '<i class="bi bi-clock"></i>Request Sent';
            button.classList.add('pending');
            button.disabled = true;
        } else {
            showToast(data.message || 'Failed to send friend request', 'error');
        }
    })
    .catch(() => showToast('An error occurred', 'error'));
}

function removeFriend(userId) {
    const button = event.target.closest('.btn-friend');
    showConfirm('Are you sure you want to remove this friend?', function() {
        fetch('../api/remove-friend.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                button.innerHTML = '<i class="bi bi-people"></i>Add Friend';
                button.classList.remove('friends');
                button.onclick = function() { sendFriendRequest(userId); };
            } else {
                showToast(data.message || 'Failed to remove friend', 'error');
            }
        })
        .catch(() => showToast('An error occurred', 'error'));
    });
}
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html> 