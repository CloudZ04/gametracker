<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<?php
// Include database connection
require_once 'includes/db.php';

// Handle search, filter, and sort parameters from URL
$search = $_GET['search'] ?? '';
$platform = $_GET['platform'] ?? '';
$genre = $_GET['genre'] ?? '';
$sort = $_GET['sort'] ?? '';

// Build WHERE clause for SQL query based on filters
$where = [];
$order = "ORDER BY (release_date IS NULL), release_date ASC";

// Only show games from 2025 onwards or TBA games
$where[] = "(release_date >= '2025-01-01' OR (is_tba = 1 AND (tba_year >= 2025 OR tba_year IS NULL)))";

if (!empty($search)) {
    $searchUpper = strtoupper($search);
    $abbrevPattern = '';
    
    // If the search term is short enough to be an abbreviation (less than 5 chars)
    if (strlen($search) < 5) {
        // Convert "gta" or "GTA" to "G[^ ]+ T[^ ]+ A[^ ]+"
        $abbrevPattern = implode('[^ ]+ ', str_split($searchUpper)) . '[^ ]*';
        $where[] = "(title LIKE '%" . $conn->real_escape_string($search) . "%' OR UPPER(title) REGEXP '" . $conn->real_escape_string($abbrevPattern) . "')";
    } else {
        $where[] = "title LIKE '%" . $conn->real_escape_string($search) . "%'";
    }
}

if (!empty($platform)) {
    $where[] = "platforms LIKE '%" . $conn->real_escape_string($platform) . "%'";
}
if (!empty($genre)) {
    $where[] = "genre LIKE '%" . $conn->real_escape_string($genre) . "%'";
}

// Set sort order based on user selection
if ($sort == 'title_asc') {
    $order = "ORDER BY title ASC";
} elseif ($sort == 'release_asc') {
    $order = "ORDER BY release_date ASC";
} elseif ($sort == 'release_desc') {
    $order = "ORDER BY release_date DESC";
}

// Build and execute main query
$sql = "SELECT * FROM games";
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " $order";

$result = $conn->query($sql);

// Fetch user's game statuses if logged in
$userStatuses = [];
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $statusQuery = $conn->prepare("SELECT game_id, status FROM user_game_status WHERE user_id = ?");
    $statusQuery->bind_param("i", $userId);
    $statusQuery->execute();
    $statusResult = $statusQuery->get_result();
    while ($row = $statusResult->fetch_assoc()) {
        $userStatuses[$row['game_id']] = $row['status'];
    }
    $statusQuery->close();
}

// Fetch user's wishlist if logged in
$userWishlist = [];
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $wishlistQuery = $conn->prepare("SELECT game_id FROM user_wishlist WHERE user_id = ?");
    $wishlistQuery->bind_param("i", $userId);
    $wishlistQuery->execute();
    $wishlistResult = $wishlistQuery->get_result();
    while ($row = $wishlistResult->fetch_assoc()) {
        $userWishlist[] = $row['game_id'];
    }
    $wishlistQuery->close();
}

// Build sidebar timeline data
$months = [];
$tbaExists = false;

// Pre-scan games to build sidebar timeline
$allGames = [];
while($row = $result->fetch_assoc()) {
    $allGames[] = $row;
    $releaseDate = $row['release_date'];
    $releaseDateObj = $releaseDate ? new DateTime($releaseDate) : null;

    if ($releaseDateObj && $releaseDateObj->format('d') !== '01') {
        $monthYear = $releaseDateObj->format('F Y');
        $months[strtolower(str_replace(' ', '', $monthYear))] = $monthYear;
    } else if (empty($row['release_date']) && !empty($row['is_tba'])) {
        $tbaExists = true;
    }
}

// Group TBA games by year
$tbaGroups = [];
foreach ($allGames as $row) {
    if (empty($row['release_date']) && !empty($row['is_tba'])) {
        $year = $row['tba_year'] ?? 'TBA';
        $tbaGroups[$year][] = $row;
    }
}

// Sort TBA groups by year
uksort($tbaGroups, function($a, $b) {
    return (int)$a <=> (int)$b;
});

