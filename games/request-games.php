<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db.php';

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

// Only show released games (release date is in the past)
$where[] = "(release_date <= CURDATE() AND is_tba = 0)";

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
    <link rel="stylesheet" href="../includes/styles.css">
    <style>
        .request-form-container {
            background: var(--card-bg);
            border: 1px solid rgba(127, 0, 255, 0.2);
            border-radius: 1rem;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .form-label {
            color: var(--text-light);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-family: 'Exo 2', sans-serif;
        }

        .form-control, .form-select {
            background-color: rgba(30, 30, 47, 0.5);
            border: 1px solid rgba(127, 0, 255, 0.3);
            color: white;
            transition: all 0.3s ease;
            font-family: 'Exo 2', sans-serif;
        }

        .form-control:focus, .form-select:focus {
            background-color: rgba(30, 30, 47, 0.8);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(127, 0, 255, 0.25);
            color: white;
        }

        .form-text {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .btn-submit {
            background: var(--primary-color);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            font-weight: 600;
            font-family: 'Exo 2', sans-serif;
            transition: all 0.3s ease;
            border-radius: 0.5rem;
        }

        .btn-submit:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(127, 0, 255, 0.4);
        }

        .image-preview {
            max-width: 300px;
            max-height: 300px;
            border-radius: 0.5rem;
            display: none;
            margin-top: 1rem;
            border: 1px solid rgba(127, 0, 255, 0.2);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .section-title {
            font-family: 'Orbitron', sans-serif;
            color: var(--text-light);
            margin-bottom: 1.5rem;
            position: relative;
            display: inline-block;
            font-size: 1.75rem;
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
            height: 500px;
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
            .request-form-container {
                padding: 1.5rem;
                margin-top: 1rem;
            }

            .btn-submit {
                width: 100%;
                padding: 0.75rem 1rem;
            }

            .hero-section {
                padding: 3rem 0 1.5rem;
                text-align: center;
            }

            .hero-section h1 {
                font-size: 2rem !important;
            }

            .hero-section .lead {
                font-size: 1rem;
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

        .btn-primary {
            background: var(--primary-color);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            font-weight: 600;
            font-family: 'Exo 2', sans-serif;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(127, 0, 255, 0.3);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(127, 0, 255, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            background: rgba(127, 0, 255, 0.5);
            transform: none;
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .btn-primary {
                width: 100%;
                padding: 0.75rem 1rem;
            }
        }

        .btn-outline-light {
            background: transparent;
            border: 2px solid rgba(127, 0, 255, 0.5);
            color: white;
            transition: all 0.3s ease;
            font-family: 'Exo 2', sans-serif;
            padding: 0.5rem 1.5rem;
        }

        .btn-outline-light:hover {
            background: rgba(127, 0, 255, 0.2);
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(127, 0, 255, 0.3);
        }

        .btn-outline-light:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        @media (max-width: 768px) {
            .btn-outline-light {
                width: 100%;
                padding: 0.75rem 1rem;
            }
        }
    </style>
</head>
<body>

<?php include '../includes/nav.php'; ?>

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
                    <span style="color: #b200ff;">Request</span> Games
                </h1>
                <p class="lead mb-4">Can't find a game you want to track? Submit a request to add it to our database.</p>
            </div>
        </div>
    </div>
</section>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="request-form-container">
                <form id="gameRequestForm" action="process-game-request.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="gameTitle" class="form-label">Game Title *</label>
                        <input type="text" class="form-control" id="gameTitle" name="gameTitle" required>
                        <div class="form-text">Please provide the exact title of the game. (Please ensure if it is a special edition, include the edition name in the title).</div>
    </div>
    
                    <div class="mb-4">
                        <label for="releaseYear" class="form-label">Release Year</label>
                        <input type="number" class="form-control" id="releaseYear" name="releaseYear" min="1950" max="<?= date('Y') + 5 ?>">
                        <div class="form-text">If known, enter the year the game was or will be released.</div>
    </div>

                    <div class="mb-4">
                        <label for="platforms" class="form-label">Platforms *</label>
                        <input type="text" class="form-control" id="platforms" name="platforms" required>
                        <div class="form-text">Enter platforms separated by commas (e.g., PC, PS5, Xbox Series X)</div>
                        </div>

                    <div class="mb-4">
                        <label for="gameImage" class="form-label">Game Image</label>
                        <input type="file" class="form-control" id="gameImage" name="gameImage" accept="image/*">
                        <div class="form-text">Optional: Upload a cover image or screenshot of the game (We may choose to use the image as the cover if suitable, but this is not guaranteed).</div>
                        <img id="imagePreview" class="image-preview" alt="Image preview">
                                </div>
                                
                    <div class="mb-4">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required maxlength="500"></textarea>
                        <div class="d-flex justify-content-between align-items-start mt-1">
                            <div class="form-text">Please provide a brief description of the game and why you'd like it added. (This is to help us understand what users may want, and helps us determine a suitable description for the game).</div>
                            <div class="char-counter ms-2" style="font-size: 0.85rem; white-space: nowrap; transition: color 0.3s ease; color: #2ecc71;">
                                <span id="charCount">500</span> characters remaining
                                </div>
                                </div>
        </div>

                    <div class="mb-4">
                        <label for="additionalInfo" class="form-label">Additional Information</label>
                        <textarea class="form-control" id="additionalInfo" name="additionalInfo" rows="3"></textarea>
                        <div class="form-text">Any other relevant information (e.g., developer, publisher, links to the game store page, etc.)</div>
</div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-outline-light">
                            <i class="bi bi-send me-2"></i>Submit Request
                        </button>
      </div>
                </form>
      </div>
    </div>
  </div>
</div>

<script>
// Image preview functionality
document.getElementById('gameImage').addEventListener('change', function(e) {
    const preview = document.getElementById('imagePreview');
    const file = e.target.files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(file);
            } else {
        preview.style.display = 'none';
            }
});

// Form submission handling
document.getElementById('gameRequestForm').addEventListener('submit', function(e) {
    e.preventDefault();

    // Add loading state to button
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

    // Submit form
    const formData = new FormData(this);
    fetch(this.action, {
            method: 'POST',
        body: formData
        })
    .then(async response => {
        const text = await response.text(); // Get raw response text
        console.log('Raw response:', text);
        
        try {
            return JSON.parse(text); // Try to parse as JSON
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Response text:', text);
            throw new Error('Invalid server response');
        }
    })
            .then(data => {
        console.log('Parsed data:', data);
                if (data.success) {
            showToast('Game request submitted successfully!', 'success');
            this.reset();
            document.getElementById('imagePreview').style.display = 'none';
        } else {
            throw new Error(data.message || 'Failed to submit game request. Please try again.');
                }
            })
            .catch(error => {
        console.error('Error details:', error);
        showToast('An error occurred while submitting the request: ' + error.message, 'error');
    })
    .finally(() => {
        // Restore button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Character counter for description with color changes
document.getElementById('description').addEventListener('input', function() {
    const maxLength = 500; // Set max length to 500
    const currentLength = this.value.length;
    const remaining = maxLength - currentLength;
    const counter = document.getElementById('charCount');
    const counterDiv = counter.parentElement;
    
    // Update the count
    counter.textContent = remaining;
    
    // Update color based on remaining characters
    if (remaining > 200) {
        counterDiv.style.color = '#2ecc71'; // Green
    } else if (remaining > 50) {
        counterDiv.style.color = '#f39c12'; // Orange
                } else {
        counterDiv.style.color = '#e74c3c'; // Red
    }
});
</script>

<?php include '../includes/footer.php'; ?>
