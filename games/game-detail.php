<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db.php';

// Validate game ID parameter
if (!isset($_GET['id'])) {
    die('Game ID not specified.');
}

// Fetch game details from database
$game_id = intval($_GET['id']);
$sql = "SELECT * FROM games WHERE id = $game_id";
$result = $conn->query($sql);

if ($result->num_rows != 1) {
    die('Game not found.');
}

$game = $result->fetch_assoc();

// Helper function to write debug logs
function writeLog($message) {
    $logFile = __DIR__ . '/rawg_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Function to get IGDB API access token
function getIGDBAccessToken() {
    $clientId = 'avrcrn7yp1lyhkkve1et2ha4rwvhzo';
    $clientSecret = '4rsurue3p8kv0l0kua3orx9y6oxjwf';
    
    $url = 'https://id.twitch.tv/oauth2/token';
    $data = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'grant_type' => 'client_credentials'
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        writeLog("Failed to get IGDB access token");
        return null;
    }
    
    $tokenData = json_decode($response, true);
    return $tokenData['access_token'] ?? null;
}

// Function to search game on IGDB API
function searchIGDBGame($gameTitle, $accessToken) {
    $url = 'https://api.igdb.com/v4/games';
    $data = "search \"{$gameTitle}\"; fields id,name,websites.*; limit 1;";
    
    $options = [
        'http' => [
            'header' => [
                "Client-ID: avrcrn7yp1lyhkkve1et2ha4rwvhzo",
                "Authorization: Bearer {$accessToken}",
                "Content-Type: application/json"
            ],
            'method' => 'POST',
            'content' => $data
        ]
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        writeLog("Failed to search game on IGDB");
        return null;
    }
    
    $games = json_decode($response, true);
    return !empty($games) ? $games[0] : null;
}

// Function to fetch store links from RAWG API
function getStoreLinks($gameTitle) {
    $storeLinks = [];
    $officialWebsite = null;
    
    // Get RAWG store links
    $apiKey = '58aed2d9aedd4274ab81d91356e775f2';
    $searchUrl = "https://api.rawg.io/api/games?key={$apiKey}&search=" . urlencode($gameTitle) . "&page_size=1";
    
    writeLog("Searching for game on RAWG: " . $gameTitle);
    writeLog("Search URL: " . $searchUrl);
    
    $response = file_get_contents($searchUrl);
    if ($response === false) {
        writeLog("Failed to fetch game search from RAWG");
    } else {
        $data = json_decode($response, true);
        if (!empty($data['results'])) {
            $gameId = $data['results'][0]['id'];
            writeLog("Found game ID on RAWG: " . $gameId);
            
            // Get game details to fetch official website
            $detailsUrl = "https://api.rawg.io/api/games/{$gameId}?key={$apiKey}";
            $detailsResponse = file_get_contents($detailsUrl);
            if ($detailsResponse !== false) {
                $detailsData = json_decode($detailsResponse, true);
                if (!empty($detailsData['website'])) {
                    $officialWebsite = $detailsData['website'];
                    writeLog("Found official website: " . $officialWebsite);
                }
            }
            
            // Get store links
            $storesUrl = "https://api.rawg.io/api/games/{$gameId}/stores?key={$apiKey}";
            $storesResponse = file_get_contents($storesUrl);
            
            if ($storesResponse !== false) {
                $storesData = json_decode($storesResponse, true);
                
                // Store ID mapping for RAWG - only major platforms
                $storeIdMap = [
                    1 => 'Steam',
                    2 => 'Xbox Store',
                    3 => 'PlayStation Store',
                    6 => 'Nintendo Store',
                    11 => 'Epic Games'
                ];
                
                if (!empty($storesData['results'])) {
                    foreach ($storesData['results'] as $store) {
                        if (!empty($store['store_id']) && !empty($store['url']) && isset($storeIdMap[$store['store_id']])) {
                            $storeName = $storeIdMap[$store['store_id']];
                            $storeLinks[$storeName] = $store['url'];
                            writeLog("Added RAWG store: " . $storeName);
                        }
                    }
                }
            }
        }
    }
    
    writeLog("Final store links array: " . print_r($storeLinks, true));
    return ['stores' => $storeLinks, 'website' => $officialWebsite];
}

// Fetch store links and official website
$links = getStoreLinks($game['title']);
$storeLinks = $links['stores'];
$officialWebsite = $links['website'];
writeLog("Store links retrieved for game: " . $game['title']);

