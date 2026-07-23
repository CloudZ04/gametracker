<?php
require_once '../includes/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: login.php');
    exit();
}

// Fetch user's game collections with ratings
$stmt = $conn->prepare("
    SELECT ugs.user_id, ugs.game_id, ugs.status, ugs.updated_at, g.title, g.platforms as platform, r.rating
    FROM user_game_status ugs 
    LEFT JOIN games g ON ugs.game_id = g.id 
    LEFT JOIN reviews r ON ugs.user_id = r.user_id AND ugs.game_id = r.game_id
    WHERE ugs.user_id = ? 
    ORDER BY ugs.status, g.title
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$collections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Debug: Check if collections are being fetched
if (empty($collections)) {
    echo "<!-- Debug: No collections found for user ID: " . $user_id . " -->";
}

// Fetch user's reviews
$stmt = $conn->prepare("
    SELECT r.*, g.title, g.image_url, g.portrait_image_url 
    FROM reviews r 
    LEFT JOIN games g ON r.game_id = g.id 
    WHERE r.user_id = ? 
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch user's achievements
$stmt = $conn->prepare("
    SELECT sa.*, g.title 
    FROM steam_achievements sa 
    LEFT JOIN games g ON sa.game_id = g.id 
    WHERE sa.user_id = ? AND sa.unlocked = 1
    ORDER BY sa.unlock_time DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$achievements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch user's friends
$stmt = $conn->prepare("
    SELECT u.username, u.name, ur.created_at as friendship_date
    FROM user_relationships ur 
    LEFT JOIN users u ON (ur.follower_id = ? AND ur.following_id = u.id) OR (ur.following_id = ? AND ur.follower_id = u.id)
    WHERE (ur.follower_id = ? OR ur.following_id = ?) AND ur.status = 'friends'
    ORDER BY ur.created_at DESC
");
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$friends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();



// Set headers for file download
$filename = "gametracker_data_" . $user['username'] . "_" . date('Y-m-d_H-i-s') . ".html";
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Generate the HTML export
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameTracker Data Export - <?= htmlspecialchars($user['username']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #b200ff;
            --card-bg: #1e1e2f;
            --text-color: #ffffff;
            --border-color: rgba(127, 0, 255, 0.3);
            --hover-bg: rgba(127, 0, 255, 0.1);
        }

        body {
            font-family: 'Exo 2', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh;
        }
        
        .header {
            background: rgba(30, 30, 47, 0.8);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
            border: 1px solid var(--border-color);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .logo {
            width: auto;
            height: 50px;
            filter: drop-shadow(0 0 10px rgba(178, 0, 255, 0.5));
        }
        
        .header h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 600;
            font-family: 'Orbitron', sans-serif;
            color: var(--primary-color);
        }
        
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        
        .section {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
        }
        
        .section h2 {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-family: 'Orbitron', sans-serif;
            font-size: 1.8rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            background: rgba(30, 30, 47, 0.5);
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .info-item:hover {
            background: var(--hover-bg);
            transform: translateX(5px);
        }
        
        .info-item strong {
            color: var(--primary-color);
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background: var(--card-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
        }
        
        th {
            background: var(--primary-color);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-family: 'Orbitron', sans-serif;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
        }
        
        tr:hover {
            background-color: var(--hover-bg);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-playing { background: #28a745; color: white; }
        .status-completed { background: #007bff; color: white; }
        .status-beaten { background: #ffc107; color: black; }
        .status-shelved { background: #6c757d; color: white; }
        .status-abandoned { background: #dc3545; color: white; }
        .status-want { background: #17a2b8; color: white; }
        
        .rating {
            color: #ffc107;
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .achievement-rare {
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            color: #333;
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 8px;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            color: rgba(255, 255, 255, 0.6);
            border-top: 1px solid var(--border-color);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .stats {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color), #8a00cc);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(178, 0, 255, 0.3);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            display: block;
            font-family: 'Orbitron', sans-serif;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
            margin-top: 5px;
        }
        
        .collection-group {
            margin-bottom: 30px;
        }
        
        .collection-subtitle {
            color: var(--primary-color);
            font-family: 'Orbitron', sans-serif;
            font-size: 1.4rem;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border-color);
        }
        
        @media print {
            body { 
                background: white; 
                color: #333;
            }
            .section { 
                box-shadow: none; 
                border: 1px solid #ddd; 
                background: white;
            }
            .header {
                background: #f8f9fa;
                color: #333;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-container">
            <img src="data:image/png;base64,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" alt="GameTracker.gg Logo" class="logo">
            <h1>GameTracker Data Export</h1>
        </div>
        <p>Personal gaming data for <?= htmlspecialchars($user['username']) ?> - Exported on <?= date('F j, Y \a\t g:i A') ?></p>
    </div>

    <!-- Profile Information -->
    <div class="section">
        <h2>👤 Profile Information</h2>
        <div class="info-grid">
            <div class="info-item">
                <strong>Username</strong>
                <?= htmlspecialchars($user['username']) ?>
            </div>
            <div class="info-item">
                <strong>Display Name</strong>
                <?= htmlspecialchars($user['name'] ?: 'Not set') ?>
            </div>
            <div class="info-item">
                <strong>Email</strong>
                <?= htmlspecialchars($user['email']) ?>
            </div>
            <div class="info-item">
                <strong>Member Since</strong>
                <?= date('F j, Y', strtotime($user['created_at'])) ?>
            </div>
            <div class="info-item">
                <strong>Last Login</strong>
                <?= isset($user['last_login']) && $user['last_login'] ? date('F j, Y \a\t g:i A', strtotime($user['last_login'])) : 'Never' ?>
            </div>
            <div class="info-item">
                <strong>About</strong>
                <?= htmlspecialchars($user['about'] ?: 'No bio added') ?>
            </div>
        </div>
    </div>

    <!-- Friends -->
    <div class="section">
        <h2>👥 Friends</h2>
        <?php if (!empty($friends)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Display Name</th>
                        <th>Friends Since</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($friends as $friend): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($friend['username']) ?></strong></td>
                            <td><?= htmlspecialchars($friend['name'] ?: 'No display name') ?></td>
                            <td><?= date('M j, Y', strtotime($friend['friendship_date'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No friends added yet.</p>
        <?php endif; ?>
    </div>

    <!-- Statistics -->
    <div class="section">
        <h2>📊 Gaming Statistics</h2>
        <div class="stats">
            <div class="stat-card">
                <span class="stat-number"><?= count($collections) ?></span>
                <span class="stat-label">Total Games</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= count(array_filter($collections, function($c) { return $c['status'] === 'Completed'; })) ?></span>
                <span class="stat-label">Completed</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= count(array_filter($collections, function($c) { return $c['status'] === 'Playing'; })) ?></span>
                <span class="stat-label">Currently Playing</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= count(array_filter($collections, function($c) { return $c['status'] === 'Want to Play'; })) ?></span>
                <span class="stat-label">Want to Play</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= count(array_filter($collections, function($c) { return $c['status'] === 'Beaten'; })) ?></span>
                <span class="stat-label">Beaten</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= count(array_filter($collections, function($c) { return $c['status'] === 'Shelved'; })) ?></span>
                <span class="stat-label">Shelved</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= count(array_filter($collections, function($c) { return $c['status'] === 'Abandoned'; })) ?></span>
                <span class="stat-label">Abandoned</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= count($reviews) ?></span>
                <span class="stat-label">Reviews Written</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= count($achievements) ?></span>
                <span class="stat-label">Achievements Unlocked</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= count($friends) ?></span>
                <span class="stat-label">Friends</span>
            </div>
        </div>
    </div>

    <!-- Game Collections -->
    <div class="section">
        <h2>🎮 Game Collections</h2>
        <?php 
        // Debug: Show collection count
        echo "<!-- Debug: Found " . count($collections) . " collections -->";
        
        // Debug: Show first few collections to see structure
        if (!empty($collections)) {
            echo "<!-- Debug: First collection: " . json_encode(array_slice($collections, 0, 1)) . " -->";
        }
        
        if (!empty($collections)): 
        ?>
            <?php
            // Group collections by status
            $grouped_collections = [];
            foreach ($collections as $game) {
                $status = $game['status'];
                if (!isset($grouped_collections[$status])) {
                    $grouped_collections[$status] = [];
                }
                $grouped_collections[$status][] = $game;
            }
            
            // Debug: Show what statuses were found
            echo "<!-- Debug: Found statuses: " . implode(', ', array_keys($grouped_collections)) . " -->";
            echo "<!-- Debug: Grouped collections count: " . count($grouped_collections) . " -->";
            
            // Define status order and labels
            $status_order = ['Playing', 'Completed', 'Beaten', 'Want to Play', 'Shelved', 'Abandoned'];
            $status_labels = [
                'Playing' => 'Currently Playing',
                'Completed' => 'Completed',
                'Beaten' => 'Beaten',
                'Want to Play' => 'Want to Play',
                'Shelved' => 'Shelved',
                'Abandoned' => 'Abandoned'
            ];
            ?>
            
            <?php foreach ($status_order as $status): ?>
                <?php 
                echo "<!-- Debug: Checking status: $status -->";
                echo "<!-- Debug: isset(grouped_collections[$status]): " . (isset($grouped_collections[$status]) ? 'true' : 'false') . " -->";
                if (isset($grouped_collections[$status])) {
                    echo "<!-- Debug: count(grouped_collections[$status]): " . count($grouped_collections[$status]) . " -->";
                }
                ?>
                <?php if (isset($grouped_collections[$status]) && !empty($grouped_collections[$status])): ?>
                    <div class="collection-group">
                        <h3 class="collection-subtitle"><?= $status_labels[$status] ?> (<?= count($grouped_collections[$status]) ?>)</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Game</th>
                                    <th>Platform</th>
                                    <th>Status</th>
                                    <th>Rating</th>
                                    <th>Date Updated</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grouped_collections[$status] as $game): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($game['title']) ?></strong></td>
                                        <td><?= htmlspecialchars($game['platform'] ?: 'Unknown') ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $game['status'] ?>">
                                                <?= ucfirst($game['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (isset($game['rating']) && $game['rating']): ?>
                                                <span class="rating"><?= str_repeat('★', $game['rating']) . str_repeat('☆', 5 - $game['rating']) ?></span>
                                            <?php else: ?>
                                                Not rated
                                            <?php endif; ?>
                                        </td>
                                        <td><?= isset($game['updated_at']) ? date('M j, Y', strtotime($game['updated_at'])) : 'Unknown' ?></td>
                                        <td>-</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No games in your collection yet.</p>
        <?php endif; ?>
    </div>

    <!-- Reviews -->
    <div class="section">
        <h2>⭐ Reviews</h2>
        <?php if (!empty($reviews)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Game</th>
                        <th>Rating</th>
                        <th>Review</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $review): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($review['title']) ?></strong></td>
                            <td>
                                <span class="rating"><?= str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($review['review_text'] ?: 'No review text') ?></td>
                            <td><?= date('M j, Y', strtotime($review['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No reviews written yet.</p>
        <?php endif; ?>
    </div>

    <!-- Achievements -->
    <div class="section">
        <h2>🏆 Achievements</h2>
        <?php if (!empty($achievements)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Game</th>
                        <th>Achievement</th>
                        <th>Description</th>
                        <th>Rarity</th>
                        <th>Unlocked</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($achievements as $achievement): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($achievement['title']) ?></strong></td>
                            <td><?= htmlspecialchars($achievement['achievement_name']) ?></td>
                            <td><?= htmlspecialchars($achievement['achievement_description']) ?></td>
                            <td>
                                <?php if (isset($achievement['rarity']) && $achievement['rarity'] < 10): ?>
                                    <span class="achievement-rare">Rare (<?= $achievement['rarity'] ?>%)</span>
                                <?php else: ?>
                                    Common
                                <?php endif; ?>
                            </td>
                            <td><?= date('M j, Y', strtotime($achievement['unlock_time'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No achievements unlocked yet.</p>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>This data export was generated on <?= date('F j, Y \a\t g:i A') ?> for <?= htmlspecialchars($user['username']) ?></p>
        <p>© <?= date('Y') ?> GameTracker.gg - Your personal gaming companion</p>
    </div>
</body>
</html> 