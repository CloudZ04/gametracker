<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db.php';

// --- Handle search, filter, and sort parameters from the URL ---
// These parameters are used to filter and sort the games displayed on the page
$search = $_GET['search'] ?? '';
$platform = $_GET['platform'] ?? '';
$genre = $_GET['genre'] ?? '';
$sort = $_GET['sort'] ?? '';
$games_per_page = 12; // Number of games to display per page
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $games_per_page; // Calculate offset for pagination

// --- Build SQL WHERE and ORDER BY clauses based on filters ---
// Initialize arrays to store filter conditions and default sorting
$where = [];
$order = "ORDER BY avg_rating DESC, total_reviews DESC"; // Default to most popular

// Only show released games (release date is in the past — includes timeline games once they release)
$where[] = "(release_date IS NOT NULL AND release_date <= CURDATE())";

// Add search filter for game title using SQL LIKE for partial matches
if (!empty($search)) {
    $where[] = "title LIKE '%" . $conn->real_escape_string($search) . "%'";
}
// Add platform filter
if (!empty($platform)) {
    $where[] = "platforms LIKE '%" . $conn->real_escape_string($platform) . "%'";
}
// Add genre filter
if (!empty($genre)) {
    $where[] = "genre LIKE '%" . $conn->real_escape_string($genre) . "%'";
}

// --- Set sorting order based on user selection ---
if ($sort == 'popular') {
    $order = "ORDER BY avg_rating DESC, total_reviews DESC";
} elseif ($sort == 'recent') {
    $order = "ORDER BY release_date DESC";
} elseif ($sort == 'rating') {
    $order = "ORDER BY avg_rating DESC";
} elseif ($sort == 'title_asc') {
    $order = "ORDER BY title ASC";
}

// --- Build the main SQL query for paginated games ---
// Construct the SQL query with filters and pagination
$sql = "SELECT * FROM games";
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " $order LIMIT $games_per_page OFFSET $offset";

// --- Execute the query to get games for this page ---
$result = $conn->query($sql);

// --- If user is logged in, fetch their game statuses and wishlist ---
// This section handles user-specific data for game status and wishlist
$userStatuses = [];
$userWishlist = [];

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // Fetch user's status for each game (e.g., playing, completed)
    $statusQuery = $conn->prepare("SELECT game_id, status FROM user_game_status WHERE user_id = ?");
    $statusQuery->bind_param("i", $userId);
    $statusQuery->execute();
    $statusResult = $statusQuery->get_result();

    while ($row = $statusResult->fetch_assoc()) {
        $userStatuses[$row['game_id']] = $row['status'];
    }
    $statusQuery->close();

    // Fetch user's wishlist (game IDs)
    $wishlistQuery = $conn->prepare("SELECT game_id FROM user_wishlist WHERE user_id = ?");
    $wishlistQuery->bind_param("i", $userId);
    $wishlistQuery->execute();
    $wishlistResult = $wishlistQuery->get_result();

    while ($row = $wishlistResult->fetch_assoc()) {
        $userWishlist[] = $row['game_id'];
    }
    $wishlistQuery->close();
}

// --- Count total number of games for pagination ---
// This query helps calculate the total number of pages needed
$count_sql = "SELECT COUNT(*) as total FROM games";
if (!empty($where)) {
    $count_sql .= " WHERE " . implode(' AND ', $where);
}
$total_games_result = $conn->query($count_sql);
$total_games_row = $total_games_result->fetch_assoc();
$total_games = $total_games_row['total'];
$total_pages = ceil($total_games / $games_per_page);