// Get user's game status and wishlist status
$userStatus = '';
$inWishlist = false;

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    // Get user's game status
    $statusQuery = $conn->prepare("SELECT status FROM user_game_status WHERE user_id = ? AND game_id = ?");
    $statusQuery->bind_param("ii", $userId, $game_id);
    $statusQuery->execute();
    $statusResult = $statusQuery->get_result();
    
    if ($row = $statusResult->fetch_assoc()) {
        $userStatus = $row['status'];
    }
    
    $statusQuery->close();
    
    // Check if game is in user's wishlist
    $wishlistQuery = $conn->prepare("SELECT 1 FROM user_wishlist WHERE user_id = ? AND game_id = ?");
    $wishlistQuery->bind_param("ii", $userId, $game_id);
    $wishlistQuery->execute();
    $inWishlist = $wishlistQuery->get_result()->num_rows > 0;
    $wishlistQuery->close();
}

function renderStars($rating) {
    $output = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $output .= '<i class="bi bi-star-fill"></i>';
        } elseif ($rating >= $i - 0.5) {
            $output .= '<i class="bi bi-star-half"></i>';
        } else {
            $output .= '<i class="bi bi-star"></i>';
        }
    }
    return $output;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($game['title']) ?> - Game Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        .game-header {
            position: relative;
            padding: 6rem 0 4rem;
            background: linear-gradient(to right, rgba(21, 21, 30, 0.8), rgba(21, 21, 30, 0.6)), 
                        url('<?= !empty($game['image_url']) ? htmlspecialchars($game['image_url']) : "https://via.placeholder.com/1920x600" ?>') center/cover no-repeat;
            border-bottom: 1px solid rgba(127, 0, 255, 0.3);
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }

        .game-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 3rem;
            margin-bottom: 1rem;
            text-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            animation: fadeInUp 0.8s ease forwards;
        }

        .game-container {
            display: flex;
            flex-direction: column;
            gap: 2rem;
            padding: 0 2rem 4rem;
            max-width: 1400px;
            margin: auto;
        }

        .game-content-wrapper {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .game-content {
            flex: 3;
            min-width: 280px;
            animation: fadeIn 0.8s ease forwards;
        }

        .game-sidebar {
            flex: 1;
            min-width: 280px;
            background-color: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(127, 0, 255, 0.1);
            height: fit-content;
            animation: fadeInRight 0.8s ease forwards;
            transition: all 0.3s ease;
        }

        .game-sidebar:hover {
            border-color: rgba(127, 0, 255, 0.3);
            box-shadow: var(--glow-shadow);
        }

        .game-sidebar img {
            width: 100%;
            height: auto;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .game-sidebar img:hover {
            transform: scale(1.02);
            box-shadow: var(--glow-shadow);
        }

        .game-sidebar h5 {
            font-family: 'Orbitron', sans-serif;
            border-bottom: 2px solid rgba(127, 0, 255, 0.3);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            color: var(--text-light);
        }

        .game-sidebar p {
            font-size: 0.95rem;
            margin-bottom: 1.2rem;
            color: var(--text-muted);
        }

        .game-sidebar p strong {
            display: block;
            color: var(--text-light);
            margin-bottom: 0.3rem;
        }

        .description-markdown {
            background-color: var(--card-bg);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(127, 0, 255, 0.1);
            font-size: 1rem;
            line-height: 1.7;
            color: var(--text-light);
            transition: all 0.3s ease;
        }

        .description-markdown:hover {
            border-color: rgba(127, 0, 255, 0.3);
            box-shadow: var(--glow-shadow);
        }

        /* Markdown Typography */
        .description-markdown h1, 
        .description-markdown h2, 
        .description-markdown h3,
        .description-markdown h4,
        .description-markdown h5,
        .description-markdown h6 {
            font-family: 'Orbitron', sans-serif;
            margin-top: 2rem;
            margin-bottom: 1rem;
            color: var(--text-light);
            line-height: 1.4;
        }

        .description-markdown h1 { font-size: 2.2rem; }
        .description-markdown h2 { font-size: 1.8rem; }
        .description-markdown h3 { font-size: 1.5rem; }
        .description-markdown h4 { font-size: 1.3rem; }
        .description-markdown h5 { font-size: 1.1rem; }
        .description-markdown h6 { font-size: 1rem; }

        .description-markdown h1::after,
        .description-markdown h2::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background: var(--primary-color);
            margin-top: 0.5rem;
            border-radius: 2px;
        }

        /* Paragraphs and Lists */
        .description-markdown p {
            margin-bottom: 1.2rem;
            color: var(--text-muted);
        }

        .description-markdown ul,
        .description-markdown ol {
            margin-bottom: 1.2rem;
            padding-left: 1.5rem;
            color: var(--text-muted);
        }

        .description-markdown li {
            margin-bottom: 0.5rem;
        }

        /* Links */
        .description-markdown a {
            color: var(--primary-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .description-markdown a:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        /* Blockquotes */
        .description-markdown blockquote {
            border-left: 4px solid var(--primary-color);
            margin: 1.5rem 0;
            padding: 1rem 1.5rem;
            background: rgba(127, 0, 255, 0.1);
            border-radius: 0 8px 8px 0;
            font-style: italic;
            color: var(--text-light);
        }

        .description-markdown blockquote p:last-child {
            margin-bottom: 0;
        }

        /* Code Blocks */
        .description-markdown pre,
        .description-markdown code {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 4px;
            font-family: monospace;
            padding: 0.2em 0.4em;
            font-size: 0.9em;
        }

        .description-markdown pre {
            padding: 1rem;
            margin: 1.5rem 0;
            overflow-x: auto;
        }

        .description-markdown pre code {
            background: none;
            padding: 0;
            border-radius: 0;
        }

        /* Tables */
        .description-markdown table {
            width: 100%;
            margin: 1.5rem 0;
            border-collapse: collapse;
        }

        .description-markdown th,
        .description-markdown td {
            padding: 0.75rem;
            border: 1px solid rgba(127, 0, 255, 0.2);
            text-align: left;
        }

        .description-markdown th {
            background: rgba(127, 0, 255, 0.1);
            color: var(--text-light);
            font-weight: 600;
        }

        .description-markdown tr:nth-child(even) {
            background: rgba(127, 0, 255, 0.05);
        }

        /* Images */
        .description-markdown img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 1.5rem 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .description-markdown img:hover {
            transform: scale(1.02);
            box-shadow: var(--glow-shadow);
        }

        /* Horizontal Rule */
        .description-markdown hr {
            border: 0;
            height: 1px;
            background: linear-gradient(to right, 
                rgba(127, 0, 255, 0.1), 
                rgba(127, 0, 255, 0.3), 
                rgba(127, 0, 255, 0.1));
            margin: 2rem 0;
        }

        .btn-back {
            margin: 2rem;
            display: inline-block;
            padding: 0.5rem 1.5rem;
            background-color: transparent;
            color: var(--text-light);
            border: 2px solid rgba(127, 0, 255, 0.5);
            border-radius: 8px;
            font-family: 'Exo 2', sans-serif;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            animation: fadeIn 0.8s ease forwards;
        }

        .btn-back:hover {
            background-color: rgba(127, 0, 255, 0.1);
            transform: translateX(-5px);
            box-shadow: var(--glow-shadow);
            color: white;
        }

        .btn-back i {
            margin-right: 0.5rem;
        }

        .store-links {
            margin-top: 1.5rem;
        }

        .store-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            margin-bottom: 0.75rem;
            background: rgba(127, 0, 255, 0.05);
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text-light);
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .store-link:hover {
            background: rgba(127, 0, 255, 0.15);
            color: white;
            transform: translateX(5px);
            box-shadow: var(--glow-shadow);
            border-color: rgba(127, 0, 255, 0.3);
        }

        .store-link i {
            margin-right: 1rem;
            font-size: 1.2rem;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
        }

        .platform-badge {
            display: inline-block;
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            background-color: rgba(127, 0, 255, 0.2);
            border: 1px solid rgba(127, 0, 255, 0.3);
            color: var(--text-light);
            transition: all 0.3s ease;
        }

        .platform-badge:hover {
            background-color: rgba(127, 0, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(127, 0, 255, 0.3);
        }

        .genre-badge {
            display: inline-block;
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--text-light);
            transition: all 0.3s ease;
        }

        .genre-badge:hover {
            background-color: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .game-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .btn-game-action {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            background-color: var(--card-bg);
            border: 1px solid rgba(127, 0, 255, 0.3);
            text-decoration: none;
            color: var(--text-light);
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-game-action:hover {
            background-color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: var(--glow-shadow);
        }

        .btn-game-action i {
            font-size: 1.2rem;
        }

        .btn-wishlist.active {
            background-color: #2196f3;
            border-color: #2196f3;
        }

        /* Reviews Section Styles */
        .reviews-section {
            margin-top: 3rem;
            padding: 2rem;
            background-color: var(--card-bg);
            border-radius: 12px;
            border: 1px solid rgba(127, 0, 255, 0.1);
        }

        .reviews-section h3 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-family: 'Orbitron', sans-serif;
        }

        .user-review-card,
        .no-review-card {
            background-color: rgba(127, 0, 255, 0.05);
            border: 1px solid rgba(127, 0, 255, 0.2);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .no-review-card {
            text-align: center;
        }

        .no-review-card h4 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .review-content h5 {
            color: var(--text-light);
            margin: 1rem 0 0.5rem 0;
        }

        .rating-display {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .rating-display .bi-star,
        .rating-display .bi-star-fill {
            color: #b200ff;
            font-size: 1.2rem;
        }

        .rating-text {
            color: var(--text-light);
            font-weight: bold;
            margin-left: 0.5rem;
        }

        .community-reviews h4 {
            color: var(--text-light);
            margin-bottom: 1rem;
        }

        .reviews-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .review-card {
            background-color: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .review-card:hover {
            border-color: rgba(127, 0, 255, 0.3);
            box-shadow: 0 2px 8px rgba(127, 0, 255, 0.1);
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .reviewer-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }

        .reviewer-avatar-initials {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .reviewer-name {
            color: var(--text-light);
            font-weight: 500;
        }

        .review-date {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .no-reviews {
            color: var(--text-muted);
            text-align: center;
            font-style: italic;
        }

        /* Status colors */
        .status-playing { background-color: #28a745 !important; }
        .status-beaten { background-color: #d742f5 !important; }
        .status-completed { background-color: #f1c40f !important; }
        .status-shelved { background-color: #e67e22 !important; }
        .status-abandoned { background-color: #e74c3c !important; }

        /* Modal styles */
        .modal-content {
            background-color: var(--card-bg);
            border: 1px solid rgba(127, 0, 255, 0.3);
            border-radius: 12px;
        }
        
        .modal-header, .modal-footer {
            border: none;
        }
        
        .status-option {
            background: rgba(30, 30, 47, 0.5);
            border: 2px solid transparent;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .status-option:hover {
            transform: translateX(5px);
            background-color: var(--card-hover-bg);
            border-color: rgba(127, 0, 255, 0.3);
        }
        
        .status-option.active {
            border-color: var(--primary-color);
            background-color: var(--card-hover-bg);
            transform: translateX(10px);
            box-shadow: 0 0 15px rgba(127, 0, 255, 0.2);
        }
        
        .status-option i {
            font-size: 1.8rem;
            color: var(--text-muted);
            transition: color 0.3s ease;
        }
        
        /* Status colors */
        .status-option.active[data-status="Want to Play"] i {
            color: #3b82f6;
        }
        .status-option.active[data-status="Playing"] i {
            color: #28a745;
        }
        
        .status-option.active[data-status="Beaten"] i {
            color: #d742f5;
        }
        
        .status-option.active[data-status="Completed"] i {
            color: #f1c40f;
        }
        
        .status-option.active[data-status="Shelved"] i {
            color: #e67e22;
        }
        
        .status-option.active[data-status="Abandoned"] i {
            color: #e74c3c;
        }

        /* Animation keyframes */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeInUp {
            from { 
                opacity: 0; 
                transform: translateY(20px);
            }
            to { 
                opacity: 1; 
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInRight {
            from { 
                opacity: 0; 
                transform: translateX(20px);
            }
            to { 
                opacity: 1; 
                transform: translateX(0);
            }
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .game-content-wrapper {
                flex-direction: column;
            }
            
            .game-sidebar {
                order: 1;
                margin-bottom: 2rem;
            }
            
            .game-content {
                order: 2;
            }
        }

        @media (max-width: 576px) {
            .game-title {
                font-size: 2rem;
            }
            
            .game-container {
                padding: 0 1rem 2rem;
            }
            
            .game-header {
                padding: 4rem 0 2rem;
            }
            
            .btn-back {
                margin: 1rem;
            }
        }

        .btn-game-action[data-is-released="0"] {
            background-color: rgba(60, 60, 77, 0.8) !important;
            cursor: not-allowed;
            border-color: rgba(255, 255, 255, 0.1);
            opacity: 0.7;
        }

        .btn-game-action[data-is-released="0"]:hover {
            transform: none !important;
            box-shadow: none !important;
        }

        /* DLC Section Styles */
        .dlc-section {
            margin-top: 3rem;
            padding: 2rem;
            background: linear-gradient(135deg, rgba(127, 0, 255, 0.05) 0%, rgba(127, 0, 255, 0.1) 100%);
            border-radius: 16px;
            border: 1px solid rgba(127, 0, 255, 0.2);
            box-shadow: 0 8px 32px rgba(127, 0, 255, 0.1);
            position: relative;
            overflow: hidden;
        }

        .dlc-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--primary-color), transparent);
            animation: shimmer 2s ease-in-out infinite;
        }

        @keyframes shimmer {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 1; }
        }

        .dlc-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid rgba(127, 0, 255, 0.3);
        }

        .dlc-title {
            font-family: 'Orbitron', sans-serif;
            color: var(--primary-color);
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-shadow: 0 0 10px rgba(127, 0, 255, 0.3);
        }

        .dlc-title i {
            font-size: 1.5rem;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .dlc-count {
            background: rgba(127, 0, 255, 0.2);
            color: var(--text-light);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            border: 1px solid rgba(127, 0, 255, 0.3);
            backdrop-filter: blur(10px);
        }

        .dlc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .dlc-card {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(127, 0, 255, 0.1);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            cursor: pointer;
        }

        .dlc-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(127, 0, 255, 0.4);
            box-shadow: 0 12px 40px rgba(127, 0, 255, 0.2);
        }

        .dlc-image-container {
            position: relative;
            height: 160px;
            overflow: hidden;
            background: linear-gradient(45deg, rgba(127, 0, 255, 0.1), rgba(127, 0, 255, 0.05));
        }

        .dlc-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.4s ease;
        }

        .dlc-card:hover .dlc-image {
            transform: scale(1.1);
        }

        .dlc-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(127, 0, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .dlc-card:hover .dlc-overlay {
            opacity: 1;
        }

        .dlc-overlay-content {
            text-align: center;
            color: white;
            font-weight: 600;
        }

        .dlc-overlay-content i {
            font-size: 2rem; 
            margin-bottom: 0.5rem;
            display: block;
        }

        .dlc-info {
            padding: 1rem;
            position: relative;
        }

        .dlc-name {
            color: var(--text-light);
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 0.5rem 0;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .dlc-badge {
            display: inline-block;
            background: linear-gradient(45deg, var(--primary-color), #9933ff);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(127, 0, 255, 0.3);
        }

        /* Responsive adjustments for DLC section */
        @media (max-width: 768px) {
            .dlc-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .dlc-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 1rem;
            }

            .dlc-title {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .dlc-section {
                padding: 1.5rem;
                margin-top: 2rem;
            }

            .dlc-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php include '../includes/nav.php'; ?>

<!-- Game Header -->
<header class="game-header">
    <div class="container">
        <h1 class="game-title mb-4"><?= htmlspecialchars($game['title']) ?></h1>
        <!-- Platform and Genre Badges -->
        <div class="mb-4">
            <?php
                if (!empty($game['platforms'])) {
                    $platforms = explode(', ', $game['platforms']);
                    foreach ($platforms as $plat) {
                        echo "<span class='platform-badge'>" . htmlspecialchars($plat) . "</span>";
                    }
                }
            ?>
        </div>
        <div>
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
</header>

<div class="game-container">
    <!-- Back Button -->
    <a href="../explore.php" class="btn-back mb-4">
        <i class="bi bi-arrow-left"></i> Back to List
    </a>
    
    <div class="game-content-wrapper">
        <div class="game-content">
            <div class="description-markdown">
                <?php
                    require_once __DIR__ . '/../parsedown/Parsedown.php';
                    require_once __DIR__ . '/../parsedown/ParsedownExtra.php';
                    $Parsedown = new ParsedownExtra();
                    $desc = stripslashes(str_replace(['\\r\\n', '\r\n', "\r\n", '\n', "\n", '\r', "\r"], "\n", $game['description']));
                    echo $Parsedown->text($desc);
                ?>
            </div>
            <?php
// Fetch DLCs for this game
$dlcQuery = $conn->prepare("SELECT id, title, image_url FROM dlcs WHERE parent_game_id = ?");
$dlcQuery->bind_param("i", $game_id);
$dlcQuery->execute();
$dlcs = $dlcQuery->get_result();
?>

<?php if ($dlcs->num_rows > 0): ?>
    <div class="dlc-section">
        <div class="dlc-header">
            <h3 class="dlc-title">
                <i class="bi bi-puzzle-fill"></i>
                Downloadable Content
            </h3>
            <div class="dlc-count"><?= $dlcs->num_rows ?> DLC<?= $dlcs->num_rows > 1 ? 's' : '' ?></div>
        </div>
        <div class="dlc-grid">
            <?php while ($dlc = $dlcs->fetch_assoc()): ?>
                <div class="dlc-card">
                    <div class="dlc-image-container">
                        <img src="<?= htmlspecialchars($dlc['image_url']) ?>" alt="<?= htmlspecialchars($dlc['title']) ?>" class="dlc-image">
                        <div class="dlc-overlay">
                            <div class="dlc-overlay-content">
                                <i class="bi bi-plus-circle"></i>
                                <span>View Details</span>
                            </div>
                        </div>
                    </div>
                    <div class="dlc-info">
                        <h5 class="dlc-name"><?= htmlspecialchars($dlc['title']) ?></h5>
                        <div class="dlc-badge">DLC</div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
<?php endif; ?>
        </div>

        <aside class="game-sidebar">
            <?php if (!empty($game['image_url'])): ?>
                <img src="<?= htmlspecialchars($game['image_url']) ?>" alt="<?= htmlspecialchars($game['title']) ?>">
            <?php endif; ?>

            <div class="game-actions">
                <?php
                    $now = new DateTime();
                    $isReleased = false;
                    
                    if (!empty($game['is_tba']) && $game['is_tba']) {
                        $isReleased = false;
                    } elseif (!empty($game['release_date'])) {
                        $releaseDate = new DateTime($game['release_date']);
                        $isReleased = $releaseDate <= $now;
                    }
                ?>
                <a class="btn btn-primary" href="/1hnd/gametracker/games/characters.php?game_id=<?= (int)$game['id'] ?>">
  Characters
</a>

                <button type="button" id="statusBtn" class="btn-game-action <?= !empty($userStatus) ? 'status-' . strtolower(str_replace(' ', '-', $userStatus)) : '' ?>" 
                    data-is-released="<?= $isReleased ? '1' : '0' ?>"
                    title="<?= $isReleased ? 'Set play status' : 'Available after release' ?>"
                    <?= $isReleased ? '' : 'disabled' ?>>
                    <i class="ph-fill ph-game-controller"></i>
                    <?= !empty($userStatus) ? htmlspecialchars($userStatus) : 'Set Status' ?>
                </button>
                
                <button id="wishlistBtn" class="btn-game-action btn-wishlist <?= $inWishlist ? 'active' : '' ?>" data-game-id="<?= $game_id ?>">
                    <i class="bi <?= $inWishlist ? 'bi-cart-check-fill' : 'bi-cart-plus' ?>"></i>
                    <?= $inWishlist ? 'In Wishlist' : 'Add to Wishlist' ?>
                </button>
                
                <!-- Review Button -->
                <button id="reviewBtn" class="btn-game-action" data-game-id="<?= $game_id ?>">
                    <i class="bi bi-pencil-square"></i>
                    Write Review
                </button>

                <?php if (isset($_SESSION['user_id']) && !empty($game['steam_app_id'])): ?>
                <!-- Achievements Button -->
                <a href="achievements.php?id=<?= $game_id ?>" class="btn-game-action">
                    <i class="bi bi-trophy"></i>
                    Achievements
                </a>
                <?php endif; ?>
            </div>

            <h5>Quick Facts</h5>
            <p><strong>Release Date</strong>
                <?php
                    if (!empty($game['is_tba']) && $game['is_tba']) {
                        echo "TBA" . (!empty($game['tba_year']) ? " " . htmlspecialchars($game['tba_year']) : "");
                    } elseif (!empty($game['release_date'])) {
                        echo date('F j, Y', strtotime($game['release_date']));
                    } else {
                        echo "Unknown";
                    }
                ?>
            </p>
            
            <p><strong>Platforms</strong>
                <?= nl2br(htmlspecialchars($game['platforms'])) ?>
            </p>
            
            <p><strong>Genres</strong>
                <?= nl2br(htmlspecialchars($game['genre'])) ?>
            </p>

            <?php if (!empty($storeLinks) || !empty($officialWebsite)): ?>
                <h5>Where to Buy</h5>
                <div class="store-links">
                    <?php if (!empty($officialWebsite)): ?>
                        <a href="<?= htmlspecialchars($officialWebsite) ?>" target="_blank" class="store-link">
                            <i class="fas fa-globe"></i>
                            Official Website
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $storeIcons = [
                        'Steam' => '<i class="fab fa-steam"></i>',
                        'Epic Games' => '<i class="fas fa-gamepad"></i>',
                        'Xbox Store' => '<i class="fab fa-xbox"></i>',
                        'PlayStation Store' => '<i class="fab fa-playstation"></i>',
                        'Nintendo Store' => '<i class="fas fa-gamepad"></i>'
                    ];

                    foreach ($storeLinks as $storeName => $storeUrl):
                        $icon = $storeIcons[$storeName] ?? '<i class="fas fa-shopping-cart"></i>';
                    ?>
                        <a href="<?= htmlspecialchars($storeUrl) ?>" target="_blank" class="store-link">
                            <?= $icon ?>
                            <?= htmlspecialchars($storeName) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</div>

<!-- Reviews Section -->
<div class="reviews-section">
    <h3>Reviews</h3>
    <?php
    // Check if user has reviewed this game
    $userReview = null;
    if (isset($_SESSION['user_id'])) {
        $reviewQuery = $conn->prepare("SELECT * FROM reviews WHERE user_id = ? AND game_id = ?");
        $reviewQuery->bind_param("ii", $_SESSION['user_id'], $game_id);
        $reviewQuery->execute();
        $userReview = $reviewQuery->get_result()->fetch_assoc();
        $reviewQuery->close();
    }
    
    // Get all public reviews for this game
    $reviewsQuery = $conn->prepare("
        SELECT r.*, u.username, u.profile_image 
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.game_id = ? AND r.is_public = 1 
        ORDER BY r.created_at DESC
    ");
    $reviewsQuery->bind_param("i", $game_id);
    $reviewsQuery->execute();
    $reviews = $reviewsQuery->get_result();
    $reviewsQuery->close();
    ?>
    
    <!-- User Review Section -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <?php if ($userReview): ?>
            <div class="user-review-card">
                <h4>Your Review</h4>
                <div class="review-content">
                    <div class="rating-display">
                        <?= renderStars($userReview['rating']) ?>
                        <span class="rating-text"><?= $userReview['rating'] ?>/5</span>
                    </div>
                    <?php if (!empty($userReview['review_title'])): ?>
                        <h5><?= htmlspecialchars($userReview['review_title']) ?></h5>
                    <?php endif; ?>
                    <?php if (!empty($userReview['review_text'])): ?>
                        <p><?= nl2br(htmlspecialchars($userReview['review_text'])) ?></p>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-primary edit-review-btn" data-review='<?= json_encode($userReview) ?>'>
                        <i class="bi bi-pencil"></i> Edit Review
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="no-review-card">
                <h4>Review This Game!</h4>
                <p>Share your thoughts about this game with the community.</p>
                <button class="btn btn-primary write-review-btn" data-game-id="<?= $game_id ?>">
                    <i class="bi bi-pencil-square"></i> Write a Review
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Community Reviews -->
    <div class="community-reviews">
        <h4>Community Reviews</h4>
        <?php if ($reviews->num_rows > 0): ?>
            <div class="reviews-list">
                <?php while ($review = $reviews->fetch_assoc()): ?>
                    <?php if ($review['user_id'] != ($_SESSION['user_id'] ?? 0)): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <div class="reviewer-info">
                                    <?php if (!empty($review['profile_image'])): ?>
                                        <img src="../<?= htmlspecialchars($review['profile_image']) ?>" alt="Profile" class="reviewer-avatar">
                                    <?php else: ?>
                                        <div class="reviewer-avatar-initials"><?= strtoupper(substr($review['username'], 0, 2)) ?></div>
                                    <?php endif; ?>
                                    <span class="reviewer-name"><?= htmlspecialchars($review['username']) ?></span>
                                </div>
                                <div class="rating-display">
                                    <?= renderStars($review['rating']) ?>
                                    <span class="rating-text"><?= $review['rating'] ?>/5</span>
                                </div>
                            </div>
                            <?php if (!empty($review['review_title'])): ?>
                                <h5><?= htmlspecialchars($review['review_title']) ?></h5>
                            <?php endif; ?>
                            <?php if (!empty($review['review_text'])): ?>
                                <p><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                            <?php endif; ?>
                            <small class="review-date"><?= date('M j, Y', strtotime($review['created_at'])) ?></small>
                        </div>
                    <?php endif; ?>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="no-reviews">No reviews yet. Be the first to review this game!</p>
        <?php endif; ?>
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
                <div class="status-option <?= $userStatus == 'Want to Play' ? 'active' : '' ?>" data-status="Want to Play">
                    <i class="ph-fill ph-list-plus"></i>
                    <div>
                        <strong>Want to Play</strong>
                        <br><small>On your backlog to play</small>
                    </div>
                </div>
                <div class="status-option <?= $userStatus == 'Playing' ? 'active' : '' ?>" data-status="Playing">
                    <i class="ph-fill ph-game-controller"></i>
                    <div>
                        <strong>Playing</strong>
                        <br><small>Currently playing this game</small>
                    </div>
                </div>
                <div class="status-option <?= $userStatus == 'Beaten' ? 'active' : '' ?>" data-status="Beaten">
                    <i class="bi bi-check-circle"></i>
                    <div>
                        <strong>Beaten</strong>
                        <br><small>Finished the main objective</small>
                    </div>
                </div>
                <div class="status-option <?= $userStatus == 'Completed' ? 'active' : '' ?>" data-status="Completed">
                    <i class="bi bi-trophy"></i>
                    <div>
                        <strong>Completed</strong>
                        <br><small>Finished main story and additional content</small>
                    </div>
                </div>
                <div class="status-option <?= $userStatus == 'Shelved' ? 'active' : '' ?>" data-status="Shelved">
                    <i class="bi bi-pause-circle"></i>
                    <div>
                        <strong>Shelved</strong>
                        <br><small>Temporarily set aside</small>
                    </div>
                </div>
                <div class="status-option <?= $userStatus == 'Abandoned' ? 'active' : '' ?>" data-status="Abandoned">
                    <i class="bi bi-x-circle"></i>
                    <div>
                        <strong>Abandoned</strong>
                        <br><small>Stopped playing with no intent to return</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveStatus">Save Status</button>
            </div>
        </div>
    </div>
</div>

<!-- Add this new modal for login prompt -->
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
                    <a href="../auth/login.php" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </a>
                    <a href="../auth/register.php" class="btn btn-outline-light flex-grow-1">
                        <i class="bi bi-person-plus me-2"></i>Sign Up
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/review-modal.php'; ?>

<?php include '../includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function resetStatusModalState() {
    const modalEl = document.getElementById('statusModal');
    if (!modalEl) return;

    const instance = bootstrap.Modal.getInstance(modalEl);
    if (instance) {
        instance.dispose();
    }
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.removeAttribute('aria-modal');

    if (!document.querySelector('.modal.show')) {
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
        document.querySelectorAll('.modal-backdrop').forEach(function(el) { el.remove(); });
    }
}

function openStatusModal() {
    const modalEl = document.getElementById('statusModal');
    if (!modalEl) return;
    resetStatusModalState();
    const modal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true });
    modal.show();
}

function closeStatusModal() {
    const modalEl = document.getElementById('statusModal');
    if (!modalEl) {
        resetStatusModalState();
        return;
    }
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) {
        modal.hide();
    } else {
        resetStatusModalState();
    }
}

document.getElementById('statusModal')?.addEventListener('hidden.bs.modal', resetStatusModalState);

$(document).ready(function() {
    $('#statusBtn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const isReleased = this.getAttribute('data-is-released') === '1';
        if (!isReleased) {
            return;
        }

        <?php if (!isset($_SESSION['user_id'])): ?>
            const loginModalEl = document.getElementById('loginPromptModal');
            if (loginModalEl) {
                bootstrap.Modal.getOrCreateInstance(loginModalEl).show();
            }
            return;
        <?php endif; ?>

        openStatusModal();
    });
    
    // Check login status before handling wishlist
    $('#wishlistBtn').click(function(e) {
        <?php if (!isset($_SESSION['user_id'])): ?>
            e.preventDefault();
            $('#loginPromptModal').modal('show');
            return false;
        <?php else: ?>
        const gameId = $(this).data('game-id');
        const isInWishlist = $(this).hasClass('active');
        
        $.ajax({
            url: '../api/wishlist.php',
            type: 'POST',
            data: {
                game_id: gameId,
                action: isInWishlist ? 'remove' : 'add'
            },
            success: function(response) {
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (data.success) {
                        const $btn = $('#wishlistBtn');
                        
                        if (isInWishlist) {
                            // Remove from wishlist
                            $btn.removeClass('active');
                            $btn.html('<i class="bi bi-cart-plus"></i> Add to Wishlist');
                        } else {
                            // Add to wishlist
                            $btn.addClass('active');
                            $btn.html('<i class="bi bi-cart-check-fill"></i> In Wishlist');
                        }
                    } else {
                        showToast('Error: ' + (data.message || 'Failed to update wishlist'), 'error');
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    showToast('An error occurred while processing the response', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                showToast('An error occurred. Please try again.', 'error');
            }
        });
        <?php endif; ?>
    });

    // Status option selection
    $('.status-option').click(function() {
        $('.status-option').removeClass('active');
        $(this).addClass('active');
    });
    
    // Review button functionality
    $('#reviewBtn, .write-review-btn').click(function(e) {
        e.preventDefault();
        
        <?php if (!isset($_SESSION['user_id'])): ?>
            $('#loginPromptModal').modal('show');
            return;
        <?php endif; ?>
        
        const gameId = $(this).data('game-id');
        
        // Open review modal
        if (typeof openReviewModal === 'function') {
            openReviewModal(gameId);
        } else {
            console.error('Review modal function not found');
        }
    });
    
    // Edit review button
    $('.edit-review-btn').click(function(e) {
        e.preventDefault();
        
        const reviewData = $(this).data('review');
        const gameId = <?= $game_id ?>;
        
        // Open review modal with existing data
        if (typeof openReviewModal === 'function') {
            openReviewModal(gameId, reviewData);
        } else {
            console.error('Review modal function not found');
        }
    });
    
    // Save status
    $('#saveStatus').click(function() {
        const selectedStatus = $('.status-option.active').data('status');
        
        if (!selectedStatus) {
            showToast('Please select a status first.', 'warning');
            return;
        }
        
        $.ajax({
            url: '../api/save-status.php',
            type: 'POST',
            dataType: 'json',
            data: {
                game_id: <?= $game_id ?>,
                status: selectedStatus
            },
            success: function(data) {
                if (data.success) {
                    const statusClass = selectedStatus.toLowerCase().replace(/\s+/g, '-');
                    const $statusBtn = $('#statusBtn');
                    $statusBtn.html('<i class="ph-fill ph-game-controller"></i> ' + selectedStatus);
                    $statusBtn.removeClass('status-playing status-beaten status-completed status-shelved status-abandoned status-want-to-play');
                    $statusBtn.addClass('status-' + statusClass);
                    closeStatusModal();
                } else {
                    showToast('Error: ' + (data.error || data.message || 'Failed to update status'), 'error');
                }
            },
            error: function(xhr) {
                console.error('Status update failed:', xhr.responseText);
                showToast('An error occurred. Please try again.', 'error');
            }
        });
    });
});
</script>

<?php $conn->close(); ?>
</body>
</html>
