<?php
// Include database connection and start session
require_once '../includes/db.php';
session_start();

// Authentication check: Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information from session
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch user's profile information from database
$query = $conn->prepare("SELECT profile_image, name, about FROM users WHERE id = ?");
$query->bind_param("i", $userId);
$query->execute();
$result = $query->get_result();
$user = $result->fetch_assoc();

// Generate initials from username for avatar fallback
$initials = strtoupper(preg_replace('/[^A-Z]/i', '', $username[0] . ($username[1] ?? '')));

// Define game collection statuses
$collections = ['Want to Play', 'Playing', 'Beaten', 'Completed', 'Shelved', 'Abandoned'];
$user_id = $_SESSION['user_id'];
$collection_games = [];

// Fetch a random game for each collection status
foreach ($collections as $status) {
    $sql = "SELECT g.*, ugs.status 
            FROM user_game_status ugs
            JOIN games g ON ugs.game_id = g.id
            WHERE ugs.user_id = ? AND ugs.status = ?
            ORDER BY RAND() LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $user_id, $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $collection_games[$status] = $result->fetch_assoc(); // will be null if none
}

// Fetch random portrait images for hero section background
$heroImages = [];
$bgQuery = $conn->query("SELECT portrait_image_url FROM games WHERE portrait_image_url IS NOT NULL AND portrait_image_url != '' ORDER BY RAND() LIMIT 6");
while ($row = $bgQuery->fetch_assoc()) {
    $heroImages[] = $row['portrait_image_url'];
}