// --- Fetch random portrait images for the hero section background ---
// Get random game images for the hero section background
$heroImages = [];
$bgQuery = $conn->query("SELECT portrait_image_url FROM games WHERE portrait_image_url IS NOT NULL AND portrait_image_url != '' ORDER BY RAND() LIMIT 6");
while ($row = $bgQuery->fetch_assoc()) {
    $heroImages[] = $row['portrait_image_url'];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="GameTracker | Browse and track your favorite released games. Create your game collection, track your progress, write reviews, and discover new games to play.">
    <title>GameTracker.gg | Browse Games</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="includes/styles.css">
    <style>
        /* Hero section styling */
        .hero-section {
            position: relative;
            padding: 6rem 0 4rem;
            background: linear-gradient(to right, rgba(21, 21, 30, 0.9), rgba(21, 21, 30, 0.7));
            border-bottom: 1px solid rgba(127, 0, 255, 0.3);
            margin-bottom: 2rem;
        }

        .hero-section h1 span:first-child {
            color: var(--primary-color);
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
            height: 400px;
            width: 350px;
            object-fit: cover;
            transform: rotate(-3deg);
            border-radius: 12px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.4);
        }

        /* Results info */
        .results-info {
            margin-bottom: 1.5rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* No results */
        .no-results {
            text-align: center;
            padding: 3rem;
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid rgba(127, 0, 255, 0.1);
        }

        .no-results i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            opacity: 0.6;
        }

        /* Mobile Responsive Improvements */
        @media (max-width: 768px) {
            .hero-section {
                padding: 4rem 0 2rem;
                text-align: center;
            }

            .hero-section h1 {
                font-size: 2.5rem !important;
                line-height: 1.2;
                margin-bottom: 1rem;
            }

            .hero-section .lead {
                font-size: 1rem;
                margin-bottom: 2rem;
            }

            .hero-image-stack {
                opacity: 0.05;
            }

            .hero-image-stack img {
                height: 300px;
                gap: 10px;
            }

            /* Filter improvements for mobile */
            .filter-container {
                padding: 1rem;
                margin-bottom: 1.5rem;
            }

            .filter-container .row {
                gap: 0.5rem;
            }

            .filter-container .col-lg-4,
            .filter-container .col-lg-2 {
                margin-bottom: 1rem;
            }

            .filter-container .btn {
                margin-top: 0.5rem;
            }

            /* Game cards mobile optimization */
            .game-card {
                margin-bottom: 1.5rem;
            }

            .game-card .card-img-container {
                height: 180px;
            }

            .game-card .card-title {
                font-size: 1.1rem;
                white-space: normal;
                overflow: visible;
                text-overflow: initial;
                line-height: 1.3;
            }

            .game-card .card-body {
                padding: 1rem;
            }

            /* Status and wishlist icons mobile */
            .status-icon,
            .wishlist-icon,
            .review-icon {
                width: 35px;
                height: 35px;
                opacity: 1;
            }

            .status-icon {
                top: 8px;
                right: 8px;
            }

            .wishlist-icon {
                top: 8px;
                right: 50px;
            }

            .review-icon {
                top: 8px;
                right: 110px;
            }

            .status-icon > i,
            .wishlist-icon > i,
            .review-icon > i {
                font-size: 1.4rem;
            }

            /* Results info mobile */
            .results-info .d-flex {
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
            }

            .results-info h2 {
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }

            /* Pagination mobile */
            .pagination {
                margin-top: 2rem;
                flex-wrap: wrap;
                justify-content: center;
            }

            .pagination .page-item {
                margin: 0.1rem;
            }

            .pagination .page-link {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }

            /* Modal mobile improvements */
            .modal-dialog {
                margin: 1rem;
                max-width: calc(100% - 2rem);
            }

            .status-option {
                padding: 0.75rem;
                margin-bottom: 0.5rem;
            }

            .status-option i {
                font-size: 1.5rem;
            }

            /* No results mobile */
            .no-results {
                padding: 2rem 1rem;
            }

            .no-results i {
                font-size: 2.5rem;
            }

            .no-results h3 {
                font-size: 1.3rem;
            }
        }

        @media (max-width: 576px) {
            .hero-section h1 {
                font-size: 2rem !important;
            }

            .hero-section .lead {
                font-size: 0.95rem;
            }

            .filter-container {
                padding: 0.75rem;
            }

            .game-card .card-img-container {
                height: 160px;
            }

            .game-card .card-title {
                font-size: 1rem;
            }

            .game-card .game-info {
                font-size: 0.8rem;
            }

            .platform-badge,
            .genre-badge {
                font-size: 0.65rem;
                padding: 1px 6px;
            }

            /* Stack filter elements vertically on very small screens */
            .filter-container .col-lg-4,
            .filter-container .col-lg-2,
            .filter-container .col-md-6,
            .filter-container .col-md-12 {
                width: 100%;
                margin-bottom: 0.75rem;
            }

            .status-icon,
            .wishlist-icon {
                width: 32px;
                height: 32px;
            }

            .status-icon > i,
            .wishlist-icon > i {
                font-size: 1.2rem;
            }
        }

        /* Landscape mobile optimization */
        @media (max-width: 768px) and (orientation: landscape) {
            .hero-section {
                padding: 3rem 0 1.5rem;
            }

            .hero-section h1 {
                font-size: 2.2rem !important;
            }

            .hero-image-stack img {
                height: 250px;
            }
        }

        /* Touch improvements */
        @media (hover: none) and (pointer: coarse) {
            .game-card:hover {
                transform: none;
            }

            .game-card:active {
                transform: scale(0.98);
                transition: transform 0.1s ease;
            }

            .status-icon,
            .wishlist-icon {
                opacity: 1;
            }

            .btn:hover {
                transform: none;
            }

            .btn:active {
                transform: scale(0.98);
            }
        }

        /* Search input text color */
        .search-input {
            color: #ffffff !important;
        }

        /* Live Search Dropdown */
        .search-container {
            position: relative;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #1e1e2f;
            border: 1px solid rgba(127, 0, 255, 0.2);
            border-radius: 8px;
            margin-top: 0.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            display: none;
            max-height: 400px;
            overflow-y: auto;
        }

        .search-result-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid rgba(127, 0, 255, 0.1);
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item:hover {
            background: rgba(127, 0, 255, 0.1);
        }

        .search-result-image {
            width: 50px;
            height: 70px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 1rem;
        }

        .search-result-info {
            flex: 1;
        }

        .search-result-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #fff;
        }

        .search-result-meta {
            font-size: 0.85rem;
            color: #a8a8b3;
        }

        .search-result-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
            margin-top: 0.25rem;
        }

        .search-result-badge {
            font-size: 0.7rem;
            padding: 0.1rem 0.5rem;
            border-radius: 12px;
            background: rgba(127, 0, 255, 0.1);
            border: 1px solid rgba(127, 0, 255, 0.2);
            color: #fff;
        }

        /* Loading indicator */
        .search-loading {
            padding: 1rem;
            text-align: center;
            color: #a8a8b3;
            display: none;
        }

        .search-loading .spinner-border {
            width: 1.5rem;
            height: 1.5rem;
            margin-right: 0.5rem;
        }

        /* No results state */
        .search-no-results {
            padding: 1rem;
            text-align: center;
            color: #a8a8b3;
            display: none;
        }
    </style>
