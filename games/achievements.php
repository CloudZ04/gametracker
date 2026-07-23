<?php
require_once '../includes/db.php';
require_once '../includes/steam_achievements.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: ../explore.php');
    exit();
}

$gameId = intval($_GET['id']);
$userId = $_SESSION['user_id'];

// Get game details with both images
$stmt = $conn->prepare("SELECT *, COALESCE(portrait_image_url, image_url) as hero_image FROM games WHERE id = ?");
$stmt->bind_param("i", $gameId);
$stmt->execute();
$game = $stmt->get_result()->fetch_assoc();

if (!$game) {
    header('Location: ../explore.php');
    exit();
}

// Get user's Steam ID
$stmt = $conn->prepare("SELECT steam_id FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$achievements = null;
if ($user['steam_id'] && !empty($game['steam_app_id'])) {
    $achievements = getSteamAchievements($user['steam_id'], $game['steam_app_id']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($game['title']) ?> - Achievements</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/styles.css">
    <style>
        /* Hero section styles */
        .hero-section {
            position: relative;
            padding: 4rem 0;
            margin-bottom: 2rem;
            color: #fff;
            overflow: hidden;
            min-height: 300px;
            display: flex;
            align-items: center;
            margin-top: -60px; /* Offset the navbar spacer */
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('<?= htmlspecialchars($game['hero_image'] ?? '../images/logo.png') ?>');
            background-size: cover;
            background-position: center;
            filter: blur(8px) brightness(0.3);
            z-index: -1;
            transform: scale(1.1); /* Prevent blur edges */
        }

        .hero-section::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to right, rgba(21, 21, 30, 0.95), rgba(21, 21, 30, 0.8));
            z-index: -1;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #b200ff;
        }

        .hero-subtitle {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .achievement-progress-bar {
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 1rem;
        }

        .achievement-progress-bar .progress-bar {
            height: 100%;
            background: #b200ff;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        /* Achievement card styles */
        .achievement-card {
            background: var(--card-bg);
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }

        .achievement-card.locked {
            opacity: 0.6;
            filter: grayscale(100%);
        }

        .achievement-card:hover {
            transform: translateY(-2px);
            border-color: rgba(127, 0, 255, 0.3);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .achievement-icon {
            width: 48px;
            height: 48px;
            border-radius: 6px;
            margin-right: 1rem;
            object-fit: cover;
        }

        .achievement-info {
            flex: 1;
            min-width: 0; /* Helps with text truncation */
        }

        .achievement-name {
            font-family: 'Orbitron', sans-serif;
            color: white;
            margin-bottom: 0.25rem;
            font-size: 1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .achievement-desc {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .achievement-date {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .achievement-status {
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 1rem;
            white-space: nowrap;
        }

        .achievement-status.locked {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .achievement-status.unlocked {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .achievement-stats {
            background: var(--card-bg);
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        /* Add spacing for the header section */
        .achievement-header {
            padding-top: 2rem;
            margin-bottom: 2rem;
        }

        .achievement-header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: white;
        }

        .achievement-header .stats {
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .hero-section {
            position: relative;
            padding: 6rem 0 4rem;
            background: linear-gradient(to right, rgba(21, 21, 30, 0.8), rgba(21, 21, 30, 0.6)), 
                        url('<?= !empty($game['image_url']) ? htmlspecialchars($game['image_url']) : "https://via.placeholder.com/1920x600" ?>') center/cover no-repeat;
            border-bottom: 1px solid rgba(127, 0, 255, 0.3);
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }

        .game-header h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        /* Connect Steam prompt */
        .connect-steam {
            text-align: center;
            padding: 2rem;
            background: var(--card-bg);
            border-radius: 8px;
            border: 1px solid rgba(127, 0, 255, 0.1);
        }

        .connect-steam h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .btn-steam {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .btn-steam:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title"><?= htmlspecialchars($game['title']) ?></h1>
                <?php if ($achievements): ?>
                    <?php
                    $unlockedCount = array_reduce($achievements, function($carry, $item) {
                        return $carry + ($item['achieved'] ? 1 : 0);
                    }, 0);
                    $totalCount = count($achievements);
                    $percentage = $totalCount > 0 ? ($unlockedCount / $totalCount) * 100 : 0;
                    ?>
                    <div class="hero-subtitle">
                        <?= $unlockedCount ?> of <?= $totalCount ?> Achievements (<?= number_format($percentage, 1) ?>% Complete)
                    </div>
                    <div class="achievement-progress-bar">
                        <div class="progress-bar" style="width: <?= $percentage ?>%"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="container">
        <?php if (!$user['steam_id']): ?>
            <div class="connect-steam">
                <h3>Connect Steam to View Achievements</h3>
                <p class="text-muted mb-4">Link your Steam account to track achievements for this game.</p>
                <a href="../auth/steam_connect.php" class="btn btn-steam">
                    <i class="bi bi-steam me-2"></i>Connect Steam Account
                </a>
            </div>
        <?php elseif (!$game['steam_app_id']): ?>
            <div class="alert alert-info">
                This game is not linked to Steam. Achievements are not available.
            </div>
        <?php elseif ($achievements === null): ?>
            <div class="alert alert-info">
                Unable to fetch achievements. You might not own this game on Steam or your profile might be private.
            </div>
        <?php else: ?>

            <div class="row">
                <?php foreach ($achievements as $achievement): ?>
                    <div class="col-12">
                        <div class="achievement-card <?= !$achievement['achieved'] ? 'locked' : '' ?>">
                            <img src="<?= htmlspecialchars($achievement['icon']) ?>" alt="Achievement Icon" class="achievement-icon">
                            <div class="achievement-info">
                                <h5 class="achievement-name"><?= htmlspecialchars($achievement['displayName']) ?></h5>
                                <p class="achievement-desc"><?= htmlspecialchars($achievement['description']) ?></p>
                                <?php if ($achievement['achieved']): ?>
                                    <span class="achievement-date">
                                        Unlocked <?= date('M j, Y', strtotime($achievement['unlock_time'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span class="achievement-status <?= $achievement['achieved'] ? 'unlocked' : 'locked' ?>">
                                    <?= $achievement['achieved'] ? 'Unlocked' : 'Locked' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html> 