// Get user's Steam ID from database
$steamQuery = $conn->prepare("SELECT steam_id FROM users WHERE id = ?");
$steamQuery->bind_param("i", $_SESSION['user_id']);
$steamQuery->execute();
$steamResult = $steamQuery->get_result();
$steamId = $steamResult->fetch_assoc()['steam_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($username) ?>'s Profile | GameTracker.gg</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/styles.css">
    <style>
        :root {
            --primary-color: #b200ff;
            --primary-hover: #9933ff;
            --dark-bg: #15151e;
            --card-bg: #1e1e2f;
            --card-hover-bg: #2a2a3d;
            --text-light: #ffffff;
            --text-muted: #a8a8b3;
            --glow-shadow: 0 0 20px rgba(127, 0, 255, 0.5);
        }
        
        body {
            background-color: var(--dark-bg);
            font-family: 'Exo 2', sans-serif;
            color: var(--text-light);
            min-height: 100vh;
            background-image: 
                radial-gradient(circle at 10% 10%, rgba(127, 0, 255, 0.03) 0%, transparent 70%),
                radial-gradient(circle at 90% 90%, rgba(127, 0, 255, 0.03) 0%, transparent 70%);
        }
        
        .profile-header {
            background: linear-gradient(135deg, rgba(30, 30, 47, 0.95), rgba(21, 21, 30, 0.9));
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            margin-top: 2rem;
            padding: 2rem;
            border: 1px solid rgba(127, 0, 255, 0.2);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
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
        
        .profile-avatar {
            position: relative;
            flex-shrink: 0;
        }
        
        .profile-avatar img {
            width: 128px;
            height: 128px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 0 15px rgba(127, 0, 255, 0.5);
            border: 3px solid rgba(127, 0, 255, 0.3);
            transition: all 0.3s ease;
        }
        
        .profile-avatar img:hover {
            transform: scale(1.05);
            box-shadow: 0 0 25px rgba(127, 0, 255, 0.7);
        }
        
        .profile-avatar-initials {
            background: linear-gradient(135deg, #b200ff, #9933ff);
            color: white;
            font-weight: bold;
            font-size: 2rem;
            width: 128px;
            height: 128px;
            border-radius: 50%;
            text-align: center;
            line-height: 128px;
            display: inline-block;
            box-shadow: 0 0 15px rgba(127, 0, 255, 0.5);
            border: 3px solid rgba(127, 0, 255, 0.3);
            transition: all 0.3s ease;
        }
        
        .profile-avatar-initials:hover {
            transform: scale(1.05);
            box-shadow: 0 0 25px rgba(127, 0, 255, 0.7);
        }
        
        .profile-info {
            flex: 1;
        }
        
        .profile-info h2 {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.5rem;
            margin-bottom: 0.2rem;
            background: linear-gradient(to right, #ffffff, #a8a8b3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .text-muted {
            font-size: 1rem !important;
            margin-bottom: 0.5rem !important;
            color: var(--text-muted) !important;
        }
        
        .about-text {
            font-family: 'Exo 2', sans-serif;
            font-size: 0.95rem;
            color: #ccc;
            line-height: 1.5;
            max-width: 800px;
        }
        
        .text-age {
            font-size: 0.95rem;
            color: #aaa;
            margin-bottom: 0.5rem;
        }
        
        .edit-btn .btn {
            background: transparent;
            border: 2px solid rgba(127, 0, 255, 0.5);
            color: white;
            transition: all 0.3s ease;
            font-family: 'Exo 2', sans-serif;
            padding: 0.5rem 1.5rem;
        }
        
        .edit-btn .btn:hover {
            background: rgba(127, 0, 255, 0.2);
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(127, 0, 255, 0.3);
        }
        
        .section-title {
            font-family: 'Orbitron', sans-serif;
            margin-bottom: 1.5rem;
            position: relative;
            display: inline-block;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 60px;
            height: 4px;
            background: var(--primary-color);
            border-radius: 2px;
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
        }
        
        @media (max-width: 576px) {
            .collection-flex {
                grid-template-columns: 1fr;
            }
        }
        
/* Enhanced Profile Navigation Tabs - Matching Main Navigation Style */
.nav-tabs {
    border-bottom: 2px solid rgba(178, 0, 255, 0.2);
    background: linear-gradient(135deg, rgba(21, 21, 30, 0.8) 0%, rgba(26, 26, 38, 0.8) 100%);
    border-radius: 12px 12px 0 0;
    padding: 0.5rem 1rem 0;
    margin-bottom: 2rem;
    position: relative;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

.nav-tabs::before {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, 
        transparent,
        rgba(178, 0, 255, 0.3) 20%,
        rgba(178, 0, 255, 0.6) 50%,
        rgba(178, 0, 255, 0.3) 80%,
        transparent
    );
}

.nav-tabs .nav-item {
    margin-bottom: 0;
    margin-right: 0.5rem;
}

.nav-tabs .nav-link {
    font-family: 'Exo 2', sans-serif;
    font-size: 1rem;
    font-weight: 500;
    letter-spacing: 0.03em;
    color: #e0e0e0 !important;
    padding: 0.75rem 1.25rem;
    border: none;
    border-radius: 8px 8px 0 0;
    position: relative;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    display: flex;
    align-items: center;
    background: transparent;
    margin-bottom: 0;
}

.nav-tabs .nav-link:hover {
    color: #ffffff !important;
    background: rgba(178, 0, 255, 0.1);
    transform: translateY(-2px);
    border-color: transparent;
}

.nav-tabs .nav-link.active {
    color: #b200ff !important;
    background: rgba(178, 0, 255, 0.15);
    font-weight: 600;
    border-color: transparent;
    border-bottom: none;
}

.nav-tabs .nav-link.active::before {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #b200ff, #8b00cc);
    border-radius: 2px 2px 0 0;
}

.nav-tabs .nav-link.active::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, 
        transparent,
        rgba(178, 0, 255, 0.5) 30%,
        rgba(178, 0, 255, 0.8) 50%,
        rgba(178, 0, 255, 0.5) 70%,
        transparent
    );
    border-radius: 8px 8px 0 0;
}

/* Icon styling in tabs */
.nav-tabs .nav-link i {
    font-size: 1.1rem;
    margin-right: 0.4rem;
    opacity: 0.8;
    transition: opacity 0.3s ease;
}

.nav-tabs .nav-link:hover i,
.nav-tabs .nav-link.active i {
    opacity: 1;
}

/* Badge styling for friend requests */
.nav-tabs .nav-link .badge {
    background: linear-gradient(135deg, #ff4757, #ff3742) !important;
    border: 1px solid rgba(21, 21, 30, 0.8);
    font-size: 0.65rem;
    font-weight: 600;
    min-width: 18px;
    height: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    margin-left: 0.4rem;
    animation: pulse-badge 2s ease-in-out infinite;
}

@keyframes pulse-badge {
    0%, 100% { 
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(255, 71, 87, 0.4);
    }
    50% { 
        transform: scale(1.05);
        box-shadow: 0 0 0 4px rgba(255, 71, 87, 0.1);
    }
}

/* Focus states for accessibility */
.nav-tabs .nav-link:focus {
    outline: none;
    box-shadow: none;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .nav-tabs {
        padding: 0.25rem 0.5rem 0;
        overflow-x: auto;
        overflow-y: hidden;
        white-space: nowrap;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    
    .nav-tabs::-webkit-scrollbar {
        display: none;
    }
    
    .nav-tabs .nav-item {
        display: inline-block;
        margin-right: 0.25rem;
    }
    
    .nav-tabs .nav-link {
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
        white-space: nowrap;
    }
    
    .nav-tabs .nav-link i {
        font-size: 1rem;
        margin-right: 0.3rem;
    }
}

@media (max-width: 576px) {
    .nav-tabs .nav-link {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
    }
    
    .nav-tabs .nav-link i {
        margin-right: 0.25rem;
    }
    
    .nav-tabs .nav-link .badge {
        font-size: 0.6rem;
        min-width: 16px;
        height: 16px;
    }
}

/* Smooth transitions for tab switching */
.tab-content {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Enhanced container styling to match */
.nav-tabs + .tab-content {
    background: rgba(21, 21, 30, 0.6);
    border-radius: 0 0 12px 12px;
    padding: 2rem;
    border: 1px solid rgba(178, 0, 255, 0.1);
    border-top: none;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}
        
        .activity-card {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            background: var(--card-bg);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .activity-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        
        .activity-card img {
            transition: all 0.5s ease;
            border-radius: 12px 12px 0 0;
            width: 100%;
            height: 280px;
            object-fit: cover;
        }
        
        .activity-card:hover img {
            transform: scale(1.05);
        }
        
        .activity-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            transition: all 0.3s ease;
        }
        
        .activity-card:hover::after {
            height: 6px;
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
            height: 500px;
            object-fit: cover;
            transform: rotate(-3deg);
            border-radius: 12px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.4);
        }
        
        .hero-section {
            position: relative;
            padding: 4rem 0 2rem;
            background: linear-gradient(to right, rgba(21, 21, 30, 0.9), rgba(21, 21, 30, 0.7));
            border-bottom: 1px solid rgba(127, 0, 255, 0.3);
            margin-bottom: 2rem;
        }
        
        /* Custom Modal Styling */
        .modal-content {
            background-color: var(--card-bg);
            border: 1px solid rgba(127, 0, 255, 0.3);
            border-radius: 1rem;
        }
        
        .modal-header, .modal-footer {
            border-color: rgba(127, 0, 255, 0.2);
        }
        
        .modal-title {
            font-family: 'Orbitron', sans-serif;
            color: white;
        }
        
        .form-control, .form-select {
            background-color: rgba(30, 30, 47, 0.5);
            border: 1px solid rgba(127, 0, 255, 0.3);
            color: white;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: rgba(30, 30, 47, 0.8);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(127, 0, 255, 0.25);
            color: white;
        }
        
        .form-control::placeholder {
            color: var(--text-muted);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            box-shadow: 0 4px 10px rgba(127, 0, 255, 0.3);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(127, 0, 255, 0.4);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .game-card {
            background: var(--card-bg);
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .game-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--glow-shadow);
            border-color: rgba(127, 0, 255, 0.5);
        }

        .game-card .card-img-container {
            position: relative;
            overflow: hidden;
            height: 200px;
        }

        .game-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .game-card:hover img {
            transform: scale(1.05);
        }

        .game-card .card-body {
            padding: 1.25rem;
        }

        .game-card .card-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.2rem;
            margin-bottom: 0.75rem;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .game-info {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        .game-info strong {
            color: var(--text-light);
        }

        .platform-badge {
            display: inline-block;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 4px;
            margin-right: 4px;
            margin-bottom: 4px;
            background-color: rgba(127, 0, 255, 0.2);
            border: 1px solid rgba(127, 0, 255, 0.3);
            color: var(--text-light);
        }

        .genre-badge {
            display: inline-block;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 4px;
            margin-right: 4px;
            margin-bottom: 4px;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--text-light);
        }

        .wishlist-icon {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #2196f3;
            color: #fff;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #2196f3;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            z-index: 10;
        }

        .wishlist-icon > i {
            font-size: 1.6rem;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Enhanced Mobile Responsiveness */
        @media (max-width: 992px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem;
                margin-top: 1rem;
                align-items: center;
            }

            .profile-avatar {
                margin-bottom: 1.5rem;
                margin-right: 0 !important;
                align-self: center;
            }

            .profile-info {
                margin-bottom: 1.5rem;
                text-align: center;
                width: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
            }

            .profile-info h2 {
                text-align: center;
            }

            .profile-info .text-muted {
                text-align: center;
            }

            .profile-info .about-text {
                text-align: center;
                max-width: 100%;
            }

            .edit-btn {
                margin-left: 0 !important;
                width: 100%;
                display: flex;
                justify-content: center;
            }

            .edit-btn .btn {
                width: 100%;
                max-width: 300px;
                padding: 0.75rem 1.5rem;
            }

            .nav-tabs {
                margin-top: 1.5rem;
                margin-bottom: 1.5rem;
                justify-content: center;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .nav-tabs .nav-link {
                margin: 0 0.5rem;
                padding: 0.75rem 1rem;
                font-size: 0.95rem;
            }

            .section-title {
                font-size: 1.5rem;
                margin-bottom: 1rem;
                text-align: center;
            }

            .collection-flex {
                gap: 1rem;
            }

            .collection-card {
                height: 250px;
            }

            .status-banner {
                font-size: 1rem;
                padding: 0.6rem 0;
            }

            .game-count {
                font-size: 0.85rem;
                padding: 0.4rem 0.75rem;
            }
        }

        @media (max-width: 768px) {
            .profile-header {
                padding: 1rem;
                align-items: center;
            }

            .profile-avatar {
                align-self: center;
            }

            .profile-avatar img,
            .profile-avatar-initials {
                width: 100px;
                height: 100px;
                line-height: 100px;
                font-size: 1.5rem;
                margin: 0 auto;
                display: block;
            }

            .profile-info {
                text-align: center;
                align-items: center;
            }

            .profile-info h2 {
                font-size: 1.3rem;
                margin-bottom: 0.5rem;
                text-align: center;
            }

            .text-muted {
                font-size: 0.9rem !important;
                text-align: center;
            }

            .about-text {
                font-size: 0.9rem;
                line-height: 1.4;
                text-align: center;
            }

            .edit-btn {
                justify-content: center;
            }

            .edit-btn .btn {
                max-width: 250px;
            }

            .nav-tabs .nav-link {
                padding: 0.6rem 0.8rem;
                font-size: 0.9rem;
                margin: 0 0.25rem;
            }

            .section-title {
                font-size: 1.3rem;
                text-align: center;
            }

            .collection-card {
                height: 220px;
            }

            .status-banner {
                font-size: 0.95rem;
                padding: 0.5rem 0;
            }

            .game-count {
                font-size: 0.8rem;
                padding: 0.35rem 0.5rem;
            }

            /* Activity cards mobile */
            .activity-card img {
                height: 200px;
            }

            /* Modal improvements for mobile */
            .modal-dialog {
                margin: 1rem;
                max-width: calc(100% - 2rem);
            }

            .modal-body {
                padding: 1rem;
            }

            .form-control,
            .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
            }
        }

        @media (max-width: 576px) {
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .profile-header {
                padding: 0.75rem;
                margin-top: 0.5rem;
                align-items: center;
            }

            .profile-avatar {
                align-self: center;
                margin-bottom: 1rem;
            }

            .profile-avatar img,
            .profile-avatar-initials {
                width: 80px;
                height: 80px;
                line-height: 80px;
                font-size: 1.2rem;
                margin: 0 auto;
                display: block;
            }

            .profile-info {
                text-align: center;
                align-items: center;
                margin-bottom: 1rem;
            }

            .profile-info h2 {
                font-size: 1.2rem;
                text-align: center;
            }

            .about-text {
                font-size: 0.85rem;
                text-align: center;
            }

            .edit-btn {
                justify-content: center;
            }

            .edit-btn .btn {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
                max-width: 200px;
            }

            .nav-tabs {
                margin-top: 1rem;
                margin-bottom: 1rem;
                gap: 0.25rem;
            }

            .nav-tabs .nav-link {
                padding: 0.5rem 0.6rem;
                font-size: 0.85rem;
                margin: 0 0.1rem;
            }

            .section-title {
                font-size: 1.2rem;
                margin-bottom: 0.75rem;
                text-align: center;
            }

            .collection-flex {
                gap: 0.75rem;
            }

            .collection-card {
                height: 200px;
                border-radius: 0.5rem;
            }

            .status-banner {
                font-size: 0.9rem;
                padding: 0.4rem 0;
            }

            .game-count {
                font-size: 0.75rem;
                padding: 0.3rem 0.4rem;
            }

            .activity-card img {
                height: 180px;
            }

            /* Stack activity cards on very small screens */
            .col-xl-2,
            .col-lg-3,
            .col-md-4,
            .col-sm-6 {
                width: 100%;
                margin-bottom: 1rem;
            }

            /* Wishlist cards mobile */
            .col-md-4 {
                width: 100%;
                margin-bottom: 1rem;
            }

            .game-card .card-img-container {
                height: 200px;
            }

            .game-card .card-title {
                font-size: 1rem;
                line-height: 1.3;
            }

            .platform-badge,
            .genre-badge {
                font-size: 0.7rem;
                padding: 2px 6px;
                margin: 1px;
            }
        }

        /* Touch improvements */
        @media (hover: none) and (pointer: coarse) {
            .collection-card:hover,
            .activity-card:hover {
                transform: none;
            }

            .collection-card:active,
            .activity-card:active {
                transform: scale(0.98);
                transition: transform 0.1s ease;
            }

            .profile-avatar img:hover,
            .profile-avatar-initials:hover {
                transform: none;
            }

            .profile-avatar img:active,
            .profile-avatar-initials:active {
                transform: scale(0.95);
                transition: transform 0.1s ease;
            }

            .edit-btn .btn:hover {
                transform: none;
            }

            .edit-btn .btn:active {
                transform: scale(0.98);
            }

            .nav-tabs .nav-link:hover {
                transform: none;
            }
        }

        /* Reviews Section Styles */
        .review-card {
            background: var(--card-bg);
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .review-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--glow-shadow);
            border-color: rgba(127, 0, 255, 0.5);
        }

        .review-game-info {
            position: relative;
            height: 200px;
            overflow: hidden;
        }

        .review-game-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .review-card:hover .review-game-image {
            transform: scale(1.05);
        }

        .review-game-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1rem;
            background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
            color: white;
        }

        .review-game-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .review-rating {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.9rem;
        }

        .review-rating i {
            color: var(--primary-color);
        }

        .rating-text {
            margin-left: 0.5rem;
            font-weight: bold;
        }

        .review-content {
            padding: 1.25rem;
        }

        .review-title {
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
            color: white;
        }

        .review-text {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
        }

        .review-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.75rem;
            border-top: 1px solid rgba(127, 0, 255, 0.1);
        }

        .review-date {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .review-link {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .review-link:hover {
            color: var(--primary-hover);
            transform: translateX(5px);
        }

        .btn-review-link {
            background: rgba(127, 0, 255, 0.1);
            border: 1px solid rgba(127, 0, 255, 0.2);
            color: var(--text-light);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.3s ease;
        }

        .btn-review-link:hover {
            background: rgba(127, 0, 255, 0.2);
            border-color: var(--primary-color);
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 4px 12px rgba(127, 0, 255, 0.2);
        }

        .btn-review-link i {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .btn-review-link:hover i {
            transform: translateX(3px);
        }

        @media (max-width: 768px) {
            .review-card {
                margin-bottom: 1rem;
            }

            .review-game-info {
                height: 180px;
            }

            .review-game-title {
                font-size: 1rem;
            }

            .review-content {
                padding: 1rem;
            }

            .review-text {
                -webkit-line-clamp: 2;
            }
        }

        /* New styles for ratings distribution */
        .ratings-overview-card {
            background: var(--card-bg);
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            margin-top: 0; /* Remove the top margin to align with reviews */
        }

        .ratings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(127, 0, 255, 0.1);
        }

        .total-reviews {
            text-align: left;
        }

        .review-count {
            font-family: 'Orbitron', sans-serif;
            font-size: 2rem;
            color: var(--primary-color);
            font-weight: bold;
        }

        .review-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-left: 0.5rem;
        }

        .average-rating {
            text-align: right;
        }

        .big-rating {
            font-family: 'Orbitron', sans-serif;
            font-size: 3rem;
            color: var(--primary-color);
            font-weight: bold;
        }

        .rating-stars {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 1.2rem;
            color: var(--primary-color);
        }

        .rating-bars {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .rating-bar-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .rating-label {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .rating-bar-container {
            flex-grow: 1;
            height: 10px;
            background-color: rgba(127, 0, 255, 0.2);
            border-radius: 5px;
            overflow: hidden;
            margin: 0 10px;
        }

        .rating-bar {
            height: 100%;
            background: var(--primary-color);
            border-radius: 5px;
            transition: width 0.3s ease-in-out;
        }

        .rating-count {
            font-size: 0.8rem;
            font-weight: bold;
            color: var(--text-light);
        }

        /* Add these new styles */
        .btn-view-all {
            background: rgba(127, 0, 255, 0.1);
            border: 1px solid rgba(127, 0, 255, 0.2);
            color: var(--text-light);
            text-decoration: none;
            padding: 0.5rem 1.2rem;
            border-radius: 6px;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-view-all:hover {
            background: rgba(127, 0, 255, 0.2);
            border-color: var(--primary-color);
            transform: translateX(5px);
            color: white;
            box-shadow: 0 4px 12px rgba(127, 0, 255, 0.2);
        }

        .btn-view-all i {
            transition: transform 0.3s ease;
        }

        .btn-view-all:hover i {
            transform: translateX(3px);
        }

        /* Update ratings card styles */
        .ratings-overview-card {
            margin-top: 0; /* Remove the top margin to align with reviews */
        }

        /* Make sure the section title doesn't have a bottom margin */
        .section-title {
            margin-bottom: 0;
        }

        /* Update the row gap */
        .row.g-4 {
            --bs-gutter-y: 1.5rem;
        }
        .btn-steam {
            background: transparent;
            border: 1px solid #2a475e;
            color: white;
            padding: 0.5rem 1.2rem;
            border-radius: 6px;
            font-family: 'Exo 2', sans-serif;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-steam:not(:disabled):hover {
            background: rgba(42, 71, 94, 0.2);
            border-color: #66c0f4;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(42, 71, 94, 0.3);
            color: white;
        }

        .btn-steam:disabled {
            opacity: 0.8;
            cursor: default;
            background: transparent;
            border: 1px solid rgba(42, 71, 94, 0.5);
        }

        .achievement-card {
            background: var(--card-bg);
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .achievement-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary-color);
            box-shadow: var(--glow-shadow);
        }

        .achievement-image {
            position: relative;
            width: 100%;
            padding-top: 56.25%; /* 16:9 aspect ratio */
            overflow: hidden;
        }

        .achievement-image img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .achievement-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .achievement-card:hover .achievement-overlay {
            opacity: 1;
        }

        .achievement-progress {
            text-align: center;
        }

        .progress-ring {
            width: 80px;
            height: 80px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            padding: 10px;
            box-shadow: 0 0 20px rgba(127, 0, 255, 0.3);
        }

        .progress-circle {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: conic-gradient(var(--primary-color) var(--progress), rgba(127, 0, 255, 0.1) var(--progress));
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .progress-circle::before {
            content: '';
            position: absolute;
            width: 80%;
            height: 80%;
            background: rgba(0, 0, 0, 0.8);
            border-radius: 50%;
            box-shadow: inset 0 0 10px rgba(127, 0, 255, 0.2);
        }

        .progress-circle span {
            position: relative;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            font-family: 'Orbitron', sans-serif;
            text-shadow: 0 0 10px rgba(127, 0, 255, 0.5);
        }

        .achievement-info {
            padding: 1.2rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            background: linear-gradient(to bottom, rgba(30, 30, 47, 0.95), rgba(21, 21, 30, 0.9));
        }

        .achievement-info h4 {
            font-size: 1.1rem;
            font-family: 'Orbitron', sans-serif;
            margin-bottom: 0.5rem;
            color: white;
            line-height: 1.4;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .achievement-info p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            flex-grow: 1;
        }

        .achievement-info .btn {
            align-self: flex-start;
            padding: 0.4rem 1rem;
            font-size: 0.9rem;
            background: rgba(127, 0, 255, 0.1);
            border: 1px solid rgba(127, 0, 255, 0.2);
            color: white;
            transition: all 0.3s ease;
        }

        .achievement-info .btn:hover {
            background: rgba(127, 0, 255, 0.2);
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(127, 0, 255, 0.2);
        }

        @media (max-width: 768px) {
            .achievement-image {
                padding-top: 75%; /* 4:3 aspect ratio for mobile */
            }
            
            .progress-ring {
                width: 60px;
                height: 60px;
            }
            
            .progress-circle span {
                font-size: 1rem;
            }
            
            .achievement-info {
                padding: 1rem;
            }
            
            .achievement-info h4 {
                font-size: 1rem;
            }
        }

        /* Five-column grid */
        .col-lg-2-4 {
            flex: 0 0 20%;
            max-width: 20%;
            position: relative;
            width: 100%;
            padding-right: 15px;
            padding-left: 15px;
        }

        @media (max-width: 1200px) {
            .col-lg-2-4 {
                flex: 0 0 25%;
                max-width: 25%;
            }
        }

        @media (max-width: 992px) {
            .col-lg-2-4 {
                flex: 0 0 33.333333%;
                max-width: 33.333333%;
            }
        }

        @media (max-width: 768px) {
            .col-lg-2-4 {
                flex: 0 0 50%;
                max-width: 50%;
            }
        }

        /* Adjust card styles for smaller columns */
        .achievement-card {
            margin-bottom: 1.5rem;
        }

        .achievement-image {
            padding-top: 75%;
        }

        .achievement-info {
            padding: 1rem;
        }

        .achievement-info h4 {
            font-size: 0.95rem;
            -webkit-line-clamp: 1;
        }

        .progress-ring {
            width: 60px;
            height: 60px;
        }

        .progress-circle span {
            font-size: 0.9rem;
        }

        .achievement-info .btn {
            padding: 0.35rem 0.8rem;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
<?php include '../includes/nav.php'; ?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-image-stack">
        <?php foreach ($heroImages as $img): ?>
            <img src="<?= htmlspecialchars($img) ?>" alt="Game Background">
        <?php endforeach; ?>
    </div>
    <div class="container position-relative" style="z-index: 1;">
        <div class="row">
            <div class="col-lg-8">
                <h1 class="display-5 mb-0" style="font-family: 'Orbitron', sans-serif;">
                    <span style="color: var(--primary-color);">Gamer</span> Profile
                </h1>
                <p class="lead mt-2 mb-0">Track your progress, showcase your collection, and share your gaming journey.</p>
            </div>
        </div>
    </div>
</section>

<div class="container">
    <!-- Profile Header -->
    <div class="profile-header d-flex align-items-start">
        <div class="profile-avatar me-4">
            <?php if (!empty($user['profile_image'])): ?>
                <img src="<?= strpos($user['profile_image'], 'uploads/profiles/') === 0 ? htmlspecialchars($user['profile_image']) : 'uploads/profiles/' . htmlspecialchars($user['profile_image']) ?>" alt="Profile Image"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="profile-avatar-initials" style="display:none"><?= $initials ?></div>
            <?php else: ?>
                <div class="profile-avatar-initials"><?= $initials ?></div>
            <?php endif; ?>
        </div>

        <div class="profile-info">
            <h2 class="mb-0">
                <?= !empty($user['name']) ? htmlspecialchars($user['name']) : htmlspecialchars($username) ?>
            </h2>
            <p class="text-light">@<?= htmlspecialchars($username) ?></p>
            <p class="about-text"><?= nl2br(htmlspecialchars($user['about'] ?? "I love video games, storytelling, and discovery.")) ?></p>
            
            <?php
            // Get user's own counts
            $followerQuery = $conn->prepare("SELECT COUNT(*) as count FROM user_relationships WHERE following_id = ? AND status = 'following'");
            $followerQuery->bind_param("i", $_SESSION['user_id']);
            $followerQuery->execute();
            $followerCount = $followerQuery->get_result()->fetch_assoc()['count'];
            
            $followingQuery = $conn->prepare("SELECT COUNT(*) as count FROM user_relationships WHERE follower_id = ? AND status = 'following'");
            $followingQuery->bind_param("i", $_SESSION['user_id']);
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
            $friendQuery->bind_param("iii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']);
            $friendQuery->execute();
            $friendCount = $friendQuery->get_result()->fetch_assoc()['count'];
            ?>
            
            <div class="d-flex gap-4 mt-3">
                <div class="d-flex align-items-center">
                    <i class="bi bi-person-plus me-2 text-primary"></i>
                    <span class="text-light"><?= $followerCount ?> followers</span>
                </div>
                <div class="d-flex align-items-center">
                    <i class="bi bi-person-check me-2 text-primary"></i>
                    <span class="text-light"><?= $followingCount ?> following</span>
                </div>
                <div class="d-flex align-items-center">
                    <i class="bi bi-people me-2 text-primary"></i>
                    <span class="text-light"><?= $friendCount ?> friends</span>
                </div>
            </div>
        </div>
        
        <div class="edit-btn ms-auto">
            <a href="#" class="btn btn-outline-light" id="editProfileBtn">
                <i class="bi bi-pencil-square me-2"></i>Edit Profile
            </a>
        </div>
        <?php if (!empty($steamId)): ?>
            <div class="steam-info ms-3">
                <div class="btn-group">
                    <button class="btn btn-steam" disabled>
                        <i class="bi bi-steam me-2"></i>Steam Connected
                    </button>
                    <button class="btn btn-steam" onclick="disconnectSteam()" title="Disconnect Steam">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="steam-info ms-3">
                <a href="steam_connect.php" class="btn btn-steam">
                    <i class="bi bi-steam me-2"></i>Connect Steam
                </a>
            </div>
        <?php endif; ?>

        <script>
        function disconnectSteam() {
            showConfirm('Disconnect your Steam account? This will remove all achievement data.', function() {
                fetch('steam_disconnect.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            showToast('Failed to disconnect Steam: ' + data.error, 'error');
                        }
                    })
                    .catch(() => showToast('Failed to disconnect Steam', 'error'));
            });
        }
        </script>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a class="nav-link active" href="#" data-tab="profile">Profile</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#" data-tab="wishlist">Wishlist</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#" data-tab="friends">
                <i class="bi bi-person-check me-1"></i>Friends
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#" data-tab="followers">
                <i class="bi bi-person-plus me-1"></i>Followers
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#" data-tab="friend-requests">
                <i class="bi bi-people me-1"></i>Friend Requests
                <span class="badge bg-danger ms-1" id="friendRequestBadge" style="display: none;">0</span>
            </a>
        </li>
        <?php if (!empty($steamId)): ?>
        <li class="nav-item">
            <a class="nav-link" href="#" data-tab="achievements">
                <i class="bi bi-trophy me-1"></i>Achievements
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <!-- Tab Content -->
    <div id="profile-content" class="tab-content" style="display: block;">
        <!-- Collections Section -->
        <div class="my-5">
            <h3 class="section-title mb-4">Your Collections</h3>
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
                $user_id = $_SESSION['user_id'];
                foreach ($collection_statuses as $status => $banner_color) {
                    // Fetch a random game for this status
                    $sql = "SELECT g.* FROM user_game_status ugs JOIN games g ON ugs.game_id = g.id WHERE ugs.user_id = ? AND ugs.status = ? ORDER BY RAND() LIMIT 1";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('is', $user_id, $status);
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
                    $count_stmt->bind_param('is', $user_id, $status);
                    $count_stmt->execute();
                    $count_stmt->bind_result($game_count);
                    $count_stmt->fetch();
                    $count_stmt->close();
                    $slug = strtolower(str_replace(' ', '-', $status));
                ?>
                <a href="../games/collections/<?= $slug ?>.php" class="text-decoration-none">
                    <div class="collection-card" style="background: <?= strpos($banner_color, 'gradient') !== false ? $banner_color : 'transparent' ?>;">
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
        </div>

        <!-- Activity Section -->
        <?php
        $activityQuery = $conn->prepare("
        SELECT games.id, games.title, games.image_url, games.portrait_image_url, user_game_status.status 
        FROM user_game_status 
        JOIN games ON user_game_status.game_id = games.id 
        WHERE user_game_status.user_id = ? 
        ORDER BY user_game_status.updated_at DESC 
        LIMIT 12
        ");
        $activityQuery->bind_param("i", $userId);
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

        <div class="mt-5 mb-5">
            <h3 class="section-title mb-4">Recent Activity</h3>
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
                    <p class="text-muted">Start adding games to your collections to see your activity here.</p>
                    <a href="explore.php" class="btn btn-primary mt-2">Discover Games</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Reviews Section -->
        <?php
        // Get rating distribution - fixed query to properly count all ratings
        $ratingDistQuery = $conn->prepare("
            SELECT 
                rating, 
                COUNT(*) as count 
            FROM reviews 
            WHERE user_id = ? 
            GROUP BY rating 
            ORDER BY rating DESC
        ");
        $ratingDistQuery->bind_param("i", $userId);
        $ratingDistQuery->execute();
        $ratingDistResult = $ratingDistQuery->get_result();
        
        // Initialize counts for all ratings (1-5)
        $ratingDist = array_fill(1, 5, 0); // Initialize all ratings to 0
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
        $reviewsQuery->bind_param("i", $userId);
        $reviewsQuery->execute();
        $reviewsResult = $reviewsQuery->get_result();
        ?>

        <div class="mt-5 mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="section-title mb-0">Your Reviews</h3>
                <a href="all-reviews.php" class="btn-view-all">
                    View all <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            
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
                                                            <i class="bi bi-star-fill"></i>
                                                        <?php elseif ($i - 0.5 <= $review['rating']): ?>
                                                            <i class="bi bi-star-half"></i>
                                                        <?php else: ?>
                                                            <i class="bi bi-star"></i>
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
                            <p class="text-muted">Share your thoughts about the games you've played.</p>
                            <a href="../explore.php" class="btn btn-primary mt-2">Write a Review</a>
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
                                            <i class="bi bi-star-fill"></i>
                                        <?php elseif ($i - 0.5 <= $avgRating): ?>
                                            <i class="bi bi-star-half"></i>
                                        <?php else: ?>
                                            <i class="bi bi-star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="rating-bars">
                            <?php for($i = 5; $i >= 1; $i--): ?>
                                <div class="rating-bar-row">
                                    <div class="rating-label"><?= $i ?> <i class="bi bi-star-fill"></i></div>
                                    <div class="rating-bar-container">
                                        <div class="rating-bar" style="width: <?= $totalReviews > 0 ? ($ratingDist[$i] / $totalReviews) * 100 : 0 ?>%"></div>
                                    </div>
                                    <div class="rating-count"><?= $ratingDist[$i] ?></div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
        <?php if (!empty($steamId)): ?>
            <!-- Achievement Stats -->
            <?php
            // Get total achievements stats
            $achievementsStatsQuery = $conn->prepare("
                SELECT 
                    SUM(unlocked_achievements) as total_unlocked,
                    SUM(total_achievements) as total_available,
                    COUNT(DISTINCT game_id) as games_with_achievements
                FROM steam_achievement_stats 
                WHERE user_id = ? AND total_achievements > 0
            ");
            $achievementsStatsQuery->bind_param("i", $userId);
            $achievementsStatsQuery->execute();
            $achievementsStats = $achievementsStatsQuery->get_result()->fetch_assoc();

            if ($achievementsStats['total_available'] > 0):
                $completionPercentage = round(($achievementsStats['total_unlocked'] / $achievementsStats['total_available']) * 100, 1);
            ?>
            <div class="row mt-3">
                <div class="col-lg-12">
                    <div class="ratings-overview-card" style="width: 100%;">
                        <div class="d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-4">
                                <div>
                                    <div style="color: #b200ff; font-size: 2rem; font-weight: bold;"><?= number_format($achievementsStats['total_unlocked']) ?></div>
                                    <div class="text-muted" style="font-size: 0.9rem;">Achievements unlocked</div>
                                </div>
                                <div class="text-end">
                                    <div style="color: #b200ff; font-size: 2rem; font-weight: bold;"><?= $completionPercentage ?>%</div>
                                    <div class="text-muted" style="font-size: 0.9rem;">Completion</div>
                                </div>
                            </div>
                            <div class="achievement-stats-info">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="text-muted">Total Achievements</div>
                                    <div class="text-light"><?= number_format($achievementsStats['total_available']) ?></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="text-muted">Games with Achievements</div>
                                    <div class="text-light"><?= number_format($achievementsStats['games_with_achievements']) ?></div>
                                </div>
                                <div class="progress achievement-progress" style="height: 10px; background: rgba(127, 0, 255, 0.1); border-radius: 4px;">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?= $completionPercentage ?>%; background: #b200ff; border-radius: 4px;" 
                                         aria-valuenow="<?= $completionPercentage ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="#achievements" class="btn-view-all" onclick="showTab('achievements')" style="display: inline-flex; align-items: center; gap: 0.5rem; color: #fff; text-decoration: none; background: rgba(255, 255, 255, 0.1); padding: 0.5rem 1rem; border-radius: 4px; font-size: 0.9rem;">
                                        View Details <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Reviews Section End -->



        </div><!-- profile-content end -->

    <!-- Achievements Tab Content -->
    <div id="achievements-content" class="tab-content" style="display: none;">
        <?php if (!empty($steamId)): ?>
            <div class="my-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="section-title mb-0">Your Achievement Progress</h3>
                    <button class="btn btn-outline-light" onclick="refreshAchievements()">
                        <i class="bi bi-arrow-clockwise me-2"></i>Refresh Achievements
                    </button>
                </div>
                <div class="row g-4">
                    <?php
                    // Fetch games with Steam achievements
                    $achievementsQuery = $conn->prepare("
                        SELECT g.id, g.title, g.image_url, g.steam_app_id,
                               sas.total_achievements, sas.unlocked_achievements, sas.completion_percentage
                        FROM games g
                        JOIN steam_achievement_stats sas ON g.id = sas.game_id
                        WHERE sas.user_id = ? AND sas.total_achievements > 0
                        ORDER BY sas.completion_percentage DESC
                    ");
                    $achievementsQuery->bind_param("i", $userId);
                    $achievementsQuery->execute();
                    $achievementsResult = $achievementsQuery->get_result();
                    ?>
                    
                    <?php while ($game = $achievementsResult->fetch_assoc()): ?>
                        <div class="col-lg-2-4 col-md-3 col-sm-6">
                            <div class="achievement-card">
                                <div class="achievement-image">
                                    <img src="<?= htmlspecialchars($game['image_url']) ?>" alt="<?= htmlspecialchars($game['title']) ?>">
                                    <div class="achievement-overlay">
                                        <div class="achievement-progress">
                                            <div class="progress-ring">
                                                <div class="progress-circle" style="--progress: <?= $game['completion_percentage'] ?>%">
                                                    <span><?= round($game['completion_percentage']) ?>%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="achievement-info">
                                    <h4><?= htmlspecialchars($game['title']) ?></h4>
                                    <p><?= $game['unlocked_achievements'] ?> / <?= $game['total_achievements'] ?> achievements</p>
                                    <a href="../games/achievements.php?id=<?= $game['id'] ?>" class="btn btn-sm btn-outline-light">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center my-5">
                <h3>Connect Steam to View Achievements</h3>
                <p class="text-muted mb-4">Link your Steam account to track your achievements across games.</p>
                <a href="steam_connect.php" class="btn btn-steam">
                    <i class="bi bi-steam me-2"></i>Connect Steam Account
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Wishlist Content -->
    <div id="wishlist-content" class="tab-content" style="display: none;">
        <div class="my-5">
            <h3 class="section-title mb-4">Your Wishlist</h3>
            <?php
            // Fetch user's wishlist
            $wishlistQuery = $conn->prepare("
                SELECT g.* 
                FROM games g 
                INNER JOIN user_wishlist w ON g.id = w.game_id 
                WHERE w.user_id = ? 
                ORDER BY w.added_at DESC
            ");
            $wishlistQuery->bind_param("i", $userId);
            $wishlistQuery->execute();
            $wishlistResult = $wishlistQuery->get_result();
            ?>
            
            <?php if ($wishlistResult->num_rows > 0): ?>
                <div class="row g-4">
                    <?php while($game = $wishlistResult->fetch_assoc()): ?>
                        <div class="col-md-4">
                            <a href="../games/game-detail.php?id=<?= $game['id'] ?>" class="text-decoration-none">
                                <div class="game-card">
                                    <div class="card-img-container">
                                        <?php if (!empty($game['image_url'])): ?>
                                            <img src="<?= htmlspecialchars($game['image_url']) ?>" alt="<?= htmlspecialchars($game['title']) ?>">
                                        <?php else: ?>
                                            <img src="../assets/default-game.jpg" alt="Default game image">
                                        <?php endif; ?>
                                        <button class="wishlist-icon active" data-game-id="<?= $game['id'] ?>" title="Remove from Wishlist">
                                            <i class="bi bi-cart"></i>
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($game['title']) ?></h5>
                                        <div class="game-info">
                                            <?php if (!empty($game['platforms'])): ?>
                                                <div class="mb-2">
                                                    <?php foreach(explode(',', $game['platforms']) as $platform): ?>
                                                        <span class="platform-badge"><?= trim(htmlspecialchars($platform)) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($game['genre'])): ?>
                                                <div>
                                                    <?php foreach(explode(',', $game['genre']) as $genre): ?>
                                                        <span class="genre-badge"><?= trim(htmlspecialchars($genre)) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center p-5">
                    <i class="ph ph-shopping-cart" style="font-size: 3rem; color: var(--primary-color);"></i>
                    <h4 class="mt-3">Your wishlist is empty</h4>
                    <p class="text-muted">Add games to your wishlist to keep track of titles you're interested in.</p>
                    <a href="../explore.php" class="btn btn-primary mt-2">Discover Games</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Friends Content -->
    <div id="friends-content" class="tab-content" style="display: none;">
        <div class="my-5">
            <h3 class="section-title mb-4">Your Friends</h3>
            <div id="friendsContainer">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Followers Content -->
    <div id="followers-content" class="tab-content" style="display: none;">
        <div class="my-5">
            <h3 class="section-title mb-4">Your Followers</h3>
            <div id="followersContainer">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Friend Requests Content -->
    <div id="friend-requests-content" class="tab-content" style="display: none;">
        <div class="my-5">
            <h3 class="section-title mb-4">Friend Requests</h3>
            <div id="friendRequestsContainer">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editProfileForm" method="POST" action="update-profile.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <!-- Image Preview --> 
                    <div class="mb-3 text-center">
                        <?php if (!empty($user['profile_image'])): ?>
                            <img src="<?= strpos($user['profile_image'], 'uploads/profiles/') === 0 ? htmlspecialchars($user['profile_image']) : 'uploads/profiles/' . htmlspecialchars($user['profile_image']) ?>" alt="Current Image" class="rounded-circle shadow" style="width: 100px; height: 100px; object-fit: cover; border: 3px solid rgba(127, 0, 255, 0.3);">
                        <?php else: ?>
                            <div class="profile-avatar-initials mx-auto" style="width: 100px; height: 100px; line-height: 100px; font-size: 1.5rem;"><?= $initials ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Upload Field -->
                    <div class="mb-3">
                        <label for="profile_image" class="form-label">Change Profile Image</label>
                        <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*">
                    </div>

                    <!-- Name -->
                    <div class="mb-3">
                        <label for="name" class="form-label">Display Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" placeholder="e.g. Master Chief">
                    </div>

                    <!-- About -->
                    <div class="mb-3">
                        <label for="about" class="form-label">About Me</label>
                        <textarea class="form-control" id="about" name="about" rows="3" maxlength="300" placeholder="Tell others about yourself and your gaming interests!"><?= htmlspecialchars(str_replace(['\r\n', '\r', '\n'], ["\n", "\n", "\n"], $user['about'] ?? '')) ?></textarea>
                        <div class="d-flex justify-content-between align-items-start mt-1">
                            <div class="form-text">Tell others about yourself and your gaming interests!</div>
                            <div class="char-counter ms-2" style="font-size: 0.85rem; white-space: nowrap; transition: color 0.3s ease; color: #2ecc71;">
                                <span id="charCount">300</span> characters remaining
                            </div>
                        </div>
                    </div>

                    <!-- Privacy Settings -->
                    <div class="mb-3">
                        <h6 class="mb-3">Privacy Settings</h6>
                        
                        <!-- Profile Visibility -->
                        <div class="mb-3">
                            <label for="profile_visibility" class="form-label">Profile Visibility</label>
                            <select class="form-select" id="profile_visibility" name="profile_visibility">
                                <option value="public" <?= ($user['profile_visibility'] ?? 'public') === 'public' ? 'selected' : '' ?>>Public - Anyone can view</option>
                                <option value="friends" <?= ($user['profile_visibility'] ?? '') === 'friends' ? 'selected' : '' ?>>Friends Only</option>
                                <option value="private" <?= ($user['profile_visibility'] ?? '') === 'private' ? 'selected' : '' ?>>Private - Only you</option>
                            </select>
                        </div>

                        <!-- Section Visibility -->
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="show_achievements" name="show_achievements" <?= ($user['show_achievements'] ?? true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_achievements">
                                Show Achievements
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="show_reviews" name="show_reviews" <?= ($user['show_reviews'] ?? true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_reviews">
                                Show Reviews
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="show_collections" name="show_collections" <?= ($user['show_collections'] ?? true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_collections">
                                Show Game Collections
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="show_activity" name="show_activity" <?= ($user['show_activity'] ?? true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_activity">
                                Show Recent Activity
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap components
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Edit Profile Modal
    const editProfileBtn = document.getElementById('editProfileBtn');
    if (editProfileBtn) {
        editProfileBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const editProfileModal = new bootstrap.Modal(document.getElementById('editProfileModal'));
            editProfileModal.show();
        });
    }
    
    // Profile Image Preview
    const profileImageInput = document.getElementById('profile_image');
    if (profileImageInput) {
        profileImageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewContainer = document.querySelector('.modal-body .text-center');
                    let previewImg = previewContainer.querySelector('img');
                    
                    if (previewImg) {
                        previewImg.src = e.target.result;
                    } else {
                        const initialsDiv = previewContainer.querySelector('.profile-avatar-initials');
                        if (initialsDiv) {
                            previewContainer.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" class="rounded-circle shadow" style="width: 100px; height: 100px; object-fit: cover; border: 3px solid rgba(127, 0, 255, 0.3);">`;
                        }
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Character counter for About Me textarea with color changes
    const aboutTextarea = document.getElementById('about');
    const charCountSpan = document.getElementById('charCount');
    if (aboutTextarea && charCountSpan) {
        const maxLength = 300;
        
        // Function to update counter and color
        function updateCharCounter() {
            const currentLength = aboutTextarea.value.length;
            const remaining = maxLength - currentLength;
            const counterDiv = charCountSpan.parentElement;
            
            // Update the count
            charCountSpan.textContent = remaining;
            
            // Update color based on remaining characters
            if (remaining > 200) {
                counterDiv.style.color = '#2ecc71'; // Green
            } else if (remaining > 50) {
                counterDiv.style.color = '#f39c12'; // Orange
            } else {
                counterDiv.style.color = '#e74c3c'; // Red
            }
        }
        
        // Initialize counter
        updateCharCounter();
        
        // Update counter on input
        aboutTextarea.addEventListener('input', updateCharCounter);
    }
    
    // Profile Form Submission
    const editProfileForm = document.getElementById('editProfileForm');
    if (editProfileForm) {
        editProfileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Add checkbox values explicitly
            formData.set('show_achievements', document.getElementById('show_achievements').checked ? '1' : '0');
            formData.set('show_reviews', document.getElementById('show_reviews').checked ? '1' : '0');
            formData.set('show_collections', document.getElementById('show_collections').checked ? '1' : '0');
            formData.set('show_activity', document.getElementById('show_activity').checked ? '1' : '0');
            
            console.log('Sending profile update request...');
            fetch('update-profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Always update profile image in header based on backend response
                    const avatarContainer = document.querySelector('.profile-avatar');
                    if (data.profile_image) {
                    let imgPath = data.profile_image;
                    if (imgPath && !imgPath.startsWith('/')) {
                        imgPath = '/' + imgPath;
                    }
                    avatarContainer.innerHTML = `<img src="${imgPath}?t=${new Date().getTime()}" alt="Profile Image" class="profile-avatar-img">`;
                    }
                    
                    // Update name if changed
                    if (data.name) {
                        const nameEl = document.querySelector('.profile-info h2');
                        if (nameEl) {
                            nameEl.textContent = data.name;
                        }
                    }
                    
                    // Update about if changed
                    if (data.about) {
                        const aboutEl = document.querySelector('.about-text');
                        if (aboutEl) {
                            aboutEl.innerHTML = data.about.replace(/\n/g, '<br>');
                        }
                    }
                    
                    // Show success message
                    const toastElement = document.createElement('div');
                    toastElement.className = 'toast align-items-center text-white bg-success border-0 position-fixed bottom-0 end-0 m-3';
                    toastElement.setAttribute('role', 'alert');
                    toastElement.setAttribute('aria-live', 'assertive');
                    toastElement.setAttribute('aria-atomic', 'true');
                    toastElement.innerHTML = `
                        <div class="d-flex">
                            <div class="toast-body">
                                Profile updated successfully!
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    `;
                    document.body.appendChild(toastElement);
                    const toast = new bootstrap.Toast(toastElement);
                    toast.show();
                    setTimeout(() => toastElement.remove(), 3000);
                    
                    // Close modal
                    const modalElem = document.getElementById('editProfileModal');
                    const modalInstance = bootstrap.Modal.getInstance(modalElem);
                    modalInstance.hide();
                } else {
                    showToast(data.message || 'Failed to update profile.', 'error');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showToast('An error occurred while updating your profile.', 'error');
            });
        });
    }
    
    //collection display
    window.addEventListener('load', function() {
        const collectionLinks = document.querySelectorAll('.collection-flex a');
        collectionLinks.forEach(link => {
            link.addEventListener('mouseenter', function() {
                const card = this.querySelector('.collection-card');
                if (card) {
                    card.style.transform = 'translateY(-5px)';
                    card.style.boxShadow = '0 12px 30px rgba(0, 0, 0, 0.3), 0 0 20px rgba(127, 0, 255, 0.3)';
                }
            });
            
            link.addEventListener('mouseleave', function() {
                const card = this.querySelector('.collection-card');
                if (card) {
                    card.style.transform = '';
                    card.style.boxShadow = '';
                }
            });
        });
    });

    // Tab switching logic
    const tabs = document.querySelectorAll('.nav-link[data-tab]');
    const tabContents = document.querySelectorAll('.tab-content');

    // Function to get tab from URL hash or return default
    function getActiveTabFromHash() {
        const hash = window.location.hash.substring(1);
        const validTabs = ['profile', 'wishlist', 'friends', 'followers', 'friend-requests', 'achievements'];
        return validTabs.includes(hash) ? hash : 'profile';
    }

    function showTab(tabId) {
        // Hide all tab contents
        tabContents.forEach(content => {
            content.style.display = 'none';
        });
        
        // Remove active from all tabs
        tabs.forEach(tab => tab.classList.remove('active'));
        
        // Show the selected tab content and set active on tab
        const tab = document.querySelector('.nav-link[data-tab="' + tabId + '"]');
        const content = document.getElementById(tabId + '-content');
        
        if (tab) tab.classList.add('active');
        if (content) content.style.display = 'block';
        
        // Update URL hash without scrolling
        const scrollPos = window.scrollY;
        window.location.hash = tabId;
        window.scrollTo(0, scrollPos);
    }

    // Attach click event to tabs
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const tabId = this.getAttribute('data-tab');
            showTab(tabId);
        });
    });

    // On page load, show the active tab from URL or default to profile
    window.addEventListener('load', function() {
        showTab(getActiveTabFromHash());
    });

    // Handle browser back/forward
    window.addEventListener('hashchange', function() {
        showTab(getActiveTabFromHash());
    });

    // Wishlist icon click handler (AJAX remove)
    document.querySelectorAll('.wishlist-icon').forEach(icon => {
        icon.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const gameId = this.getAttribute('data-game-id');
            const isActive = this.classList.contains('active');
            fetch('../includes/toggle-wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'game_id=' + gameId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (isActive) {
                        this.closest('.col-md-4').remove();
                        // If no more games in wishlist, show empty state
                        const wishlistContent = document.getElementById('wishlist-content');
                        if (wishlistContent.querySelectorAll('.game-card').length === 0) {
                            wishlistContent.innerHTML = `
                                <div class="text-center p-5 bg-dark rounded">
                                    <i class="bi bi-cart" style="font-size: 3rem; color: var(--primary-color);"></i>
                                    <h4 class="mt-3">Your wishlist is empty</h4>
                                    <p class="text-muted">Add games to your wishlist to keep track of titles you're interested in.</p>
                                    <a href="../explore.php" class="btn btn-primary mt-2">Discover Games</a>
                                </div>
                            `;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    });

    // Friend Requests functionality
    loadFriendRequests();
    
    // Load friend requests when friend-requests tab is shown
    document.querySelector('a[data-tab="friend-requests"]').addEventListener('click', function() {
        loadFriendRequests();
    });
    
    // Load friends when friends tab is shown
    document.querySelector('a[data-tab="friends"]').addEventListener('click', function() {
        loadFriends();
    });
    
    // Load followers when followers tab is shown
    document.querySelector('a[data-tab="followers"]').addEventListener('click', function() {
        loadFollowers();
    });
});

function loadFriendRequests() {
    const container = document.getElementById('friendRequestsContainer');
    const badge = document.getElementById('friendRequestBadge');
    
    fetch('../api/get-friend-requests.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateFriendRequestBadge(data.requests.length);
                updateFriendRequestsList(data.requests, container);
            } else {
                container.innerHTML = `
                    <div class="text-center p-5">
                        <i class="bi bi-exclamation-circle text-danger" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Error Loading Friend Requests</h4>
                        <p class="text-muted">${data.message || 'An error occurred while loading friend requests.'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading friend requests:', error);
            container.innerHTML = `
                <div class="text-center p-5">
                    <i class="bi bi-exclamation-circle text-danger" style="font-size: 3rem;"></i>
                    <h4 class="mt-3">Error Loading Friend Requests</h4>
                    <p class="text-muted">An error occurred while loading friend requests.</p>
                </div>
            `;
        });
}

function updateFriendRequestBadge(count) {
    const badge = document.getElementById('friendRequestBadge');
    if (count > 0) {
        badge.textContent = count;
        badge.style.display = 'inline';
    } else {
        badge.style.display = 'none';
    }
}

function updateFriendRequestsList(requests, container) {
    if (requests.length === 0) {
        container.innerHTML = `
            <div class="text-center p-5">
                <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                <h4 class="mt-3">No Friend Requests</h4>
                <p class="text-muted">You don't have any pending friend requests.</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = requests.map(request => `
        <div class="card mb-3" style="background-color: #1e1e2f; border: 1px solid rgba(127, 0, 255, 0.2);">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        ${request.profile_image 
                            ? `<img src="../uploads/profiles/${request.profile_image}" class="rounded-circle" style="width: 48px; height: 48px; object-fit: cover;">`
                            : `<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-weight: bold;">
                                ${request.username.charAt(0).toUpperCase()}
                               </div>`
                        }
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${request.name || request.username}</h6>
                        <p class="text-muted mb-2">@${request.username}</p>
                        <div class="btn-group">
                            <button class="btn btn-success btn-sm" onclick="respondToFriendRequest(${request.id}, 'accept')">
                                <i class="bi bi-check me-1"></i>Accept
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="respondToFriendRequest(${request.id}, 'decline')">
                                <i class="bi bi-x me-1"></i>Decline
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function loadFriends() {
    const container = document.getElementById('friendsContainer');
    
    fetch('../api/get-friends.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateFriendsList(data.friends, container);
            } else {
                container.innerHTML = `
                    <div class="text-center p-5">
                        <i class="bi bi-exclamation-circle text-danger" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Error Loading Friends</h4>
                        <p class="text-muted">${data.message || 'An error occurred while loading friends.'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading friends:', error);
            container.innerHTML = `
                <div class="text-center p-5">
                    <i class="bi bi-exclamation-circle text-danger" style="font-size: 3rem;"></i>
                    <h4 class="mt-3">Error Loading Friends</h4>
                    <p class="text-muted">An error occurred while loading friends.</p>
                </div>
            `;
        });
}

function updateFriendsList(friends, container) {
    if (friends.length === 0) {
        container.innerHTML = `
            <div class="text-center p-5">
                <i class="bi bi-person-check text-muted" style="font-size: 3rem;"></i>
                <h4 class="mt-3">No Friends Yet</h4>
                <p class="text-muted">You haven't added any friends yet. Start by sending friend requests!</p>
                <a href="../search-users.php" class="btn btn-primary mt-2">Find Friends</a>
            </div>
        `;
        return;
    }
    
    container.innerHTML = friends.map(friend => `
        <div class="card mb-3" style="background-color: #1e1e2f; border: 1px solid rgba(127, 0, 255, 0.2);">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            ${friend.profile_image 
                                ? `<img src="../uploads/profiles/${friend.profile_image}" class="rounded-circle" style="width: 48px; height: 48px; object-fit: cover;">`
                                : `<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-weight: bold;">
                                    ${friend.username.charAt(0).toUpperCase()}
                                   </div>`
                            }
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 text-light">${friend.name || friend.username}</h6>
                            <p class="text-muted mb-0">@${friend.username}</p>
                            <div class="d-flex gap-3 mt-1">
                                <small class="text-light">
                                    <i class="bi bi-person-plus me-1"></i>${friend.follower_count} followers
                                </small>
                                <small class="text-light">
                                    <i class="bi bi-person-check me-1"></i>${friend.following_count} following
                                </small>
                                <small class="text-light">
                                    <i class="bi bi-people me-1"></i>${friend.friend_count} friends
                                </small>
                            </div>
                        </div>
                    </div>
                    <div>
                        <a href="view-profile.php?username=${friend.username}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye me-1"></i>View Profile
                        </a>
                        <button class="btn btn-danger btn-sm ms-2" onclick="removeFriend(${friend.id})">
                            <i class="bi bi-person-x me-1"></i>Remove
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function loadFollowers() {
    const container = document.getElementById('followersContainer');
    
    fetch('../api/get-followers.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateFollowersList(data.followers, container);
            } else {
                container.innerHTML = `
                    <div class="text-center p-5">
                        <i class="bi bi-exclamation-circle text-danger" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Error Loading Followers</h4>
                        <p class="text-muted">${data.message || 'An error occurred while loading followers.'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading followers:', error);
            container.innerHTML = `
                <div class="text-center p-5">
                    <i class="bi bi-exclamation-circle text-danger" style="font-size: 3rem;"></i>
                    <h4 class="mt-3">Error Loading Followers</h4>
                    <p class="text-muted">An error occurred while loading followers.</p>
                </div>
            `;
        });
}

function updateFollowersList(followers, container) {
    if (followers.length === 0) {
        container.innerHTML = `
            <div class="text-center p-5">
                <i class="bi bi-person-plus text-muted" style="font-size: 3rem;"></i>
                <h4 class="mt-3">No Followers Yet</h4>
                <p class="text-muted">You don't have any followers yet. Start by being active on the platform!</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = followers.map(follower => `
        <div class="card mb-3" style="background-color: #1e1e2f; border: 1px solid rgba(127, 0, 255, 0.2);">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            ${follower.profile_image 
                                ? `<img src="../uploads/profiles/${follower.profile_image}" class="rounded-circle" style="width: 48px; height: 48px; object-fit: cover;">`
                                : `<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-weight: bold;">
                                    ${follower.username.charAt(0).toUpperCase()}
                                   </div>`
                            }
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 text-light">${follower.name || follower.username}</h6>
                            <p class="text-muted mb-0">@${follower.username}</p>
                            <div class="d-flex gap-3 mt-1">
                                <small class="text-light">
                                    <i class="bi bi-person-plus me-1"></i>${follower.follower_count} followers
                                </small>
                                <small class="text-light">
                                    <i class="bi bi-person-check me-1"></i>${follower.following_count} following
                                </small>
                                <small class="text-light">
                                    <i class="bi bi-people me-1"></i>${follower.friend_count} friends
                                </small>
                            </div>
                        </div>
                    </div>
                    <div>
                        <a href="view-profile.php?username=${follower.username}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye me-1"></i>View Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function removeFriend(userId) {
    showConfirm('Are you sure you want to remove this friend?', function() {
        fetch('../api/remove-friend.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadFriends();
            } else {
                showToast(data.message || 'Failed to remove friend', 'error');
            }
        })
        .catch(() => showToast('An error occurred', 'error'));
    });
}

function respondToFriendRequest(userId, action) {
    fetch('../api/respond-friend-request.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            user_id: userId,
            action: action
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload friend requests
            loadFriendRequests();
        } else {
            showToast(data.message || `Failed to ${action} friend request`, 'error');
        }
    })
    .catch(() => showToast('An error occurred', 'error'));
}

function refreshAchievements() {
    const button = event.target.closest('button');
    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Refreshing...';

    fetch('refresh_achievements.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showToast('Failed to refresh achievements: ' + data.error, 'error');
            }
        })
        .catch(() => showToast('Failed to refresh achievements', 'error'))
        .finally(() => {
            button.disabled = false;
            button.innerHTML = originalHtml;
        });
}
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>