</head>
<body>

<?php include 'includes/nav.php'; ?>

<!-- Hero Section -->
<!-- This section creates a visually appealing header with a dynamic background -->
<section class="hero-section position-relative overflow-hidden">
    <div class="hero-image-stack">
        <?php foreach ($heroImages as $img): ?>
            <img src="<?= htmlspecialchars($img) ?>" alt="Game Background">
        <?php endforeach; ?>
    </div>
    <div class="container position-relative" style="z-index: 1;">
        <div class="row">
            <div class="col-lg-8">
                <h1 class="display-4 mb-3" style="font-family: 'Orbitron', sans-serif;">
                <span style="color: #b200ff;">Browse</span> Games
                </h1>
                <p class="lead mb-4">Track your favorite games, write reviews, and build your collection. Filter by platform, genre, and more to find your next gaming adventure.</p>
            </div>
        </div>
    </div>
</section>


<div class="container py-4">
    <!-- Filter Container -->
    <!-- This section contains the search and filter controls for the game list -->
    <div class="filter-container">
        <form method="get" class="row g-3">
            <div class="col-12 col-md-6 col-lg-4">
                <label for="search" class="form-label">Search</label>
                <div class="search-container">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-light">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="search" id="search" class="form-control search-input border-start-0" 
                               placeholder="Search game titles..." value="<?= htmlspecialchars($search) ?>"
                               autocomplete="off">
                    </div>
                    <!-- Live Search Results Dropdown -->
                    <div class="search-results">
                        <div class="search-loading">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            Searching...
                        </div>
                        <div class="search-no-results">
                            No games found
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="platform" class="form-label">Platform</label>
                <select name="platform" id="platform" class="form-select custom-select">
                    <option value="">All Platforms</option>
                    <option value="PlayStation" <?= $platform == 'PlayStation' ? 'selected' : '' ?>>PlayStation</option>
                    <option value="Xbox" <?= $platform == 'Xbox' ? 'selected' : '' ?>>Xbox</option>
                    <option value="PC" <?= $platform == 'PC' ? 'selected' : '' ?>>PC</option>
                    <option value="Switch" <?= $platform == 'Switch' ? 'selected' : '' ?>>Nintendo Switch</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="genre" class="form-label">Genre</label>
                <select name="genre" id="genre" class="form-select custom-select">
                    <option value="">All Genres</option>
                    <option value="RPG" <?= $genre == 'RPG' ? 'selected' : '' ?>>RPG</option>
                    <option value="Action" <?= $genre == 'Action' ? 'selected' : '' ?>>Action</option>
                    <option value="Shooter" <?= $genre == 'Shooter' ? 'selected' : '' ?>>Shooter</option>
                    <option value="Adventure" <?= $genre == 'Adventure' ? 'selected' : '' ?>>Adventure</option>
                </select>
            </div>
            <div class="col-6 col-md-6 col-lg-2">
                <label for="sort" class="form-label">Sort By</label>
                <select name="sort" id="sort" class="form-select custom-select">
                    <option value="popular" <?= ($sort == 'popular' || $sort == '') ? 'selected' : '' ?>>Most Popular</option>
                    <option value="recent" <?= $sort == 'recent' ? 'selected' : '' ?>>Recently Released</option>
                    <option value="rating" <?= $sort == 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                    <option value="title_asc" <?= $sort == 'title_asc' ? 'selected' : '' ?>>Title (A-Z)</option>
                </select>
            </div>
            <div class="col-6 col-md-6 col-lg-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit">
                    <i class="ph ph-funnel me-2"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>
    
    <!-- Results Info -->
    <div class="results-info">
        <?php if ($total_games > 0): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="section-title" style="font-family: 'Orbitron', sans-serif;">Games</h2>
                <p>Showing <?= min($games_per_page, $total_games) ?> of <?= $total_games ?> games</p>
            </div>
        <?php else: ?>
            <div class="no-results mb-4">
                <i class="bi bi-controller"></i>
                <h3>No games found</h3>
                <p>Try adjusting your search or filter criteria</p>
                <a href="explore.php" class="btn btn-primary mt-3">Clear Filters</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($total_games > 0): ?>
        <!-- Games Grid -->
        <!-- This section displays the filtered and paginated game cards -->
        <div class="row g-4">
            <?php 
            $counter = 0;
            while($row = $result->fetch_assoc()): 
                $counter++;
            ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-4">
                <a href="games/game-detail.php?id=<?= $row['id'] ?>" class="text-decoration-none" aria-label="View details for <?= htmlspecialchars($row['title']) ?>">
                    <div class="game-card" style="--animation-order: <?= $counter ?>">
                        <!-- Game image with status and wishlist buttons -->
                        <div class="card-img-container">
                            <?php if (!empty($row['image_url'])): ?>
                                <img src="<?= htmlspecialchars($row['image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($row['title']) ?>">
                            <?php else: ?>
                                <img src="assets/default-game.jpg" class="card-img-top" alt="Default game image">
                            <?php endif; ?>
                            
                            <?php
                                // Determine game status and release state
                                $status = $userStatuses[$row['id']] ?? '';
                                $statusClass = strtolower($status);
                                $now = new DateTime();
                                $releaseDate = new DateTime($row['release_date']);
                                $isReleased = $releaseDate <= $now;
                            ?>
                            <!-- Status button - only interactive if game is released -->
                            <button class="status-icon <?= htmlspecialchars($statusClass) ?>"
                                <?php if (isset($_SESSION['user_id']) && $isReleased): ?>
                                    data-game-id="<?= $row['id'] ?>"
                                    data-status="<?= htmlspecialchars($status) ?>"
                                    data-is-released="<?= $isReleased ? '1' : '0' ?>"
                                    title="<?= $isReleased ? 'Set play status' : 'Available after release' ?>"
                                <?php endif; ?>
                                >
                                <i class="ph-fill ph-game-controller"></i>
                            </button>
                            <!-- Wishlist button -->
                            <button class="wishlist-icon<?= in_array($row['id'], $userWishlist ?? []) ? ' active' : '' ?>" 
                                data-game-id="<?= $row['id'] ?>" 
                                title="Add to Wishlist">
                                <i class="bi bi-cart"></i>
                            </button>
                            <!-- Review button -->
                            <button class="review-icon" 
                                data-game-id="<?= $row['id'] ?>" 
                                title="Write a Review">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        </div>
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($row['title']) ?></h5>
                                
                                <!-- Release date information -->
                                <div class="game-info">
                                    <strong>Release:</strong>
                                    <?php
                                        $now = new DateTime();
                                        $releaseClass = "";
                                        
                                        // Handle different release date scenarios
                                        if (!empty($row['is_tba']) && $row['is_tba']) {
                                            echo "TBA" . (!empty($row['tba_year']) ? " " . htmlspecialchars($row['tba_year']) : "");
                                            $releaseClass = "coming-soon";
                                        } elseif (!empty($row['release_date'])) {
                                            $releaseDate = new DateTime($row['release_date']);
                                            echo date('F j, Y', strtotime($row['release_date']));
                                            
                                            if ($releaseDate > $now) {
                                                $releaseClass = "coming-soon";
                                            } else {
                                                $releaseClass = "out-now";
                                            }
                                        } else {
                                            echo "Unknown";
                                        }
                                    ?>
                                </div>
                                
                                <!-- Platform badges -->
                                <div class="mt-2">
                                    <?php
                                        if (!empty($row['platforms'])) {
                                            $platforms = explode(', ', $row['platforms']);
                                            foreach ($platforms as $plat) {
                                                echo "<span class='platform-badge'>" . htmlspecialchars($plat) . "</span>";
                                            }
                                        }
                                    ?>
                                </div>
                                
                                <!-- Genre badges -->
                                <div class="mt-2">
                                    <?php
                                        if (!empty($row['genre'])) {
                                            $genres = explode(', ', $row['genre']);
                                            foreach ($genres as $gen) {
                                                echo "<span class='genre-badge'>" . htmlspecialchars($gen) . "</span>";
                                            }
                                        }
                                    ?>
                                </div>
                            </div>
                    </div>
                    </a>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <!-- This section handles page navigation with dynamic page numbers -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-5">
                <ul class="pagination">
                    <!-- Previous page button -->
                    <?php if ($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $current_page - 1 ?>&search=<?= urlencode($search) ?>&platform=<?= urlencode($platform) ?>&genre=<?= urlencode($genre) ?>&sort=<?= urlencode($sort) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    // Calculate page range to display
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    // Show first page and ellipsis if needed
                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&platform=' . urlencode($platform) . '&genre=' . urlencode($genre) . '&sort=' . urlencode($sort) . '">1</a></li>';
                        if ($start_page > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    // Display page numbers
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        echo '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '">
                            <a class="page-link" href="?page=' . $i . '&search=' . urlencode($search) . '&platform=' . urlencode($platform) . '&genre=' . urlencode($genre) . '&sort=' . urlencode($sort) . '">' . $i . '</a>
                          </li>';
                    }
                    
                    // Show last page and ellipsis if needed
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&platform=' . urlencode($platform) . '&genre=' . urlencode($genre) . '&sort=' . urlencode($sort) . '">' . $total_pages . '</a></li>';
                    }
                    ?>
                    
                    <!-- Next page button -->
                    <?php if ($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $current_page + 1 ?>&search=<?= urlencode($search) ?>&platform=<?= urlencode($platform) ?>&genre=<?= urlencode($genre) ?>&sort=<?= urlencode($sort) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Status Modal -->
<!-- Modal for updating game play status -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="statusModalLabel">Update Play Status</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- Status options with icons and descriptions -->
        <div class="status-option" data-status="Want to Play">
            <i class="ph-fill ph-list-plus"></i>
            <div>
                <strong>Want to Play</strong>
                <br><small>Plan to play this game in the future</small>
            </div>
        </div>
        <div class="status-option" data-status="Playing">
            <i class="ph-fill ph-game-controller"></i>
            <div>
                <strong>Playing</strong>
                <br><small>Currently playing this game</small>
            </div>
        </div>
        <div class="status-option" data-status="Beaten">
            <i class="bi bi-check-circle"></i>
            <div>
                <strong>Beaten</strong>
                <br><small>Finished the main objective</small>
            </div>
        </div>
        <div class="status-option" data-status="Completed">
            <i class="bi bi-trophy"></i>
            <div>
                <strong>Completed</strong>
                <br><small>100% - All quests, items, achievements</small>
            </div>
        </div>
        <div class="status-option" data-status="Shelved">
            <i class="bi bi-pause-circle"></i>
            <div>
                <strong>Shelved</strong>
                <br><small>Put on hold, will finish later</small>
            </div>
        </div>
        <div class="status-option" data-status="Abandoned">
            <i class="bi bi-x-circle"></i>
            <div>
                <strong>Abandoned</strong>
                <br><small>Gave up on, won't play again</small>
            </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button id="clearStatus" class="btn btn-outline-light">Clear Status</button>
        <button id="submitStatus" class="btn btn-primary" disabled>Save Status</button>
      </div>
    </div>
  </div>
</div>

<!-- Login Prompt Modal -->
<!-- Modal shown when trying to use features that require authentication -->
<div class="modal fade" id="loginPromptModal" tabindex="-1" aria-labelledby="loginPromptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="loginPromptModalLabel">Login Required</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-4">You need to be logged in to use this feature. Would you like to log in or create an account?</p>
                <div class="d-flex gap-3">
                    <a href="auth/login.php" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </a>
                    <a href="auth/register.php" class="btn btn-outline-light flex-grow-1">
                        <i class="bi bi-person-plus me-2"></i>Sign Up
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/review-modal.php'; ?>
<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let selectedStatus = null;
    let selectedGameId = null;
    let statusModalInstance = null; // Store modal instance

    // Handle status icon click
    document.querySelectorAll('.status-icon').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            // Check if user is logged in
            <?php if (!isset($_SESSION['user_id'])): ?>
            const loginModal = new bootstrap.Modal(document.getElementById('loginPromptModal'));
            loginModal.show();
            return;
            <?php endif; ?>

            selectedGameId = this.getAttribute('data-game-id');
            const currentStatus = this.getAttribute('data-status');

            // Reset all options
            document.querySelectorAll('.status-option').forEach(opt => {
                opt.classList.remove('active');
            });

            // Set active class on current status
            if (currentStatus) {
                const matchingOption = document.querySelector(`.status-option[data-status="${currentStatus}"]`);
                if (matchingOption) {
                    matchingOption.classList.add('active');
                    selectedStatus = currentStatus;
                    document.getElementById('submitStatus').disabled = false;
                }
            } else {
                selectedStatus = null;
                document.getElementById('submitStatus').disabled = true;
            }

            // Show modal and store instance
            statusModalInstance = new bootstrap.Modal(document.getElementById('statusModal'));
            statusModalInstance.show();
        });
    });

    // Handle status option selection
    document.querySelectorAll('.status-option').forEach(opt => {
        opt.addEventListener('click', function() {
            // Reset all options
            document.querySelectorAll('.status-option').forEach(o => {
                o.classList.remove('active');
            });

            // Set active class on clicked option
            this.classList.add('active');
            selectedStatus = this.getAttribute('data-status');
            document.getElementById('submitStatus').disabled = false;
        });
    });

    // Handle clear status button
    document.getElementById('clearStatus').addEventListener('click', function() {
        selectedStatus = "Clear";
        document.querySelectorAll('.status-option').forEach(o => o.classList.remove('active'));
        document.getElementById('submitStatus').disabled = false;
    });

    // Handle submit status button
    document.getElementById('submitStatus').addEventListener('click', function() {
        if (!selectedGameId || !selectedStatus) return;

        fetch('api/save-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `game_id=${selectedGameId}&status=${encodeURIComponent(selectedStatus)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const icon = document.querySelector(`.status-icon[data-game-id="${selectedGameId}"]`);
                
                if (icon) {
                    // Remove all status classes
                    icon.classList.remove('playing', 'beaten', 'completed', 'shelved', 'abandoned', 'want-to-play');
                    
                    // Update with new status class if not cleared
                    if (selectedStatus !== 'Clear') {
                        const statusClass = selectedStatus.toLowerCase().replace(/\s+/g, '-');
                        icon.classList.add(statusClass);
                        icon.setAttribute('data-status', selectedStatus);
                    } else {
                        icon.setAttribute('data-status', '');
                    }
                }
                
                // Close modal using Bootstrap's method
                if (statusModalInstance) {
                    statusModalInstance.hide();
                }
                
                // Reset values
                selectedStatus = null;
                selectedGameId = null;
                document.getElementById('submitStatus').disabled = true;
            }
        })
        .catch(error => {
            console.error('Error updating status:', error);
        });
    });

    // Add event listener for when modal is hidden
    document.getElementById('statusModal').addEventListener('hidden.bs.modal', function () {
        document.body.classList.remove('modal-open');
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) backdrop.remove();
    });

    // Wishlist functionality
    document.querySelectorAll('.wishlist-icon').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Check if user is logged in
            <?php if (!isset($_SESSION['user_id'])): ?>
            const loginModal = new bootstrap.Modal(document.getElementById('loginPromptModal'));
            loginModal.show();
            return;
            <?php endif; ?>

            const gameId = this.getAttribute('data-game-id');
            const isActive = this.classList.contains('active');
            const action = isActive ? 'remove' : 'add';

            fetch('api/wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `game_id=${gameId}&action=${action}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.classList.toggle('active');
                }
            })
            .catch(error => {
                console.error('Error updating wishlist:', error);
            });
        });
    });

    // Review functionality
    document.querySelectorAll('.review-icon').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Check if user is logged in
            <?php if (!isset($_SESSION['user_id'])): ?>
            const loginModal = new bootstrap.Modal(document.getElementById('loginPromptModal'));
            loginModal.show();
            return;
            <?php endif; ?>

            const gameId = this.getAttribute('data-game-id');
            // Fetch user's review for this game
            fetch('<?= BASE_URL ?>api/fetch-user-review.php?game_id=' + gameId)
                .then(response => response.json())
                .then(data => {
                    if (typeof openReviewModal === 'function') {
                        if (data.success && data.review) {
                            openReviewModal(gameId, data.review);
                        } else {
                            openReviewModal(gameId);
                        }
                    } else {
                        console.error('Review modal function not found');
                    }
                })
                .catch(() => {
                    if (typeof openReviewModal === 'function') {
                        openReviewModal(gameId);
                    }
                });
        });
    });
});

