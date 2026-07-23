<?php
// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../includes/db.php';

// Authentication check: Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Fetch user's wishlist with game details, ordered by most recently added
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

// Fetch user's profile information
$userQuery = $conn->prepare("SELECT username, profile_image FROM users WHERE id = ?");
$userQuery->bind_param("i", $userId);
$userQuery->execute();
$userResult = $userQuery->get_result();
$user = $userResult->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta tags and title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist | GameTracker</title>
    
    <!-- External CSS dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
    
    <!-- Custom styles for wishlist page -->
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

        .hero-section {
            position: relative;
            padding: 6rem 0 4rem;
            background: linear-gradient(to right, rgba(21, 21, 30, 0.9), rgba(21, 21, 30, 0.7));
            border-bottom: 1px solid rgba(127, 0, 255, 0.3);
            margin-bottom: 2rem;
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

        .coming-soon {
            color: #f1c40f;
            font-weight: 600;
        }

        .out-now {
            color: #2ecc71;
            font-weight: 600;
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

        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>

<?php include '../includes/nav.php'; ?>

<!-- Hero Section: Page header with title and description -->
<section class="hero-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <h1 class="display-4 mb-3" style="font-family: 'Orbitron', sans-serif;">
                    <span style="color: var(--primary-color);">My</span> Wishlist
                </h1>
                <p class="lead mb-4">Track the games you're interested in playing. Add games to your wishlist to keep them organized and easily accessible.</p>
            </div>
        </div>
    </div>
</section>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <h2 class="section-title">Wishlisted Games</h2>
            <?php if ($wishlistResult->num_rows > 0): ?>
                <!-- Display wishlist items in a grid -->
                <div class="row g-4">
                    <?php while($game = $wishlistResult->fetch_assoc()): ?>
                        <div class="col-md-4">
                            <a href="games/game-detail.php?id=<?= $game['id'] ?>" class="text-decoration-none">
                                <div class="game-card">
                                    <!-- Game image with wishlist button -->
                                    <div class="card-img-container">
                                        <?php if (!empty($game['image_url'])): ?>
                                            <img src="<?= htmlspecialchars($game['image_url']) ?>" alt="<?= htmlspecialchars($game['title']) ?>">
                                        <?php else: ?>
                                            <img src="assets/default-game.jpg" alt="Default game image">
                                        <?php endif; ?>
                                        <button class="wishlist-icon active" data-game-id="<?= $game['id'] ?>" title="Remove from Wishlist">
                                            <i class="bi bi-cart"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Game details -->
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($game['title']) ?></h5>
                                        
                                        <!-- Release date information -->
                                        <div class="game-info">
                                            <strong>Release:</strong>
                                            <?php
                                                $now = new DateTime();
                                                $releaseClass = "";
                                                
                                                // Handle different release date scenarios
                                                if (!empty($game['is_tba']) && $game['is_tba']) {
                                                    echo "TBA" . (!empty($game['tba_year']) ? " " . htmlspecialchars($game['tba_year']) : "");
                                                    $releaseClass = "coming-soon";
                                                } elseif (!empty($game['release_date'])) {
                                                    $releaseDate = new DateTime($game['release_date']);
                                                    echo date('F j, Y', strtotime($game['release_date']));
                                                    
                                                    // Set appropriate class based on release date
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
                                                if (!empty($game['platforms'])) {
                                                    $platforms = explode(', ', $game['platforms']);
                                                    foreach ($platforms as $plat) {
                                                        echo "<span class='platform-badge'>" . htmlspecialchars($plat) . "</span>";
                                                    }
                                                }
                                            ?>
                                        </div>
                                        
                                        <!-- Genre badges -->
                                        <div class="mt-2">
                                            <?php
                                                if (!empty($game['genre'])) {
                                                    $genres = explode(', ', $game['genre']);
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
            <?php else: ?>
                <!-- Empty wishlist message -->
                <div class="text-center py-5">
                    <i class="bi bi-cart" style="font-size: 3rem; color: var(--primary-color); opacity: 0.6;"></i>
                    <h3 class="mt-3">Your wishlist is empty</h3>
                    <p>Add games to your wishlist to see them here</p>
                    <a href="/explore.php" class="btn btn-primary mt-3">Browse Games</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<!-- Bootstrap JavaScript -->

<!-- Custom JavaScript for wishlist functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle wishlist icon click events
    document.querySelectorAll('.wishlist-icon').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const gameId = this.getAttribute('data-game-id');
            const isActive = this.classList.contains('active');
            
            // Send request to update wishlist
            fetch('/api/wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `game_id=${gameId}&action=${isActive ? 'remove' : 'add'}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (isActive) {
                        // Reload page after removing from wishlist
                        window.location.reload();
                    } else {
                        this.classList.toggle('active');
                    }
                }
            });
        });
    });

    // Tab switching functionality
    const tabs = document.querySelectorAll('.nav-link[data-tab]');
    const tabContents = document.querySelectorAll('.tab-content');

    function showTab(tabId) {
        // Hide all tab contents
        tabContents.forEach(content => {
            content.classList.remove('active');
            content.style.display = 'none';
        });
        // Remove active class from all tabs
        tabs.forEach(tab => tab.classList.remove('active'));

        // Show selected tab and content
        const tab = document.querySelector('.nav-link[data-tab="' + tabId + '"]');
        const content = document.getElementById(tabId + '-content');
        if (tab) tab.classList.add('active');
        if (content) {
            content.classList.add('active');
            content.style.display = 'block';
        }
    }

    // Add click event listeners to tabs
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const tabId = this.getAttribute('data-tab');
            showTab(tabId);
        });
    });

    // Show default tab on page load
    showTab('profile');
});
</script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?> 