// Fetch random portrait images for hero section background
$heroImages = [];
$bgQuery = $conn->query("SELECT portrait_image_url FROM games WHERE portrait_image_url IS NOT NULL AND portrait_image_url != '' AND (release_date >= '2025-01-01' OR (is_tba = 1 AND (tba_year >= 2025 OR tba_year IS NULL))) ORDER BY RAND() LIMIT 6");
if ($bgQuery) {
    while ($row = $bgQuery->fetch_assoc()) {
        if (!empty($row['portrait_image_url'])) {
            $heroImages[] = $row['portrait_image_url'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="GameTracker | Timeline view of upcoming games. See what's coming next in chronological order.">
    <title>GameTracker.gg | Release Timeline</title>
    <!-- Include required CSS and fonts -->
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
            height: 400px;
            width: 350px;
            object-fit: cover;
            transform: rotate(-3deg);
            border-radius: 12px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.4);
        }
          
        /* Sidebar styling */
        .sidebar {
            background: var(--card-bg);
            padding: 1.5rem 1rem;
            border-radius: 12px;
            position: sticky;
            top: 100px;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(127, 0, 255, 0.1);
            z-index: 100;
        }
        
        .sidebar-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 10px;
        }
        
        .sidebar-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--primary-color);
            border-radius: 2px;
        }
        
        .sidebar .nav-link {
            color: var(--text-muted);
            border-radius: 8px;
            margin-bottom: 0.5rem;
            padding: 0.6rem 1rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            border-left: 3px solid transparent;
        }
        
        .sidebar .nav-link:hover {
            background-color: rgba(127, 0, 255, 0.1);
            color: var(--text-light);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(127, 0, 255, 0.2);
            border-left: 3px solid var(--primary-color);
            font-weight: 600;
        }
        
        /* Timeline and card styling */
        .month-section {
            position: relative;
            margin-bottom: 3rem;
            padding-bottom: 2rem;
        }
        
        .month-heading {
            font-family: 'Orbitron', sans-serif;
            margin-bottom: 2rem;
            position: relative;
            display: inline-block;
            padding-bottom: 10px;
        }
        
        .month-heading::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: var(--primary-color);
            border-radius: 2px;
        }
        
        .month-section::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 70%;
            height: 1px;
            background: linear-gradient(to right, transparent, rgba(127, 0, 255, 0.3), transparent);
            transform: translateX(-50%);
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .sidebar {
                position: relative;
                top: 0;
                max-height: none;
                margin-bottom: 2rem;
            }
        }
        
        /* Custom scrollbar for sidebar */
        #timeline-sidebar::-webkit-scrollbar {
            width: 10px;
            background: rgba(30, 30, 47, 0.2);
            border-radius: 8px;
        }
        
        #timeline-sidebar::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #b200ff 40%, #302b63 100%);
            border-radius: 8px;
            border: 2px solid #181828;
        }
        
        #timeline-sidebar::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #9933ff 40%, #b200ff 100%);
        }
        
        #timeline-sidebar::-webkit-scrollbar-corner {
            background: transparent;
        }
        
        /* For Firefox */
        #timeline-sidebar {
            scrollbar-width: thin;
            scrollbar-color: #b200ff #181828;
        }

        /* Mobile Responsive Improvements */
        @media (max-width: 992px) {
            /* Main layout changes for mobile */
            .d-flex.align-items-start {
                flex-direction: column;
            }

            .sidebar {
                position: relative;
                top: 0;
                max-height: none;
                margin-bottom: 2rem;
                margin-right: 0 !important;
                width: 100%;
                padding: 1rem;
            }

            .sidebar-title {
                font-size: 1.1rem;
                margin-bottom: 1rem;
            }

            .sidebar .nav-link {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
                margin-bottom: 0.25rem;
            }

            /* Hero section mobile */
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

            /* Month sections mobile */
            .month-section {
                margin-bottom: 2rem;
                padding-bottom: 1.5rem;
            }

            .month-heading {
                font-size: 1.5rem;
                margin-bottom: 1.5rem;
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
            .wishlist-icon {
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

            .status-icon > i,
            .wishlist-icon > i {
                font-size: 1.4rem;
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

        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 2.2rem !important;
            }

            .hero-image-stack img {
                height: 250px;
                width: 200px;
            }

            .sidebar {
                padding: 0.75rem;
            }

            .sidebar-title {
                font-size: 1rem;
            }

            .sidebar .nav-link {
                padding: 0.4rem 0.6rem;
                font-size: 0.85rem;
            }

            .month-heading {
                font-size: 1.3rem;
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

        @media (max-width: 576px) {
            .hero-section h1 {
                font-size: 2rem !important;
            }

            .hero-section .lead {
                font-size: 0.95rem;
            }

            .sidebar {
                padding: 0.5rem;
                border-radius: 8px;
            }

            .sidebar-title {
                font-size: 0.95rem;
                margin-bottom: 0.75rem;
            }

            .sidebar .nav-link {
                padding: 0.35rem 0.5rem;
                font-size: 0.8rem;
                border-radius: 6px;
            }

            .month-heading {
                font-size: 1.2rem;
                margin-bottom: 1rem;
            }

            .month-section {
                margin-bottom: 1.5rem;
                padding-bottom: 1rem;
            }

            /* Stack filter elements vertically on very small screens */
            .filter-container .col-lg-4,
            .filter-container .col-lg-2,
            .filter-container .col-md-6,
            .filter-container .col-md-12 {
                width: 100%;
                margin-bottom: 0.75rem;
            }
        }

        /* Landscape mobile optimization */
        @media (max-width: 992px) and (orientation: landscape) {
            .hero-section {
                padding: 3rem 0 1.5rem;
            }

            .hero-section h1 {
                font-size: 2.2rem !important;
            }

            .hero-image-stack img {
                height: 250px;
            }

            .sidebar {
                margin-bottom: 1.5rem;
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

            .sidebar .nav-link:hover {
                transform: none;
                background-color: rgba(127, 0, 255, 0.1);
            }

            .sidebar .nav-link:active {
                transform: scale(0.98);
                background-color: rgba(127, 0, 255, 0.2);
            }
        }

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

<body data-bs-spy="scroll" data-bs-target="#timeline-sidebar" data-bs-offset="100" tabindex="0">

<?php include 'includes/nav.php'; ?>

<!-- Hero Section -->
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
                    <span style="color: #b200ff;">Release</span> Timeline
                </h1>
                <p class="lead mb-4">Browse upcoming games chronologically and plan your gaming calendar. Navigate between months and see what's coming on your favorite platforms.</p>
  </div>
        </div>
    </div>
</section>

<!-- Main Timeline Layout -->
<div class="d-flex align-items-start">
    <!-- Sidebar on the left, outside the container -->
    <div id="timeline-sidebar" class="sidebar me-3">
        <h4 class="sidebar-title">Jump to Month</h4>
        <nav class="nav flex-column">
            <?php foreach ($months as $id => $name): ?>
                <a class="nav-link" href="#<?= $id ?>">
                    <i class="bi bi-calendar3 me-2"></i><?= $name ?>
                </a>
            <?php endforeach; ?>
            <?php if (!empty($tbaGroups)): ?>
                <h4 class="sidebar-title mt-4">TBA Releases</h4>
                <?php foreach (array_keys($tbaGroups) as $year): ?>
                    <a class="nav-link" href="#tba<?= $year ?>">
                        <i class="bi bi-clock-history me-2"></i>
                        <?php if ($year === 'TBA'): ?>
                            TBA
                        <?php else: ?>
                            TBA <?= htmlspecialchars($year) ?>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </nav>
    </div>
    <!-- Main content: filter + cards -->
    <div class="container py-4">
        <div class="filter-container mb-4">
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
                        <option value="release_asc" <?= $sort == 'release_asc' ? 'selected' : '' ?>>Release Date (Earliest)</option>
                        <option value="release_desc" <?= $sort == 'release_desc' ? 'selected' : '' ?>>Release Date (Latest)</option>
                        <option value="title_asc" <?= $sort == 'title_asc' ? 'selected' : '' ?>>Title (A–Z)</option>
              </select>
          </div>
                <div class="col-6 col-md-6 col-lg-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit" aria-label="Apply selected filters to search results">
                        <i class="ph ph-funnel me-2"></i> Apply Filters
                    </button>
          </div>
        </form>
        </div>
        <!-- Timeline Cards and Sections (months, TBA) go here, using full container width -->
        <?php
$currentMonth = '';
        $counter = 0;
        
// First, show all dated games
foreach ($allGames as $row) {
    if (!empty($row['release_date']) && empty($row['is_tba'])) {
        $releaseDateObj = new DateTime($row['release_date']);
        $monthYear = $releaseDateObj->format('F Y');
        $monthId = strtolower(str_replace(' ', '', $monthYear));

        if ($monthYear !== $currentMonth) {
                    if ($currentMonth !== '') echo '</div></div>';
            $currentMonth = $monthYear;
                    echo "<div id='$monthId' class='month-section'>";
                    echo "<h2 class='month-heading'>$monthYear</h2>";
                    echo "<div class='row g-4'>";
                }
                
                // Individual game card
                $counter++;
                ?>
                <div class="col-12 col-sm-6 col-md-6 col-lg-4">
                    <a href="games/game-detail.php?id=<?= $row['id'] ?>" class="text-decoration-none" aria-label="View details for <?= htmlspecialchars($row['title']) ?>">
                        <div class="game-card" style="--animation-order: <?= $counter ?>">
                            <div class="card-img-container">
                                <?php if (!empty($row['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="<?= htmlspecialchars($row['title']) ?>">
                                <?php else: ?>
                                    <img src="assets/default-game.jpg" alt="Default game image">
                                <?php endif; ?>
                                <?php
                                    $status = $userStatuses[$row['id']] ?? '';
                                    $statusClass = strtolower($status);
                                    $now = new DateTime();
                                    $releaseDate = new DateTime($row['release_date']);
                                    $isReleased = $releaseDate <= $now;
                                ?>
                                <button class="status-icon <?= htmlspecialchars($statusClass) ?>" 
                                    <?php if (isset($_SESSION['user_id']) && $isReleased): ?>
                                        data-bs-toggle="modal" 
                                        data-bs-target="#statusModal"
                                    <?php endif; ?>
                                    data-game-id="<?= $row['id'] ?>" 
                                    data-status="<?= htmlspecialchars($status) ?>"
                                    data-is-released="<?= $isReleased ? '1' : '0' ?>"
                                    title="<?= $isReleased ? 'Set play status' : 'Available after release' ?>">
                                    <i class="ph-fill ph-game-controller"></i>
                                </button>
                                <button class="wishlist-icon<?= in_array($row['id'], $userWishlist ?? []) ? ' active' : '' ?>" 
                                    data-game-id="<?= $row['id'] ?>" 
                                    title="Add to Wishlist">
                                    <i class="bi bi-cart"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($row['title']) ?></h5>
                                
                                <div class="game-info">
                                    <strong>Release:</strong>
                                    <?php
                                        echo date('F j, Y', strtotime($row['release_date']));
                                        
                                        $releaseClass = ($releaseDate > $now) ? "coming-soon" : "out-now";
                                    ?>
                                    <span class="<?= $releaseClass ?>">
                                        (<?= ($releaseDate > $now) ? "Coming Soon" : "Out Now" ?>)
                                    </span>
                                </div>
                                
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
                <?php
            }
        }
        
        // Close the last dated section if needed
        if ($currentMonth !== '') {
            echo '</div></div>';
        }
        
        // Output TBA sections
        foreach ($tbaGroups as $year => $games) {
            echo "<div id='tba$year' class='month-section'>";
            if ($year === 'TBA') {
                echo "<h2 class='month-heading'>TBA</h2>";
            } else {
                echo "<h2 class='month-heading'>TBA " . htmlspecialchars($year) . "</h2>";
            }
            echo "<div class='row g-4'>";
            
            foreach ($games as $row) {
                $counter++;
                ?>
                <div class="col-12 col-sm-6 col-md-6 col-lg-4">
                    <a href="games/game-detail.php?id=<?= $row['id'] ?>" class="text-decoration-none" aria-label="View details for <?= htmlspecialchars($row['title']) ?>">
                        <div class="game-card" style="--animation-order: <?= $counter ?>">
                            <div class="card-img-container">
                                <?php if (!empty($row['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="<?= htmlspecialchars($row['title']) ?>">
                                <?php else: ?>
                                    <img src="assets/default-game.jpg" alt="Default game image">
                                <?php endif; ?>
                                <?php
                                    $status = $userStatuses[$row['id']] ?? '';
                                    $statusClass = strtolower($status);
                                    $now = new DateTime();
                                    $releaseDate = new DateTime($row['release_date']);
                                    $isReleased = $releaseDate <= $now;
                                ?>
                                <button class="status-icon <?= htmlspecialchars($statusClass) ?>" 
                                    <?php if (isset($_SESSION['user_id']) && $isReleased): ?>
                                        data-bs-toggle="modal" 
                                        data-bs-target="#statusModal"
                                    <?php endif; ?>
                                    data-game-id="<?= $row['id'] ?>" 
                                    data-status="<?= htmlspecialchars($status) ?>"
                                    data-is-released="<?= $isReleased ? '1' : '0' ?>"
                                    title="<?= $isReleased ? 'Set play status' : 'Available after release' ?>">
                                    <i class="ph-fill ph-game-controller"></i>
                                </button>
                                <button class="wishlist-icon<?= in_array($row['id'], $userWishlist ?? []) ? ' active' : '' ?>" 
                                    data-game-id="<?= $row['id'] ?>" 
                                    title="Add to Wishlist">
                                    <i class="bi bi-cart"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($row['title']) ?></h5>
                                
                                <div class="game-info">
                                    <strong>Release:</strong>
                                    <span class="coming-soon">
                                        <?php if ($year === 'TBA'): ?>
                                            TBA
                                        <?php else: ?>
                                            TBA <?= htmlspecialchars($year) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
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
                <?php
            }
            
            echo '</div></div>';
        }
        
        // Show message if no games found
        if (empty($allGames)) {
            ?>
            <div class="text-center py-5">
                <i class="bi bi-controller" style="font-size: 3rem; color: var(--primary-color); opacity: 0.6;"></i>
                <h3 class="mt-3">No games found</h3>
                <p>Try adjusting your search or filter criteria</p>
                <a href="timeline.php" class="btn btn-primary mt-3">Clear Filters</a>
            </div>
            <?php
        }
        ?>
    </div>
</div>

<!-- Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="statusModalLabel">Update Play Status</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
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

<!-- Add Login Prompt Modal -->
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
    // Activate scrollspy
    document.addEventListener('DOMContentLoaded', function() {
        var scrollSpy = new bootstrap.ScrollSpy(document.body, {
            target: '#timeline-sidebar',
            offset: 100
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('#timeline-sidebar .nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 90,
                        behavior: 'smooth'
                    });
                }
            });
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        let selectedStatus = null;
        let selectedGameId = null;

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

                const isReleased = this.getAttribute('data-is-released') === '1';
                
                if (!isReleased) {
                    // Show tooltip or alert that game is not released yet
                    return;
                }
                
                <?php if (!isset($_SESSION['user_id'])): ?>
                    const loginModal = new bootstrap.Modal(document.getElementById('loginPromptModal'));
                    loginModal.show();
                    return;
                <?php else: ?>
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

                // Show modal
                const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
                statusModal.show();
                <?php endif; ?>
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
                    
                    // Force close modal
                    const modalEl = document.getElementById('statusModal');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                    
                    // Force cleanup after delay
                    setTimeout(() => {
                        modalEl.classList.remove('show');
                        modalEl.style.display = 'none';
                        modalEl.setAttribute('aria-hidden', 'true');
                        modalEl.removeAttribute('aria-modal');
                        
                        // Remove backdrop
                        const backdrop = document.querySelector('.modal-backdrop');
                        if (backdrop) {
                            backdrop.remove();
                        }
                        
                        // Reset body
                        document.body.classList.remove('modal-open');
                        document.body.style.removeProperty('padding-right');
                        document.body.style.removeProperty('overflow');
                    }, 100);
                    
                    // Reset values
                    selectedStatus = null;
                    selectedGameId = null;
                    document.getElementById('submitStatus').disabled = true;
                    
                    // Reset status options
                    document.querySelectorAll('.status-option').forEach(opt => {
                        opt.classList.remove('active');
                    });
                } else {
                    console.error('Failed to update status:', data.error || data.message);
                }
            })
            .catch(error => {
                console.error('Error updating status:', error);
            });
        });

        // Add event listener for when modal is hidden
        document.getElementById('statusModal').addEventListener('hidden.bs.modal', function () {
            // Clean up modal state
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.body.style.removeProperty('overflow');
            
            // Remove any remaining backdrops
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            
            // Reset variables
            selectedStatus = null;
            selectedGameId = null;
            document.getElementById('submitStatus').disabled = true;
            
            // Reset status options
            document.querySelectorAll('.status-option').forEach(opt => {
                opt.classList.remove('active');
            });
        });
    });

    document.querySelectorAll('.wishlist-icon').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            <?php if (!isset($_SESSION['user_id'])): ?>
                const loginModal = new bootstrap.Modal(document.getElementById('loginPromptModal'));
                loginModal.show();
                return;
            <?php else: ?>
            const gameId = this.getAttribute('data-game-id');
            const isActive = this.classList.contains('active');
            fetch('api/wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `game_id=${gameId}&action=${isActive ? 'remove' : 'add'}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.classList.toggle('active');
                }
            });
            <?php endif; ?>
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
            
            // Open review modal
            if (typeof openReviewModal === 'function') {
                openReviewModal(gameId);
            } else {
                console.error('Review modal function not found');
            }
        });
    });
</script>

<!-- Live Search JavaScript -->
<script>
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
                <img src="${game.image_url || 'images/logo.png'}" 
                     alt="${game.title}" 
                     class="search-result-image"
                     onerror="this.src='images/logo.png'">
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
            fetch(`api/search-games.php?q=${encodeURIComponent(query)}&timeline=true`)
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
</body>
</html>

<?php
$conn->close();
?>