let searchTimeout = null;
const searchInput = document.getElementById('search');
const searchResults = document.querySelector('.search-results');
const searchLoading = document.querySelector('.search-loading');
const searchNoResults = document.querySelector('.search-no-results');

// Function to show loading state
function showLoading() {
    searchResults.style.display = 'block';
    searchLoading.style.display = 'block';
    searchNoResults.style.display = 'none';
}

// Function to show no results
function showNoResults() {
    searchResults.style.display = 'block';
    searchLoading.style.display = 'none';
    searchNoResults.style.display = 'block';
}

// Function to hide search results
function hideResults() {
    searchResults.style.display = 'none';
}

// Function to create a result item
function createResultItem(game) {
    return `
        <a href="games/game-detail.php?id=${game.id}" class="search-result-item">
            <img src="${game.image_url || 'assets/default-game.jpg'}" 
                 alt="${game.title}" 
                 class="search-result-image"
                 onerror="this.src='assets/default-game.jpg'">
            <div class="search-result-info">
                <div class="search-result-title">${game.title}</div>
                <div class="search-result-meta">${game.release_date}</div>
                <div class="search-result-badges">
                    ${game.platforms.map(p => `<span class="search-result-badge">${p}</span>`).join('')}
                    ${game.genres.map(g => `<span class="search-result-badge">${g}</span>`).join('')}
                </div>
            </div>
        </a>
    `;
}

// Handle search input
searchInput.addEventListener('input', function(e) {
    const query = e.target.value.trim();
    
    // Clear previous timeout
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    // Hide results if query is empty
    if (!query) {
        hideResults();
        return;
    }

    // Show loading state
    showLoading();

    // Set new timeout
    searchTimeout = setTimeout(() => {
        fetch(`api/search-games.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.games.length > 0) {
                    searchResults.style.display = 'block';
                    searchLoading.style.display = 'none';
                    searchNoResults.style.display = 'none';

                    // Clear previous results except loading and no results divs
                    const resultsToRemove = searchResults.querySelectorAll('.search-result-item');
                    resultsToRemove.forEach(el => el.remove());

                    // Add new results
                    data.games.forEach(game => {
                        searchResults.insertAdjacentHTML('beforeend', createResultItem(game));
                    });
                } else {
                    showNoResults();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNoResults();
            });
    }, 300); // Wait 300ms after last keystroke before searching
});

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
        hideResults();
    }
});

// Prevent form submission when selecting a result
searchResults.addEventListener('click', function(e) {
    if (e.target.closest('.search-result-item')) {
        e.preventDefault();
        const href = e.target.closest('.search-result-item').href;
        window.location.href = href;
    }
});
</script>