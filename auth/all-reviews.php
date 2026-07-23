<?php
require_once '../includes/db.php';
session_start();

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Get all reviews with game information
$reviewsQuery = $conn->prepare("
    SELECT r.*, g.title as game_title, g.image_url, g.portrait_image_url, g.id as game_id
    FROM reviews r 
    JOIN games g ON r.game_id = g.id 
    WHERE r.user_id = ? 
    ORDER BY r.created_at DESC
");
$reviewsQuery->bind_param("i", $userId);
$reviewsQuery->execute();
$reviews = $reviewsQuery->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Reviews - GameTracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/styles.css">
    <?php
    // Get random portrait images for hero section background
    $heroImages = [];
    $bgQuery = $conn->query("SELECT portrait_image_url FROM games WHERE portrait_image_url IS NOT NULL AND portrait_image_url != '' ORDER BY RAND() LIMIT 6");
    while ($row = $bgQuery->fetch_assoc()) {
        $heroImages[] = $row['portrait_image_url'];
    }
    ?>

    <style>
        :root {
            --primary-color: #b200ff;
            --primary-hover: #9933ff;
            --dark-bg: #15151e;
            --card-bg: #1e1e2f;
            --text-muted: #a8a8b3;
        }

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
            height: 500px;
            object-fit: cover;
            transform: rotate(-3deg);
            border-radius: 12px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.4);
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .hero-title .highlight {
            color: var(--primary-color);
        }

        .hero-subtitle {
            color: var(--text-muted);
            font-size: 1rem;
            margin: 0;
        }

        .review-card {
            background: var(--card-bg);
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
        }

        .review-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0 20px rgba(127, 0, 255, 0.3);
            border-color: rgba(127, 0, 255, 0.3);
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
            padding: 1.5rem;
            background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
            color: white;
        }

        .review-game-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .review-rating {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .review-rating i {
            color: var(--primary-color);
        }

        .review-content {
            padding: 1.5rem;
        }

        .review-title {
            font-size: 1.1rem;
            color: white;
            margin-bottom: 1rem;
        }

        .review-text {
            color: #a8a8b3;
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .review-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid rgba(127, 0, 255, 0.1);
        }

        .review-date {
            color: #a8a8b3;
            font-size: 0.9rem;
        }

        .btn-view-game {
            background: rgba(127, 0, 255, 0.1);
            border: 1px solid rgba(127, 0, 255, 0.2);
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-view-game:hover {
            background: rgba(127, 0, 255, 0.2);
            border-color: var(--primary-color);
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 4px 12px rgba(127, 0, 255, 0.2);
        }

        .btn-back {
            background: transparent;
            border: 1px solid rgba(127, 0, 255, 0.3);
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }

        .btn-back:hover {
            background: rgba(127, 0, 255, 0.1);
            transform: translateX(-5px);
            color: white;
        }

        @media (max-width: 768px) {
            .hero-section {
                padding: 4rem 0 2rem;
                text-align: center;
            }

            .hero-title {
                font-size: 2rem;
            }

            .hero-image-stack img {
                height: 400px;
            }
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
        <div class="container position-relative">
            <div class="hero-content">
                <h1 class="hero-title">
                    <span class="highlight">Your</span> Reviews
                </h1>
                <p class="hero-subtitle">Your complete gaming journey and thoughts</p>
            </div>
        </div>
    </section>

    <div class="container">
        <a href="profile.php" class="btn-back">
            <i class="bi bi-arrow-left"></i> Back to Profile
        </a>

        <div class="row">
            <?php if ($reviews->num_rows > 0): ?>
                <?php while ($review = $reviews->fetch_assoc()): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
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
                                        <span class="ms-2"><?= number_format($review['rating'], 1) ?></span>
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
                                    <a href="../games/game-detail.php?id=<?= $review['game_id'] ?>" class="btn-view-game">
                                        View Game <i class="bi bi-arrow-right-short"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center p-5 bg-dark rounded">
                        <i class="bi bi-pencil-square" style="font-size: 3rem; color: var(--primary-color);"></i>
                        <h4 class="mt-3">No reviews yet</h4>
                        <p class="text-muted">Share your thoughts about the games you've played.</p>
                        <a href="../explore.php" class="btn btn-primary mt-2">Write a Review</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html> 