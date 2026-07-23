<?php
// Prevent any output buffering issues
ob_start();

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

// Custom error handler to prevent HTML error output
function handleError($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error: [$errno] $errstr in $errfile on line $errline");
    return true; // Don't execute PHP's internal error handler
}
set_error_handler("handleError");

// Custom exception handler
function handleException($e) {
    error_log("Uncaught Exception: " . $e->getMessage());
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit(1);
}
set_exception_handler("handleException");

// Helper functions for Roman numeral conversion
function numberToRoman($number) {
    $number = (int)$number;
    if ($number < 1 || $number > 50) return null; // Limit to reasonable range
    
    $romans = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V',
        6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X',
        11 => 'XI', 12 => 'XII', 13 => 'XIII', 14 => 'XIV', 15 => 'XV',
        16 => 'XVI', 17 => 'XVII', 18 => 'XVIII', 19 => 'XIX', 20 => 'XX',
        21 => 'XXI', 22 => 'XXII', 23 => 'XXIII', 24 => 'XXIV', 25 => 'XXV',
        26 => 'XXVI', 27 => 'XXVII', 28 => 'XXVIII', 29 => 'XXIX', 30 => 'XXX',
        31 => 'XXXI', 32 => 'XXXII', 33 => 'XXXIII', 34 => 'XXXIV', 35 => 'XXXV',
        36 => 'XXXVI', 37 => 'XXXVII', 38 => 'XXXVIII', 39 => 'XXXIX', 40 => 'XL',
        41 => 'XLI', 42 => 'XLII', 43 => 'XLIII', 44 => 'XLIV', 45 => 'XLV',
        46 => 'XLVI', 47 => 'XLVII', 48 => 'XLVIII', 49 => 'XLIX', 50 => 'L'
    ];
    
    return $romans[$number] ?? null;
}

function romanToNumber($roman) {
    $roman = strtoupper($roman);
    $romans = [
        'I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5,
        'VI' => 6, 'VII' => 7, 'VIII' => 8, 'IX' => 9, 'X' => 10,
        'XI' => 11, 'XII' => 12, 'XIII' => 13, 'XIV' => 14, 'XV' => 15,
        'XVI' => 16, 'XVII' => 17, 'XVIII' => 18, 'XIX' => 19, 'XX' => 20,
        'XXI' => 21, 'XXII' => 22, 'XXIII' => 23, 'XXIV' => 24, 'XXV' => 25,
        'XXVI' => 26, 'XXVII' => 27, 'XXVIII' => 28, 'XXIX' => 29, 'XXX' => 30,
        'XXXI' => 31, 'XXXII' => 32, 'XXXIII' => 33, 'XXXIV' => 34, 'XXXV' => 35,
        'XXXVI' => 36, 'XXXVII' => 37, 'XXXVIII' => 38, 'XXXIX' => 39, 'XL' => 40,
        'XLI' => 41, 'XLII' => 42, 'XLIII' => 43, 'XLIV' => 44, 'XLV' => 45,
        'XLVI' => 46, 'XLVII' => 47, 'XLVIII' => 48, 'XLIX' => 49, 'L' => 50
    ];
    
    return $romans[$roman] ?? null;
}

// admin/game-approval.php
require_once '../includes/db.php';
session_start();

// Security check: Verify user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../explore.php');
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        // Clear any previous output and set headers
        if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
    
    $action = $_POST['action'];
        error_log("Received action: " . $action);
        
        $response = null;
    
    switch ($action) {
            case 'fetch_new_games':
                $response = fetchNewGamesFromRAWG();
                break;
        case 'get_games':
                $response = getPendingGames();
            break;
        case 'review_game':
                $response = reviewGame();
            break;
        case 'bulk_review':
                $response = bulkReviewGames();
            break;
        case 'get_stats':
                $response = getApprovalStats();
            break;
                case 'search_games':
            $response = searchGames();
            break;
        case 'update_game':
            $response = updateGameDetails();
            break;
        default:
            throw new Exception("Invalid action: " . $action);
        }
        
        if ($response === null) {
            throw new Exception("No response generated");
        }
        
        // Ensure clean JSON output
        if (ob_get_length()) ob_clean();
        echo json_encode($response);
        
    } catch (Throwable $e) {
        error_log("Error processing request: " . $e->getMessage());
        if (ob_get_length()) ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit();
}

function getPendingGames() {
    global $conn;
    
    $page = (int)($_POST['page'] ?? 1);
    $status = $_POST['status'] ?? 'pending';
    $minRating = (float)($_POST['min_rating'] ?? 0);
    $minReviews = (int)($_POST['min_reviews'] ?? 0);
    $year = $_POST['year'] ?? '';
    
    // If page is 1, return all games for client-side pagination
    $limit = $page === 1 ? 1000 : 20;  // Use a high limit to get all games
    $offset = $page === 1 ? 0 : ($page - 1) * $limit;
    
    $whereConditions = [];
    $params = [];
    
    if ($status !== 'all') {
        $whereConditions[] = "status = ?";
        $params[] = $status;
    }
    
    if ($minRating > 0) {
        $whereConditions[] = "rating >= ?";
        $params[] = $minRating;
    }
    
    if ($minReviews > 0) {
        $whereConditions[] = "ratings_count >= ?";
        $params[] = $minReviews;
    }
    
    if ($year) {
        $whereConditions[] = "YEAR(release_date) = ?";
        $params[] = $year;
    }
    
    $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM pending_games $whereClause";
    $countStmt = $conn->prepare($countSql);
    if ($params) {
        $countStmt->bind_param(str_repeat('s', count($params)), ...$params);
    }
    $countStmt->execute();
    $totalGames = $countStmt->get_result()->fetch_row()[0];
    
    // Get games
    $sql = "SELECT * FROM pending_games $whereClause 
            ORDER BY popularity_score DESC, fetched_at DESC 
            LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($params) - 2) . 'ii', ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $games = [];
    while ($row = $result->fetch_assoc()) {
        $games[] = $row;
    }
    
    return [
        'success' => true,
        'games' => $games,
        'current_page' => $page,
        'total_pages' => ceil($totalGames / ($page === 1 ? 10 : $limit)), // Use 10 for client-side pagination
        'total_games' => $totalGames
    ];
}

function reviewGame() {
    global $conn;
    
    $gameId = (int)$_POST['game_id'];
    $status = $_POST['status'];
    
    // Get DLC information from pending_games table
    $stmt = $conn->prepare("SELECT is_dlc, parent_game_id FROM pending_games WHERE rawg_id = ?");
    $stmt->bind_param("i", $gameId);
    $stmt->execute();
    $pendingGame = $stmt->get_result()->fetch_assoc();
    
    $isDlc = $pendingGame ? (bool)$pendingGame['is_dlc'] : false;
    $parentGameId = $pendingGame ? $pendingGame['parent_game_id'] : null;
    
    $stmt = $conn->prepare("UPDATE pending_games SET status = ?, reviewed_at = NOW() WHERE rawg_id = ?");
    $stmt->bind_param("si", $status, $gameId);
    $success = $stmt->execute();
    
    // If approved, copy to appropriate table
    if ($status === 'approved' && $success) {
        if ($isDlc && $parentGameId) {
            copyApprovedDlcToMain($gameId, $parentGameId);
        } else {
            copyApprovedGameToMain($gameId);
        }
    }
    
    return ['success' => $success];
}

function bulkReviewGames() {
    global $conn;
    
    $gameIds = json_decode($_POST['game_ids'], true);
    $status = $_POST['status'];
    $dlcInfo = json_decode($_POST['dlc_info'] ?? '{}', true); // Contains DLC info for each game
    
    if (!is_array($gameIds) || empty($gameIds)) {
        return ['success' => false, 'error' => 'No games selected'];
    }
    
    if ($status === 'removed') {
        // Delete games from pending_games table
        $placeholders = str_repeat('?,', count($gameIds) - 1) . '?';
        $stmt = $conn->prepare("DELETE FROM pending_games WHERE rawg_id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($gameIds)), ...$gameIds);
        $success = $stmt->execute();
    } else {
        // Update status for approved/rejected games
        $placeholders = str_repeat('?,', count($gameIds) - 1) . '?';
        $params = array_merge([$status], $gameIds);
        $types = 's' . str_repeat('i', count($gameIds)); // 's' for status, 'i' for each game ID
        
        $stmt = $conn->prepare("UPDATE pending_games SET status = ?, reviewed_at = NOW() WHERE rawg_id IN ($placeholders)");
        $stmt->bind_param($types, ...$params);
        $success = $stmt->execute();
        
        // Copy approved games/DLCs to appropriate tables
        if ($status === 'approved' && $success) {
            foreach ($gameIds as $gameId) {
                // Get DLC information from pending_games table
                $stmt = $conn->prepare("SELECT is_dlc, parent_game_id FROM pending_games WHERE rawg_id = ?");
                $stmt->bind_param("i", $gameId);
                $stmt->execute();
                $pendingGame = $stmt->get_result()->fetch_assoc();
                
                $isDlc = $pendingGame ? (bool)$pendingGame['is_dlc'] : false;
                $parentGameId = $pendingGame ? $pendingGame['parent_game_id'] : null;
                
                if ($isDlc && $parentGameId) {
                    copyApprovedDlcToMain($gameId, $parentGameId);
                } else {
                    copyApprovedGameToMain($gameId);
                }
            }
        }
    }
    
    return ['success' => $success];
}

function copyApprovedGameToMain($rawgId) {
    global $conn;
    
    // Get pending game data
    $stmt = $conn->prepare("SELECT * FROM pending_games WHERE rawg_id = ? AND status = 'approved'");
    $stmt->bind_param("i", $rawgId);
    $stmt->execute();
    $game = $stmt->get_result()->fetch_assoc();
    
    if (!$game) return false;
    
    // Check if game already exists in main table
    $checkStmt = $conn->prepare("SELECT id FROM games WHERE rawg_id = ?");
    $checkStmt->bind_param("i", $rawgId);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        return true; // Already exists, consider it successful
    }
    
    // Map the data to your games table structure
    $insertStmt = $conn->prepare("
        INSERT INTO games 
        (title, release_date, platforms, genre, image_url, portrait_image_url, 
         description, last_updated, release_year, source, is_tba, tba_year, 
         avg_rating, total_reviews, rawg_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, 'rawg', ?, ?, ?, ?, ?)
    ");
    
    // Process the data to match your schema
    $title = $game['name'];
    $releaseDate = $game['release_date'];
    $platforms = $game['platforms']; // Already processed as comma-separated string
    $genre = $game['genres']; // Already processed as comma-separated string
    $imageUrl = $game['background_image'];
    $portraitImageUrl = $game['background_image']; // Use same image for portrait
    $description = $game['description'];
    
    // Extract release year
    $releaseYear = null;
    $isTba = 0;
    $tbaYear = null;
    
    if ($releaseDate) {
        $releaseYear = (int)date('Y', strtotime($releaseDate));
    } else {
        $isTba = 1;
        // You could set a TBA year if needed
    }
    
    // Map rating data
    $avgRating = $game['rating'] ?? null;
    $totalReviews = $game['ratings_count'] ?? 0;
    $rawgId = $game['rawg_id'];
    
    $insertStmt->bind_param("sssssssiiidii", 
        $title,
        $releaseDate,
        $platforms,
        $genre,
        $imageUrl,
        $portraitImageUrl,
        $description,
        $releaseYear,
        $isTba,
        $tbaYear,
        $avgRating,
        $totalReviews,
        $rawgId
    );
    
    $success = $insertStmt->execute();
    
    if ($success) {
        // Optional: Log the approval action
        error_log("Game approved and added to main database: " . $title . " (RAWG ID: " . $rawgId . ")");
    }
    
    return $success;
}

function copyApprovedDlcToMain($rawgId, $parentGameId) {
    global $conn;
    
    // Get pending DLC data
    $stmt = $conn->prepare("SELECT * FROM pending_games WHERE rawg_id = ? AND status = 'approved'");
    $stmt->bind_param("i", $rawgId);
    $stmt->execute();
    $dlc = $stmt->get_result()->fetch_assoc();
    
    if (!$dlc) return false;
    
    // Check if DLC already exists in dlcs table
    $checkStmt = $conn->prepare("SELECT id FROM dlcs WHERE rawg_id = ?");
    $checkStmt->bind_param("i", $rawgId);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        return true; // Already exists, consider it successful
    }
    
    // Insert into dlcs table
    $insertStmt = $conn->prepare("
        INSERT INTO dlcs 
        (title, parent_game_id, rawg_id, release_date, description, image_url, 
         portrait_image_url, platforms, genre, avg_rating, total_reviews, source) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'rawg')
    ");
    
    // Process the data
    $title = $dlc['name'];
    $releaseDate = $dlc['release_date'];
    $description = $dlc['description'];
    $imageUrl = $dlc['background_image'];
    $portraitImageUrl = $dlc['background_image']; // Use same image for portrait
    $platforms = $dlc['platforms'] ?? '';
    $genre = $dlc['genres'] ?? '';
    $avgRating = $dlc['rating'] ?? null;
    $totalReviews = $dlc['ratings_count'] ?? 0;
    
    $insertStmt->bind_param("siissssssii", 
        $title,
        $parentGameId,
        $rawgId,
        $releaseDate,
        $description,
        $imageUrl,
        $portraitImageUrl,
        $platforms,
        $genre,
        $avgRating,
        $totalReviews
    );
    
    $success = $insertStmt->execute();
    
    if ($success) {
        error_log("DLC approved and added to dlcs database: " . $title . " (RAWG ID: " . $rawgId . ", Parent: " . $parentGameId . ")");
    }
    
    return $success;
}

function fetchNewGamesFromRAWG() {
    global $conn;
    
    try {
        error_log("Starting fetchNewGamesFromRAWG");
    
        // Your RAWG API key
        $apiKey = '58aed2d9aedd4274ab81d91356e775f2';
        
        // Check if a specific game ID was requested
        $specificGameId = $_POST['specific_game_id'] ?? null;
        
        if ($specificGameId) {
            // Fetch specific game
            try {
                $gameDetails = fetchGameDetails($specificGameId, $apiKey);
                
                // Check if this is a DLC
                $isDlc = detectIfDlc($gameDetails);
                $parentGame = findParentGame($gameDetails);
                $parentGameId = null;
                
                if ($isDlc && $parentGame) {
                    // Check if parent game exists in database
                    global $conn;
                    if ($parentGame['id'] !== null) {
                        // We have a RAWG ID, search by that
                        $parentStmt = $conn->prepare("SELECT id FROM games WHERE rawg_id = ?");
                        $parentStmt->bind_param("i", $parentGame['id']);
                        $parentStmt->execute();
                        $parentGameInDb = $parentStmt->get_result()->fetch_assoc();
                        if ($parentGameInDb) {
                            $parentGameId = $parentGameInDb['id'];
                        }
                    } else {
                        // We extracted parent name from title, search by title
                        $parentStmt = $conn->prepare("SELECT id FROM games WHERE title LIKE ? ORDER BY LENGTH(title) DESC LIMIT 1");
                        $parentPattern = $parentGame['name'] . '%';
                        $parentStmt->bind_param("s", $parentPattern);
                        $parentStmt->execute();
                        $parentGameInDb = $parentStmt->get_result()->fetch_assoc();
                        if ($parentGameInDb) {
                            $parentGameId = $parentGameInDb['id'];
                        }
                    }
                }
                
                // Check if game already exists
                $existingStmt = $conn->prepare("SELECT COUNT(*) FROM pending_games WHERE rawg_id = ?");
                $existingStmt->bind_param("i", $specificGameId);
                $existingStmt->execute();
                $exists = $existingStmt->get_result()->fetch_row()[0] > 0;
                
                if ($exists) {
                    return [
                        'success' => true,
                        'message' => 'Game already exists in pending list',
                        'games_added' => 0
                    ];
                }
                
                // Add DLC information to the game data
                $gameDetails['is_dlc'] = $isDlc;
                $gameDetails['parent_game_id'] = $parentGameId;
                
                if (insertPendingGame($gameDetails)) {
                    return [
                        'success' => true,
                        'message' => ($isDlc ? 'DLC' : 'Game') . ' added to pending list',
                        'games_added' => 1
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Failed to add game to pending list'
                    ];
                }
            } catch (Exception $e) {
                error_log("Error fetching specific game $specificGameId: " . $e->getMessage());
                return [
                    'success' => false,
                    'error' => 'Failed to fetch game details: ' . $e->getMessage()
                ];
            }
        }
        
        // Original bulk fetching logic
        $newGamesCount = 0;
        $totalFetched = 0;
        $page = 1;
        $maxAttempts = 10; // Try up to 10 pages before giving up
        $attempts = 0;
        
        // Get list of games we already have (timeline, explore, pending, DLCs) — don't fetch these again
        $existingGames = [];
        $result = $conn->query("
            SELECT rawg_id FROM pending_games WHERE rawg_id IS NOT NULL
            UNION
            SELECT rawg_id FROM games WHERE rawg_id IS NOT NULL
            UNION
            SELECT rawg_id FROM dlcs WHERE rawg_id IS NOT NULL
        ");
        while ($row = $result->fetch_assoc()) {
            $existingGames[] = $row['rawg_id'];
        }
        
        // Get the game type from POST data
        $gameType = $_POST['game_type'] ?? 'released';
        
        // Define different search strategies based on game type
        if ($gameType === 'upcoming') {
            // For upcoming games, use future dates from today onwards
            $today = date('Y-m-d');
            $searchStrategies = [
                [
                    'dates' => $today . ',2030-12-31',
                    'ordering' => '-rating,-ratings_count',
                    'page_size' => 40
                ]
            ];
        } else {
            // For released games, use past dates (default behavior)
            $searchStrategies = [
                [
                    'dates' => '2001-01-01,' . date('Y-m-d'),
                    'ordering' => '-rating,-ratings_count',
                    'page_size' => 40
                ]
            ];
        }
        
        foreach ($searchStrategies as $strategy) {
            while ($newGamesCount < 40 && $attempts < $maxAttempts) {
                try {
                    $strategy['page'] = $page;
                    $strategy['key'] = $apiKey;
                    
                    $url = "https://api.rawg.io/api/games?" . http_build_query($strategy);
                    error_log("Fetching from URL: " . $url);
                    
                    $context = stream_context_create([
                        'http' => [
                            'timeout' => 30,
                            'user_agent' => 'GameTracker/1.0'
                        ]
                    ]);
                    
                    $response = @file_get_contents($url, false, $context);
                    if ($response === false) {
                        throw new Exception("Failed to fetch from RAWG API: " . error_get_last()['message']);
                    }
                    
                    $data = json_decode($response, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception("Failed to parse RAWG API response: " . json_last_error_msg());
                    }
                    
                    if (!isset($data['results']) || !is_array($data['results'])) {
                        throw new Exception("Invalid response structure from RAWG API");
                    }
                    
                    $newGamesThisPage = 0;
                    foreach ($data['results'] as $game) {
                        $totalFetched++;
                        // Skip if we already have this game
                        if (in_array($game['id'], $existingGames)) {
                            continue;
                        }
                        
                        try {
                            // Fetch full game details including description
                            $gameDetails = fetchGameDetails($game['id'], $apiKey);
                            // Merge the details back into the game data
                            $game['description'] = $gameDetails['description'] ?? '';
                            
                            if (insertPendingGame($game)) {
                                $newGamesCount++;
                                $newGamesThisPage++;
                                $existingGames[] = $game['id']; // Add to our tracking array
                            }
                        } catch (Exception $e) {
                            error_log("Failed to fetch details for game {$game['id']}: " . $e->getMessage());
                            continue;
                        }
                        
                        // Add a small delay to avoid hitting API rate limits
                        usleep(100000); // 100ms delay
                    }
                    
                    // If we got no new games this page, increment attempts
                    if ($newGamesThisPage === 0) {
                        $attempts++;
                    }
                    
                    $page++;
                    
                } catch (Exception $e) {
                    error_log("Error processing page $page: " . $e->getMessage());
                    $attempts++;
                    $page++;
                    continue;
                }
            }
        }
        
        return [
            'success' => true,
            'count' => $newGamesCount,
            'total_fetched' => $totalFetched,
            'pages_checked' => $page - 1,
            'message' => $newGamesCount > 0 ? 
                        "Added $newGamesCount new games" : 
                        "No new games found after checking " . ($page - 1) . " pages"
        ];
        
    } catch (Exception $e) {
        error_log("Error in fetchNewGamesFromRAWG: " . $e->getMessage());
        throw $e;
    }
}

function fetchGameDetails($gameId, $apiKey) {
    error_log("Fetching game details for ID: $gameId");
    
    $url = "https://api.rawg.io/api/games/{$gameId}?key={$apiKey}";
    error_log("Fetching from URL: $url");
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'GameTracker/1.0'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        error_log("Failed to fetch game details from RAWG API for ID: $gameId");
        throw new Exception("Failed to fetch game details from RAWG API");
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Failed to parse game details response for ID: $gameId");
        throw new Exception("Failed to parse game details response");
    }
    
    error_log("Successfully fetched game details for ID: $gameId - Name: " . ($data['name'] ?? 'N/A'));
    return $data;
}

function shouldIncludeGame($game, &$skippedGames = null) {
    // Skip games without proper data
    if (empty($game['name']) || empty($game['id'])) {
        if (is_array($skippedGames)) {
            $skippedGames[] = 'Skipped: ' . ($game['name'] ?? 'N/A') . ' (Missing name or ID)';
        }
        return false;
    }
    
    // Skip games with very low ratings or review counts
    $rating = $game['rating'] ?? 0;
    $ratingsCount = $game['ratings_count'] ?? 0;
    
    if ($rating < 3.0 && $ratingsCount < 1000) {
        if (is_array($skippedGames)) {
            $skippedGames[] = 'Skipped: ' . ($game['name'] ?? 'N/A') . ' (Low rating: ' . $rating . ', low reviews: ' . $ratingsCount . ')';
        }
        return false;
    }
    
    // Skip adult/NSFW content
    $name = strtolower($game['name']);
    $description = strtolower($game['description'] ?? '');
    
    $adultKeywords = ['adult', 'nsfw', '18+', 'hentai', 'erotic', 'porn'];
    foreach ($adultKeywords as $keyword) {
        if (strpos($name, $keyword) !== false || strpos($description, $keyword) !== false) {
            if (is_array($skippedGames)) {
                $skippedGames[] = 'Skipped: ' . ($game['name'] ?? 'N/A') . ' (Adult content)';
            }
            return false;
        }
    }
    
    // Skip if it's already in our pending or main games table
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM pending_games WHERE rawg_id = ? UNION SELECT id FROM games WHERE rawg_id = ? LIMIT 1");
    $stmt->bind_param("ii", $game['id'], $game['id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        if (is_array($skippedGames)) {
            $skippedGames[] = 'Skipped (duplicate): ' . ($game['name'] ?? 'N/A');
        }
        return false;
    }
    
    // REMOVE or COMMENT OUT the popularityScore filter for now
    // if ($popularityScore <= 15) {
    //     if (is_array($skippedGames)) {
    //         $skippedGames[] = 'Skipped: ' . ($game['name'] ?? 'N/A') . ' (Low popularity score: ' . $popularityScore . ')';
    //     }
    //     return false;
    // }
    return true;
}

function calculateGameScore($game) {
    $score = 0;
    
    // Base rating (0-50 points)
    $rating = $game['rating'] ?? 0;
    $score += $rating * 10;
    
    // Rating count weight (0-40 points) - More weight for popular games
    $ratingCount = $game['ratings_count'] ?? 0;
    if ($ratingCount > 50000) $score += 40;
    else if ($ratingCount > 25000) $score += 35;
    else if ($ratingCount > 10000) $score += 30;
    else if ($ratingCount > 5000) $score += 20;
    else if ($ratingCount > 1000) $score += 10;
    else if ($ratingCount > 100) $score += 5;
    
    // Metacritic bonus (0-20 points)
    $metacritic = $game['metacritic'] ?? 0;
    if ($metacritic >= 90) $score += 20;
    else if ($metacritic >= 85) $score += 18;
    else if ($metacritic >= 80) $score += 15;
    else if ($metacritic >= 75) $score += 12;
    else if ($metacritic >= 70) $score += 8;
    
    // Bonus for popular franchises/keywords
    $name = strtolower($game['name']);
    $popularKeywords = [
        'halo', 'mass effect', 'elder scrolls', 'skyrim', 'fallout', 'grand theft auto', 'gta',
        'call of duty', 'assassins creed', 'witcher', 'bioshock', 'dishonored', 'cities skylines',
        'civilization', 'total war', 'command conquer', 'starcraft', 'diablo', 'overwatch',
        'destiny', 'borderlands', 'far cry', 'watch dogs', 'metro', 'stalker', 'half life',
        'portal', 'left 4 dead', 'team fortress', 'counter strike', 'dota', 'league of legends'
    ];
    
    foreach ($popularKeywords as $keyword) {
        if (strpos($name, $keyword) !== false) {
            $score += 15;
            break;
        }
    }
    
    // Recent release bonus (games from last few years get slight boost)
    $releaseYear = $game['released'] ? (int)date('Y', strtotime($game['released'])) : 0;
    $currentYear = (int)date('Y');
    if ($releaseYear >= $currentYear - 2) {
        $score += 5;
    } else if ($releaseYear >= $currentYear - 5) {
        $score += 3;
    }
    
    return $score;
}

function insertPendingGame($game) {
    global $conn;
    
    try {
        error_log("Starting insertPendingGame for game: " . ($game['name'] ?? 'N/A') . " (ID: " . ($game['id'] ?? 'N/A') . ")");
        
        if (!isset($game['id']) || !isset($game['name'])) {
            error_log("Missing required game data - ID: " . ($game['id'] ?? 'NULL') . ", Name: " . ($game['name'] ?? 'NULL'));
            throw new Exception("Missing required game data");
        }
        
        // First, let's verify our table structure
        $checkTableSql = "DESCRIBE pending_games";
        $result = $conn->query($checkTableSql);
        if (!$result) {
            error_log("Failed to check table structure: " . $conn->error);
            throw new Exception("Failed to check table structure: " . $conn->error);
        }
        error_log("Table structure verified");

        // Prepare the insert statement with explicit column names
    $stmt = $conn->prepare("
        INSERT IGNORE INTO pending_games 
        (rawg_id, name, rating, ratings_count, metacritic_score, popularity_score, 
         release_date, background_image, description, publishers, platforms, genres, 
         status, is_dlc, parent_game_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // Extract and validate all values
        $values = [
            'rawg_id' => (int)$game['id'],
            'name' => $game['name'],
            'rating' => isset($game['rating']) ? (float)$game['rating'] : 0.0,
            'ratings_count' => isset($game['ratings_count']) ? (int)$game['ratings_count'] : 0,
            'metacritic_score' => isset($game['metacritic']) ? (int)$game['metacritic'] : 0,
            'popularity_score' => calculateGameScore($game),
            'release_date' => isset($game['released']) ? $game['released'] : null,
            'background_image' => isset($game['background_image']) ? $game['background_image'] : '',
            'description' => isset($game['description']) ? substr(trim(strip_tags($game['description'])), 0, 65535) : '',
            'publishers' => '',
            'platforms' => '',
            'genres' => '',
            'status' => 'pending',
            'is_dlc' => isset($game['is_dlc']) ? (bool)$game['is_dlc'] : false,
            'parent_game_id' => isset($game['parent_game_id']) ? (int)$game['parent_game_id'] : null
        ];
        
        // Process publishers
    if (isset($game['publishers']) && is_array($game['publishers'])) {
            $values['publishers'] = implode(', ', array_map(function($pub) {
                return $pub['name'] ?? '';
            }, $game['publishers']));
        }
    
        // Process platforms
    if (isset($game['platforms']) && is_array($game['platforms'])) {
            $values['platforms'] = implode(', ', array_map(function($plat) {
                return $plat['platform']['name'] ?? '';
            }, $game['platforms']));
        }
        
        // Process genres
    if (isset($game['genres']) && is_array($game['genres'])) {
            $values['genres'] = implode(', ', array_map(function($genre) {
                return $genre['name'] ?? '';
            }, $game['genres']));
        }
        
        error_log("Binding values: " . json_encode($values));
        
        // Bind all parameters
        $stmt->bind_param('isddidsssssssii',
            $values['rawg_id'],
            $values['name'],
            $values['rating'],
            $values['ratings_count'],
            $values['metacritic_score'],
            $values['popularity_score'],
            $values['release_date'],
            $values['background_image'],
            $values['description'],
            $values['publishers'],
            $values['platforms'],
            $values['genres'],
            $values['status'],
            $values['is_dlc'],
            $values['parent_game_id']
        );
        
        // Execute and check result
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $affectedRows = $stmt->affected_rows;
        error_log("Execute successful. Affected rows: $affectedRows");
        
        if ($affectedRows > 0) {
            error_log("Successfully inserted game: {$values['name']} (ID: {$values['rawg_id']})");
            return true;
        } else {
            error_log("No rows affected - game may already exist: {$values['name']} (ID: {$values['rawg_id']})");
            return false;
        }
    } catch (Exception $e) {
        error_log("Error in insertPendingGame: " . $e->getMessage());
        throw $e;
    }
}

function getApprovalStats() {
    global $conn;
    
    $stats = [];
    
    // Pending count
    $result = $conn->query("SELECT COUNT(*) FROM pending_games WHERE status = 'pending'");
    $stats['pending'] = $result->fetch_row()[0];
    
    // Approved today
    $result = $conn->query("SELECT COUNT(*) FROM pending_games WHERE status = 'approved' AND DATE(reviewed_at) = CURDATE()");
    $stats['approved_today'] = $result->fetch_row()[0];
    
    // Rejected today
    $result = $conn->query("SELECT COUNT(*) FROM pending_games WHERE status = 'rejected' AND DATE(reviewed_at) = CURDATE()");
    $stats['rejected_today'] = $result->fetch_row()[0];
    
    // Total games in main table
    $result = $conn->query("SELECT COUNT(*) FROM games");
    $stats['total'] = $result->fetch_row()[0];
    
    return $stats;
}

function searchGames() {
    global $conn;
    
    $searchTerm = $_POST['search_term'] ?? '';
    $page = (int)($_POST['page'] ?? 1);
    
    if (empty($searchTerm)) {
        return ['success' => true, 'games' => [], 'current_page' => $page, 'total_pages' => 1, 'total_games' => 0];
    }
    
    try {
        // Search RAWG API
        $apiKey = '58aed2d9aedd4274ab81d91356e775f2';
        $url = "https://api.rawg.io/api/games?" . http_build_query([
            'search' => $searchTerm,
            'page_size' => 20,
            'page' => $page,
            'key' => $apiKey
        ]);
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'GameTracker/1.0'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new Exception("Failed to fetch from RAWG API");
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to parse RAWG API response");
        }
        
        if (!isset($data['results']) || !is_array($data['results'])) {
            throw new Exception("Invalid response structure from RAWG API");
        }
        
        $games = [];
        foreach ($data['results'] as $game) {
            // Check if this is a DLC
            $isDlc = false;
            $parentGameInfo = null;
            $canApprove = true;
            $approvalWarning = '';
            
            // Fetch full game details including Metacritic score
            try {
                $gameDetails = fetchGameDetails($game['id'], $apiKey);
                $metacriticScore = $gameDetails['metacritic'] ?? null;
                
                // Enhanced DLC detection using multiple methods
                $isDlc = detectIfDlc($gameDetails);
                $parentGame = findParentGame($gameDetails);
                

                
                if ($isDlc && $parentGame) {
                    // Check if parent game exists in your database
                    if ($parentGame['id'] !== null) {
                        // We have a RAWG ID, search by that
                        $parentStmt = $conn->prepare("SELECT id, title FROM games WHERE rawg_id = ?");
                        $parentStmt->bind_param("i", $parentGame['id']);
                        $parentStmt->execute();
                        $parentGameInDb = $parentStmt->get_result()->fetch_assoc();
                    } else {
                        // We extracted parent name from title, search by title
                        // First try exact match
                        $parentStmt = $conn->prepare("SELECT id, title FROM games WHERE title = ?");
                        $parentStmt->bind_param("s", $parentGame['name']);
                        $parentStmt->execute();
                        $parentGameInDb = $parentStmt->get_result()->fetch_assoc();
                        
                        // If no exact match, try partial match but prefer longer/more specific titles
                        if (!$parentGameInDb) {
                            $parentStmt = $conn->prepare("SELECT id, title FROM games WHERE title LIKE ? ORDER BY LENGTH(title) DESC LIMIT 1");
                            $parentPattern = $parentGame['name'] . '%';
                            $parentStmt->bind_param("s", $parentPattern);
                            $parentStmt->execute();
                            $parentGameInDb = $parentStmt->get_result()->fetch_assoc();
                        }
                        
                        // If still no match, try common variations
                        if (!$parentGameInDb) {
                            $variations = [];
                            
                            // Handle number to Roman numeral variations (e.g., "3" vs "III")
                            $title = $parentGame['name'];
                            if (preg_match('/\b(\d+)\b/', $title, $matches)) {
                                $number = $matches[1];
                                $romanNumeral = numberToRoman($number);
                                if ($romanNumeral) {
                                    $variations[] = preg_replace('/\b' . $number . '\b/', $romanNumeral, $title);
                                }
                            }
                            
                            // Handle Roman numeral to number variations (e.g., "III" vs "3")
                            if (preg_match('/\b([IVX]+)\b/', $title, $matches)) {
                                $romanNumeral = $matches[1];
                                $number = romanToNumber($romanNumeral);
                                if ($number) {
                                    $variations[] = preg_replace('/\b' . $romanNumeral . '\b/', $number, $title);
                                }
                            }
                            
                            // Try each variation
                            foreach ($variations as $variation) {
                                $parentStmt = $conn->prepare("SELECT id, title FROM games WHERE title LIKE ? ORDER BY LENGTH(title) DESC LIMIT 1");
                                $parentPattern = $variation . '%';
                                $parentStmt->bind_param("s", $parentPattern);
                                $parentStmt->execute();
                                $parentGameInDb = $parentStmt->get_result()->fetch_assoc();
                                
                                if ($parentGameInDb) {
                                    break;
                                }
                            }
                        }
                    }
                    
                    if (!$parentGameInDb) {
                        $canApprove = false;
                        $approvalWarning = "⚠️ Parent game '{$parentGame['name']}' not in database";
                    } else {
                        $parentGameInfo = $parentGameInDb;
                    }
                } elseif ($isDlc && !$parentGame) {
                    // DLC detected but no parent game found
                    $canApprove = false;
                    $approvalWarning = "⚠️ DLC detected but parent game information not available";
                }
            } catch (Exception $e) {
                error_log("Failed to fetch details for game {$game['id']}: " . $e->getMessage());
                $metacriticScore = null;
            }
            
            // Check if game/DLC already exists
            $existingStmt = $conn->prepare("SELECT COUNT(*) FROM pending_games WHERE rawg_id = ? UNION SELECT COUNT(*) FROM games WHERE rawg_id = ? UNION SELECT COUNT(*) FROM dlcs WHERE rawg_id = ?");
            $existingStmt->bind_param("iii", $game['id'], $game['id'], $game['id']);
            $existingStmt->execute();
            $exists = $existingStmt->get_result()->fetch_row()[0] > 0;
            
            $games[] = [
                'rawg_id' => $game['id'],
                'name' => $game['name'],
                'background_image' => $game['background_image'] ?? '',
                'description' => $game['description'] ?? '',
                'rating' => $game['rating'] ?? 0,
                'ratings_count' => $game['ratings_count'] ?? 0,
                'released' => $game['released'] ?? '',
                'metacritic_score' => $metacriticScore,
                'already_exists' => $exists,
                'is_dlc' => $isDlc,
                'parent_game_info' => $parentGameInfo,
                'can_approve' => $canApprove,
                'approval_warning' => $approvalWarning,
                'debug_rawg_data' => $gameDetails,  // Full detailed game data for debugging
                'debug_detection' => [
                    'has_parent_game' => isset($gameDetails['parent_game']),
                    'parent_game_data' => $gameDetails['parent_game'] ?? null,
                    'has_tags' => isset($gameDetails['tags']),
                    'tags' => $gameDetails['tags'] ?? [],
                    'has_genres' => isset($gameDetails['genres']),
                    'genres' => $gameDetails['genres'] ?? []
                ]
            ];
        }
        
        return [
            'success' => true,
            'games' => $games,
            'current_page' => $page,
            'total_pages' => ceil(($data['count'] ?? 0) / 20),
            'total_games' => $data['count'] ?? 0
        ];
        
    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function detectIfDlc($gameData) {
    // Method 1: Check for parents_count (most reliable - if it has parents, it's likely a DLC)
    if (isset($gameData['parents_count']) && $gameData['parents_count'] > 0) {
        // But exclude VR versions - they are standalone games, not DLCs
        $name = strtolower($gameData['name'] ?? '');
        if (strpos($name, ' vr') !== false || strpos($name, 'virtual reality') !== false) {
            return false;
        }
        return true;
    }
    
    // Method 2: Check for explicit parent_game field (fallback)
    if (isset($gameData['parent_game']) && !empty($gameData['parent_game'])) {
        return true;
    }
    
    // Method 3: Check game name for DLC keywords
    $name = strtolower($gameData['name'] ?? '');
    $dlcKeywords = ['dlc', 'expansion', 'add-on', 'addon', 'pack', 'season pass', 'episode', 'chapter'];
    
    foreach ($dlcKeywords as $keyword) {
        if (strpos($name, $keyword) !== false) {
            return true;
        }
    }
    
    // Method 4: Check tags for DLC indicators
    if (isset($gameData['tags']) && is_array($gameData['tags'])) {
        foreach ($gameData['tags'] as $tag) {
            $tagName = strtolower($tag['name'] ?? '');
            if (in_array($tagName, ['dlc', 'expansion', 'add-on', 'downloadable content'])) {
                return true;
            }
        }
    }
    
    // Method 5: Check genres for DLC-specific genres
    if (isset($gameData['genres']) && is_array($gameData['genres'])) {
        foreach ($gameData['genres'] as $genre) {
            $genreName = strtolower($genre['name'] ?? '');
            if (strpos($genreName, 'expansion') !== false || strpos($genreName, 'dlc') !== false) {
                return true;
            }
        }
    }
    
    return false;
}

function findParentGame($gameData) {
    // Method 1: Direct parent_game field (if available)
    if (isset($gameData['parent_game']) && !empty($gameData['parent_game'])) {
        return $gameData['parent_game'];
    }
    
    // Method 2: If it has parents_count > 0, extract parent from title
    if (isset($gameData['parents_count']) && $gameData['parents_count'] > 0) {
        $title = $gameData['name'];
        
        // Common DLC patterns: "Game Name - DLC Name" or "Game Name: DLC Name"
        if (preg_match('/^(.+?)\s*[-:]\s*(.+)$/', $title, $matches)) {
            $parentGameTitle = trim($matches[1]);
            $dlcPart = trim($matches[2]);
            
            // Make sure the DLC part is substantial
            if (strlen($dlcPart) > 2) {
                return [
                    'id' => null, // We don't have the RAWG ID
                    'name' => $parentGameTitle,
                    'slug' => strtolower(str_replace(' ', '-', $parentGameTitle))
                ];
            }
        }
    }
    
    // Method 3: Check if there's a "main_game" field (some APIs use this)
    if (isset($gameData['main_game']) && !empty($gameData['main_game'])) {
        return $gameData['main_game'];
    }
    
    // Method 4: Check series information for parent
    if (isset($gameData['series']) && !empty($gameData['series'])) {
        // Sometimes the parent game is the main series entry
        return $gameData['series'];
    }
    
    return null;
}

// Create pending_games table if it doesn't exist
$createTableSql = "
CREATE TABLE IF NOT EXISTS pending_games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rawg_id INT UNIQUE,
    name VARCHAR(255),
    rating DECIMAL(3,2),
    ratings_count INT,
    metacritic_score INT,
    popularity_score DECIMAL(5,2),
    release_date DATE,
    background_image TEXT,
    description TEXT,
    publishers TEXT,
    platforms TEXT,
    genres TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_popularity (popularity_score DESC)
)";
$conn->query($createTableSql);

// Get initial stats for display
$pendingCount = $conn->query("SELECT COUNT(*) FROM pending_games WHERE status = 'pending'")->fetch_row()[0];
$approvedToday = $conn->query("SELECT COUNT(*) FROM pending_games WHERE status = 'approved' AND DATE(reviewed_at) = CURDATE()")->fetch_row()[0];
$rejectedToday = $conn->query("SELECT COUNT(*) FROM pending_games WHERE status = 'rejected' AND DATE(reviewed_at) = CURDATE()")->fetch_row()[0];
$totalGames = $conn->query("SELECT COUNT(*) FROM games")->fetch_row()[0];

function updateGameDetails() {
    global $conn;
    
    $rawgId = (int)$_POST['rawg_id'];
    
    try {
        // Fetch latest game details from RAWG
        $apiKey = '58aed2d9aedd4274ab81d91356e775f2';
        $gameDetails = fetchGameDetails($rawgId, $apiKey);
        
        // Process platforms and genres arrays into comma-separated strings
        $platforms = '';
        if (isset($gameDetails['platforms']) && is_array($gameDetails['platforms'])) {
            $platformNames = [];
            foreach ($gameDetails['platforms'] as $platform) {
                if (isset($platform['platform']['name'])) {
                    $platformNames[] = $platform['platform']['name'];
                }
            }
            $platforms = implode(', ', $platformNames);
        }
        
        $genres = '';
        if (isset($gameDetails['genres']) && is_array($gameDetails['genres'])) {
            $genreNames = [];
            foreach ($gameDetails['genres'] as $genre) {
                if (isset($genre['name'])) {
                    $genreNames[] = $genre['name'];
                }
            }
            $genres = implode(', ', $genreNames);
        }
        
        $publishers = '';
        if (isset($gameDetails['publishers']) && is_array($gameDetails['publishers'])) {
            $publisherNames = [];
            foreach ($gameDetails['publishers'] as $publisher) {
                if (isset($publisher['name'])) {
                    $publisherNames[] = $publisher['name'];
                }
            }
            $publishers = implode(', ', $publisherNames);
        }
        
        // Update the game in pending_games
        $stmt = $conn->prepare("
            UPDATE pending_games 
            SET 
                name = ?,
                rating = ?,
                ratings_count = ?,
                metacritic_score = ?,
                popularity_score = ?,
                release_date = ?,
                background_image = ?,
                description = ?,
                publishers = ?,
                platforms = ?,
                genres = ?
            WHERE rawg_id = ?
        ");
        
        $stmt->bind_param("sssssssssssi",
            $gameDetails['name'],
            $gameDetails['rating'],
            $gameDetails['ratings_count'],
            $gameDetails['metacritic'],
            $gameDetails['popularity_score'],
            $gameDetails['released'],
            $gameDetails['background_image'],
            $gameDetails['description'],
            $publishers,
            $platforms,
            $genres,
            $rawgId
        );
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Game details updated successfully'];
        } else {
            return ['success' => false, 'error' => 'Failed to update game details: ' . $stmt->error];
        }
    } catch (Exception $e) {
        error_log("Update game error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Create pending_games table if it doesn't exist
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Approval - GameTracker Admin</title>
    
    <!-- Test script -->
    <script>
        console.log('Head script loaded');
        
        // Global error handler
        window.onerror = function(msg, url, lineNo, columnNo, error) {
            console.error('Error: ' + msg);
            console.error('URL: ' + url);
            console.error('Line: ' + lineNo);
            console.error('Column: ' + columnNo);
            console.error('Error object: ', error);
            return false;
        };
    </script>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/styles.css">
    
    <style>
        :root {
            --primary-color: #b200ff;
        }

        .hero-section {
            position: relative;
            padding: 6rem 0 4rem;
            background: linear-gradient(to right, rgba(21, 21, 30, 0.9), rgba(21, 21, 30, 0.7));
            border-bottom: 1px solid rgba(127, 0, 255, 0.3);
            margin-bottom: 2rem;
        }

        .hero-section h1 {
            font-family: 'Orbitron', sans-serif;
            color: var(--primary-color);
        }

        .stats-row {
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #1e1e2f;
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(127, 0, 255, 0.5);
            box-shadow: 0 0 20px rgba(178, 0, 255, 0.3);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            font-family: 'Orbitron', sans-serif;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #a8a8b3;
            font-size: 0.95rem;
        }

        .approval-game-card {
            background: #1e1e2f;
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            margin-bottom: 1.5rem;
        }

        .approval-game-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0 20px rgba(178, 0, 255, 0.5);
            border-color: rgba(127, 0, 255, 0.5);
        }

        .game-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            min-height: 250px;
        }

        .game-info {
            padding: 1.5rem;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .game-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.25rem;
            color: #ffffff;
            margin-bottom: 1rem;
            line-height: 1.4;
        }

        .game-meta {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .meta-item {
            background: rgba(30, 30, 47, 0.5);
            padding: 0.35rem;
            border-radius: 6px;
            text-align: center;
            border: 1px solid rgba(127, 0, 255, 0.1);
            min-width: 80px;
        }

        .meta-label {
            font-size: 0.75rem;
            color: #a8a8b3;
            margin-bottom: 0.15rem;
        }

        .meta-value {
            font-weight: bold;
            color: #ffffff;
            font-size: 0.85rem;
        }

        .rating-high { color: #28a745; }
        .rating-medium { color: #ffc107; }
        .rating-low { color: #dc3545; }

        .status-pending { color: #ffc107; }  /* Warning yellow */
        .status-approved { color: #28a745; } /* Success green */
        .status-rejected { color: #dc3545; } /* Danger red */

        .game-description {
            color: #a8a8b3;
            font-size: 0.9rem;
            line-height: 1.4;
            margin-bottom: 1rem;
            max-height: 80px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        .game-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .tag {
            background: rgba(178, 0, 255, 0.1);
            border: 1px solid rgba(178, 0, 255, 0.2);
            color: #ffffff;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            white-space: nowrap;
        }

        .tag-genre {
            background: rgba(255, 178, 0, 0.1);
            border-color: rgba(255, 178, 0, 0.2);
        }

        .form-check {
            padding: 1rem;
        }

        .form-check-input {
            width: 1.5rem;
            height: 1.5rem;
            cursor: pointer;
            border-color: rgba(127, 0, 255, 0.5);
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-input:focus {
            box-shadow: 0 0 0 0.0rem rgba(127, 0, 255, 0.25);
        }

        .form-check-label {
            font-size: 1rem;
            color: #ffffff;
            margin-left: 0.5rem;
        }

        .game-actions {
            display: flex;
            gap: 0.75rem;
        }

        .game-actions .btn {
            flex: 1;
        }

        .bulk-actions {
            background: #1e1e2f;
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .bulk-actions .form-check {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background: rgba(127, 0, 255, 0.1);
            border: 1px solid rgba(127, 0, 255, 0.2);
            border-radius: 8px;
            margin: 0;
            height: 38px; /* Match button height */
        }

        .bulk-actions .form-check-input {
            margin: 0;
        }

        .bulk-actions .form-check-label {
            margin: 0 0 0 0.5rem;
            color: #ffffff;
            font-size: 0.9rem;
        }

        #selectedCount {
            padding: 0.5rem 1rem;
            background: rgba(127, 0, 255, 0.1);
            border: 1px solid rgba(127, 0, 255, 0.2);
            border-radius: 8px;
            color: #ffffff;
            font-size: 0.9rem;
        }

        .bulk-actions .d-flex {
            gap: 1rem !important;
        }

        .loading-spinner {
            text-align: center;
            padding: 3rem;
            color: #a8a8b3;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: #1e1e2f;
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            opacity: 0.6;
        }

        .pagination {
            margin: 0;
            gap: 0.25rem;
        }

        .pagination .page-link {
            background: rgba(127, 0, 255, 0.1);
            border: 1px solid rgba(127, 0, 255, 0.2);
            color: #ffffff;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .pagination .page-link:hover {
            background: rgba(127, 0, 255, 0.2);
            border-color: rgba(127, 0, 255, 0.3);
        }

        .pagination .page-item.active .page-link {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: #ffffff;
        }

        .pagination .page-item.disabled .page-link {
            background: rgba(127, 0, 255, 0.05);
            border-color: rgba(127, 0, 255, 0.1);
            color: rgba(255, 255, 255, 0.5);
            cursor: not-allowed;
        }

        .pagination-info {
            color: #a8a8b3;
            font-size: 0.9rem;
            margin-top: 1rem;
        }

        @media (max-width: 768px) {
            .hero-section {
                padding: 4rem 0 2rem;
                text-align: center;
            }

            .hero-section h1 {
                font-size: 2.5rem;
            }

            .game-meta {
                grid-template-columns: repeat(2, 1fr);
            }

            .game-image {
                height: 200px;
                min-height: auto;
            }

            .form-check {
                padding: 0.5rem;
                position: absolute;
                top: 0.5rem;
                right: 0.5rem;
                background: rgba(30, 30, 47, 0.8);
                border-radius: 50%;
            }

            .game-actions {
                flex-direction: column;
            }

            .bulk-actions {
                padding: 1rem;
            }
        }

        .game-select-area {
            background: rgba(30, 30, 47, 0.5);
            border-left: 1px solid rgba(127, 0, 255, 0.1);
            margin: 0;
            min-height: 100%;
            transition: all 0.2s ease;
            cursor: pointer;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .game-select-area:hover {
            background: rgba(30, 30, 47, 0.8);
        }

        .game-select-area .form-check-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            cursor: pointer;
            z-index: 2;
        }

        .game-select-area::after {
            content: '\F26B'; /* Bootstrap Icons: Check-lg */
            font-family: "bootstrap-icons";
            font-size: 2rem;
            color: rgba(178, 0, 255, 0.4);
            pointer-events: none;
            transition: all 0.2s ease;
        }

        .game-select-area.checked {
            background: rgba(178, 0, 255, 0.3);
        }

        .game-select-area.checked::after {
            color: white;
        }

        .game-select-area:hover::after {
            color: rgba(178, 0, 255, 0.7);
        }

        /* Add a subtle pulse animation to the unselected state */
        @keyframes subtlePulse {
            0% { opacity: 0.5; }
            50% { opacity: 0.7; }
            100% { opacity: 0.5; }
        }

        .game-select-area:not(.checked)::after {
            animation: subtlePulse 2s infinite;
        }

        .game-type-buttons {
    width: 100%;
    height: 38px;
}

.game-type-buttons .btn-check {
    position: absolute;
    clip: rect(0, 0, 0, 0);
    pointer-events: none;
}

.game-type-buttons .btn {
    position: relative;
    background: transparent;
    border: 1px solid #0dcaf0;
    color: #0dcaf0;
    height: 38px;
    flex: 1;
    margin: 0;
    font-size: 1rem;
    padding: 0 0.75rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.game-type-buttons .btn:hover {
    background: #0dcaf0;
    color: #000;
}

.game-type-buttons .btn-check:checked + .btn {
    background: #0dcaf0;
    color: #000;
    border-color: #0dcaf0;
}

.game-type-buttons .btn:first-child {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
    border-right: none;
}

.game-type-buttons .btn:last-child {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

.filter-row .form-select,
.filter-row .btn {
    height: 38px;
    line-height: 1.5;
}
    </style>
</head>
<body>
<?php include '../includes/nav.php'; ?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto text-center">
                <h1 class="display-4 mb-3">Game Approval Center</h1>
                <p class="lead">Review and approve games before they appear on GameTracker. Filter high-quality titles and maintain site standards.</p>
            </div>
        </div>
    </div>
</section>

<div class="container py-4">
    <!-- Stats Dashboard -->
    <div class="row stats-row g-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-number" id="pendingCount"><?= $pendingCount ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-number" id="approvedCount"><?= $approvedToday ?></div>
                <div class="stat-label">Approved Today</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-number" id="rejectedCount"><?= $rejectedToday ?></div>
                <div class="stat-label">Rejected Today</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-number" id="totalCount"><?= $totalGames ?></div>
                <div class="stat-label">Total Games</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-container">
        <div class="row g-3">
            <div class="col-md-2">
                <label for="statusFilter" class="form-label">Status</label>
                <select id="statusFilter" class="form-select custom-select">
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="all">All</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="ratingFilter" class="form-label">Min Rating</label>
                <select id="ratingFilter" class="form-select custom-select">
                    <option value="0">All Ratings</option>
                    <option value="3.5">3.5+</option>
                    <option value="4.0">4.0+</option>
                    <option value="4.5">4.5+</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="reviewsFilter" class="form-label">Min Reviews</label>
                <select id="reviewsFilter" class="form-select custom-select">
                    <option value="0">All</option>
                    <option value="100">100+</option>
                    <option value="1000">1000+</option>
                    <option value="5000">5000+</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="yearFilter" class="form-label">Year</label>
                <select id="yearFilter" class="form-select custom-select">
                    <option value="">All Years</option>
                    <option value="2024">2024</option>
                    <option value="2023">2023</option>
                    <option value="2022">2022</option>
                    <option value="2021">2021</option>
                    <option value="2020">2020</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" onclick="applyFilters()">
                    <i class="ph ph-funnel me-2"></i>Apply
                </button>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-1">
                <button type="button" class="btn btn-outline-light flex-grow-1" id="fetchReleasedButton" data-game-type="released" title="Fetch released games (2001 – today)">
                    <i class="ph ph-calendar-check me-1"></i>Fetch Released
                </button>
                <button type="button" class="btn btn-outline-info flex-grow-1" id="fetchUpcomingButton" data-game-type="upcoming" title="Fetch upcoming games (today – 2030)">
                    <i class="ph ph-calendar-plus me-1"></i>Fetch Upcoming
                </button>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-outline-info w-100" id="searchGameButton" data-bs-toggle="modal" data-bs-target="#searchGameModal">
                    <i class="ph ph-magnifying-glass me-2"></i>Search Game
                </button>
            </div>
            <div class="col-md-6"></div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="btn-group game-type-buttons" role="group" aria-label="Game Type">
                    <input type="radio" class="btn-check" name="gameType" id="releasedGames" value="released" checked>
                    <label class="btn btn-outline-info" for="releasedGames">
                        <i class="ph ph-calendar-check-fill me-2"></i>Released Games
                    </label>
                    
                    <input type="radio" class="btn-check" name="gameType" id="upcomingGames" value="upcoming">
                    <label class="btn btn-outline-info" for="upcomingGames">
                        <i class="ph ph-calendar-plus-fill me-2"></i>Upcoming Games
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="btn btn-warning mb-4" role="alert" style="cursor: default;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Developer Note:</strong> If "0 games found" when fetching, increase --$maxAttempts = 10-- in fetchNewGamesFromRAWG function - <strong>DELETE AFTER REVIEW!</strong>
    </div>
    <!-- Bulk Actions -->
    <div class="bulk-actions">
        <div class="d-flex align-items-center">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                <label class="form-check-label" for="selectAll">Select All</label>
            </div>
            <button type="button" class="btn btn-success" onclick="window.bulkApprove()">
                <i class="bi bi-check-circle me-2"></i>Approve Selected
            </button>
            <button type="button" class="btn btn-danger" onclick="window.bulkReject()">
                <i class="bi bi-x-circle me-2"></i>Reject Selected
            </button>
            <button type="button" class="btn btn-warning" onclick="window.bulkRemove()">
                <i class="bi bi-trash me-2"></i>Remove Selected
            </button>
            <span id="selectedCount" class="ms-auto me-3">0 selected</span>
            <div class="btn-group" role="group" aria-label="Status filters">
                <button type="button" class="btn btn-outline-warning active" onclick="filterByStatus('pending')">
                    <i class="bi bi-clock-history me-1"></i>Pending
                </button>
                <button type="button" class="btn btn-outline-success" onclick="filterByStatus('approved')">
                    <i class="bi bi-check-circle me-1"></i>Approved
                </button>
                <button type="button" class="btn btn-outline-danger" onclick="filterByStatus('rejected')">
                    <i class="bi bi-x-circle me-1"></i>Rejected
                </button>
            </div>
        </div>
    </div>

    <!-- Games Container -->
    <div id="gamesContainer">
        <div class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Loading games...</p>
        </div>
    </div>

    <!-- Pagination -->
    <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination justify-content-center" id="pagination"></ul>
    </nav>
</div>

<!-- Search Game Modal -->
<div class="modal fade" id="searchGameModal" tabindex="-1" aria-labelledby="searchGameModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="searchGameModalLabel">
                    <i class="ph ph-magnifying-glass me-2"></i>Search for a Game
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="gameSearchInput" class="form-label">Game Title</label>
                    <div class="input-group">
                        <input type="text" class="form-control bg-dark text-light border-secondary" id="gameSearchInput" placeholder="Enter game title (e.g., 'The Finals', 'Cyberpunk 2077')">
                        <button class="btn btn-primary" type="button" id="searchGameBtn">
                            <i class="ph ph-magnifying-glass me-2"></i>Search
                        </button>
                    </div>
                </div>
                
                <div id="searchResults" class="mt-3" style="display: none;">
                    <h6 class="text-light mb-3">Search Results:</h6>
                    <div id="searchResultsList"></div>
                </div>
                
                <div id="searchLoading" class="text-center mt-3" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Searching...</span>
                    </div>
                    <p class="mt-2">Searching for games...</p>
                </div>
                
                <div id="searchError" class="alert alert-danger mt-3" style="display: none;"></div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// Wrap all JavaScript in try-catch
try {
    console.log('Main script starting');
    
    // Global variables
let currentPage = 1;
let totalPages = 1;
let selectedGames = new Set();

    // Make functions globally available
    window.bulkApprove = async function() {
        await bulkAction('approved');
    }

    window.bulkReject = async function() {
        await bulkAction('rejected');
    }

    window.bulkRemove = async function() {
        await bulkAction('removed');
    }

    window.addGameToPending = async function(rawgId, gameName) {
        try {
            // Directly fetch and add the game
            await fetchAndAddGame(rawgId, gameName);
        } catch (error) {
            console.error('Error adding game to pending:', error);
            showNotification('Failed to add game to pending list', 'error');
        }
    }

    window.viewGameDetails = function(rawgId) {
        // Open RAWG page in new tab
        window.open(`https://rawg.io/games/${rawgId}`, '_blank');
    }

    // Define all functions first
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 5000);
    }

    async function bulkApprove() {
        await bulkAction('approved');
    }

    async function bulkReject() {
        await bulkAction('rejected');
    }

    async function bulkAction(status) {
        const selectedIds = Array.from(selectedGames);
        if (selectedIds.length === 0) {
            showNotification('Please select at least one game', 'warning');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'bulk_review');
            formData.append('game_ids', JSON.stringify(selectedIds));
            formData.append('status', status);

            const response = await fetch('game-approval.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                showNotification(`Successfully ${status} ${selectedIds.length} games`, 'success');
                selectedGames.clear();
                updateSelectedCount();
                loadGames(1); // Reload first page
                updateStats(); // Update the stats display
            } else {
                throw new Error(data.error || 'Failed to update games');
            }
        } catch (error) {
            console.error('Error in bulk action:', error);
            showNotification(error.message, 'error');
        }
    }

async function loadGames(page = 1) {
        console.log('loadGames called with page:', page);
    const container = document.getElementById('gamesContainer');
        const gamesPerPage = 10; // Show 10 games per page

    try {
        const formData = new FormData();
        formData.append('action', 'get_games');
        formData.append('page', 1); // Get all games at once
        formData.append('status', document.getElementById('statusFilter').value);
        formData.append('min_rating', document.getElementById('ratingFilter').value);
        formData.append('min_reviews', document.getElementById('reviewsFilter').value);
        formData.append('year', document.getElementById('yearFilter').value);

        console.log('Fetching games...');
        const response = await fetch('game-approval.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        console.log('Games data received:', data);

        if (data.success && data.games && data.games.length > 0) {
                // Calculate pagination based on actual games array length
                const allGames = data.games;
                const totalGames = allGames.length; // This should be 40
                const totalPages = Math.ceil(totalGames / gamesPerPage); // Should be 4 for 40 games
                const startIndex = (page - 1) * gamesPerPage;
                const endIndex = Math.min(startIndex + gamesPerPage, totalGames);
                const currentPageGames = allGames.slice(startIndex, endIndex);

                console.log('Client-side pagination:', {
                    actualTotalGames: totalGames,
                    gamesPerPage,
                    calculatedPages: totalPages,
                    startIndex,
                    endIndex,
                    currentPage: page,
                    gamesOnCurrentPage: currentPageGames.length
                });

                // Render games for current page
                container.innerHTML = currentPageGames.map(game => `
        <div class="approval-game-card">
            <div class="row g-0">
                <div class="col-md-4">
                    <img src="${game.background_image || '../images/logo.png'}" 
                         class="game-image" alt="${game.name}"
                         onerror="this.src='../images/logo.png'">
                </div>
                            <div class="col-md-7">
                    <div class="game-info">
                                    <h3 class="game-title">${game.name}</h3>
                        <div class="game-meta">
                            <div class="meta-item">
                                <div class="meta-label">Rating</div>
                                <div class="meta-value ${getRatingClass(game.rating)}">${game.rating || 'N/A'}</div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Reviews</div>
                                <div class="meta-value">${game.ratings_count || 0}</div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Metacritic</div>
                                <div class="meta-value ${getMetacriticClass(game.metacritic_score)}">${game.metacritic_score || 'N/A'}</div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Status</div>
                                <div class="meta-value ${getStatusClass(game.status)}">${capitalizeFirst(game.status)}</div>
                            </div>
                            </div>
                                    <div class="game-description">${game.description || ''}</div>
                                    <div class="game-tags">
                                        ${game.platforms ? game.platforms.split(',').map(platform => 
                                            `<span class="tag">${platform.trim()}</span>`
                                        ).join('') : ''}
                            </div>
                        <div class="game-tags">
                                        ${game.genres ? game.genres.split(',').map(genre => 
                                            `<span class="tag tag-genre">${genre.trim()}</span>`
                            ).join('') : ''}
                        </div>
                        </div>
                            </div>
                            <div class="col-md-1 p-0">
                                <div class="form-check game-select-area h-100 m-0 ${selectedGames.has(game.rawg_id) ? 'checked' : ''}">
                                    <input class="form-check-input game-checkbox" 
                                           type="checkbox" 
                                           value="${game.rawg_id}" 
                                           id="game-${game.rawg_id}"
                                           onchange="handleCheckboxChange(this)"
                                           ${selectedGames.has(game.rawg_id) ? 'checked' : ''}>
                                </div>
                            </div>
                        </div>
                    </div>
    `).join('');

                // Create pagination controls
                const paginationHtml = `
                    <nav aria-label="Game list navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item ${page === 1 ? 'disabled' : ''}">
                                <button class="page-link" data-page="${page - 1}" ${page === 1 ? 'disabled' : ''}>
                                    <i class="bi bi-chevron-left"></i>
                                </button>
                            </li>
                            ${Array.from({length: totalPages}, (_, i) => i + 1).map(pageNum => `
                                <li class="page-item ${pageNum === page ? 'active' : ''}">
                                    <button class="page-link" data-page="${pageNum}">
                                        ${pageNum}
                                    </button>
                                </li>
                            `).join('')}
                            <li class="page-item ${page === totalPages ? 'disabled' : ''}">
                                <button class="page-link" data-page="${page + 1}" ${page === totalPages ? 'disabled' : ''}>
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </li>
                        </ul>
                        <div class="text-center mt-2">
                            Showing ${startIndex + 1}-${endIndex} of ${totalGames} games
                        </div>
                    </nav>
                `;
                
                // Add pagination after the games container
                const paginationContainer = document.getElementById('pagination');
                if (paginationContainer) {
                    paginationContainer.innerHTML = paginationHtml;

                    // Add click handlers to all pagination buttons
                    paginationContainer.querySelectorAll('.page-link').forEach(button => {
                        button.addEventListener('click', (e) => {
                            if (!button.disabled) {
                                const pageNum = parseInt(button.dataset.page);
                                loadGames(pageNum);
                                
                                // Smooth scroll to top of games container
                                document.getElementById('gamesContainer').scrollIntoView({ 
                                    behavior: 'smooth',
                                    block: 'start'
                                });
                            }
                        });
                    });
    }

                // Reattach event listeners
                document.querySelectorAll('.game-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const gameId = parseInt(this.value);
                        if (this.checked) {
                            selectedGames.add(gameId);
                        } else {
                            selectedGames.delete(gameId);
                        }
                        updateSelectedCount();
                    });
                });
            } else {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="ph ph-game-controller"></i>
                        <h3>No games found</h3>
                        <p>Try fetching games from RAWG API</p>
                        <div class="d-flex gap-2 justify-content-center mt-3 flex-wrap">
                            <button class="btn btn-outline-light" data-game-type="released">
                                <i class="ph ph-calendar-check me-1"></i>Fetch Released
                            </button>
                            <button class="btn btn-outline-info" data-game-type="upcoming">
                                <i class="ph ph-calendar-plus me-1"></i>Fetch Upcoming
                            </button>
                        </div>
                    </div>
        `;
                container.querySelectorAll('[data-game-type]').forEach(btn => {
                    btn.addEventListener('click', handleFetchNewGames);
                });
            }
        } catch (error) {
            console.error('Error in loadGames:', error);
            container.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-exclamation-circle"></i>
                    <h3>Error loading games</h3>
                    <p>${error.message}</p>
                    <button class="btn btn-primary mt-3" onclick="loadGames()">Retry</button>
                </div>
            `;
        }
}

    async function updateStats() {
    try {
            console.log('Updating stats...');
        const formData = new FormData();
            formData.append('action', 'get_stats');

        const response = await fetch('game-approval.php', {
            method: 'POST',
            body: formData
        });

            const stats = await response.json();
            console.log('Stats received:', stats);

            document.getElementById('pendingCount').textContent = stats.pending || 0;
            document.getElementById('approvedCount').textContent = stats.approved_today || 0;
            document.getElementById('rejectedCount').textContent = stats.rejected_today || 0;
            document.getElementById('totalCount').textContent = stats.total || 0;
    } catch (error) {
            console.error('Error updating stats:', error);
    }
}

    async function handleFetchNewGames(event) {
        const button = event.target.closest('button');
        const gameType = button && button.dataset.gameType ? button.dataset.gameType : 'released';
        console.log('Fetch button clicked, game type:', gameType);
        
        try {
            button.disabled = true;
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="spinner-border spinner-border-sm me-1"></i>Fetching...';
            
            const formData = new FormData();
            formData.append('action', 'fetch_new_games');
            formData.append('game_type', gameType);

        const response = await fetch('game-approval.php', {
            method: 'POST',
            body: formData
        });

            console.log('Got response');
        const data = await response.json();
            console.log('Response data:', data);

        if (data.success) {
                const gameTypeText = gameType === 'upcoming' ? 'upcoming' : 'released';
                showNotification(`${data.count} new ${gameTypeText} games fetched successfully!`, 'success');
                loadGames(1);
            updateStats();
        } else {
                showNotification('Error fetching new games', 'error');
        }
    } catch (error) {
            console.error('Error in handleFetchNewGames:', error);
            showNotification('Error fetching new games', 'error');
        } finally {
            button.disabled = false;
            if (gameType === 'upcoming') {
                button.innerHTML = '<i class="ph ph-calendar-plus me-1"></i>Fetch Upcoming';
            } else {
                button.innerHTML = '<i class="ph ph-calendar-check me-1"></i>Fetch Released';
            }
        }
    }

    function getRatingClass(rating) {
        if (!rating) return '';
        if (rating >= 4.5) return 'rating-high';
        if (rating >= 3.5) return 'rating-medium';
        return 'rating-low';
    }

    function getMetacriticClass(score) {
        if (!score) return '';
        if (score >= 85) return 'rating-high';
        if (score >= 70) return 'rating-medium';
        return 'rating-low';
    }

    function getStatusClass(status) {
        switch(status) {
            case 'pending': return 'status-pending';
            case 'approved': return 'status-approved';
            case 'rejected': return 'status-rejected';
            default: return '';
        }
    }

    function capitalizeFirst(str) {
        return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
    }

    function updateSelectedCount() {
        const count = selectedGames.size;
        document.getElementById('selectedCount').textContent = `${count} selected`;
        document.getElementById('selectAll').checked = count > 0 && count === document.querySelectorAll('.game-checkbox').length;
    }

    function toggleSelectAll() {
        const isChecked = document.getElementById('selectAll').checked;
        document.querySelectorAll('.game-checkbox').forEach(checkbox => {
            checkbox.checked = isChecked;
            const gameId = parseInt(checkbox.value);
            const selectArea = checkbox.closest('.game-select-area');
            
            if (isChecked) {
                selectedGames.add(gameId);
                selectArea.classList.add('checked');
            } else {
                selectedGames.delete(gameId);
                selectArea.classList.remove('checked');
            }
        });
        updateSelectedCount();
    }

    function handleCheckboxChange(checkbox) {
        const gameId = parseInt(checkbox.value);
        const selectArea = checkbox.closest('.game-select-area');
        
        if (checkbox.checked) {
            selectedGames.add(gameId);
            selectArea.classList.add('checked');
        } else {
            selectedGames.delete(gameId);
            selectArea.classList.remove('checked');
        }
        
        updateSelectedCount();
    }

    // Add the filter function
    function filterByStatus(status) {
        // Update active state of buttons
        document.querySelectorAll('.btn-group .btn').forEach(btn => {
            btn.classList.remove('active');
            if(btn.textContent.toLowerCase().includes(status)) {
                btn.classList.add('active');
            }
        });
        
        // Update the status filter dropdown to match
        document.getElementById('statusFilter').value = status;
        
        // Clear selections when changing filters
        selectedGames.clear();
        updateSelectedCount();
        
        // Reload games with new filter
        loadGames(1);
    }

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM Content Loaded');
        
        // Set up fetch button handlers (released + upcoming)
        document.getElementById('fetchReleasedButton')?.addEventListener('click', handleFetchNewGames);
        document.getElementById('fetchUpcomingButton')?.addEventListener('click', handleFetchNewGames);
        
        // Set up search functionality
        setupSearchFunctionality();
        
        // Initialize games list
        console.log('Initializing games list');
        loadGames();
        updateStats();
    });

    // Search functionality
    function setupSearchFunctionality() {
        const searchBtn = document.getElementById('searchGameBtn');
        const searchInput = document.getElementById('gameSearchInput');
        
        if (searchBtn) {
            searchBtn.addEventListener('click', performGameSearch);
        }
        
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    performGameSearch();
                }
            });
        }
    }

    async function performGameSearch() {
        const searchInput = document.getElementById('gameSearchInput');
        const searchResults = document.getElementById('searchResults');
        const searchLoading = document.getElementById('searchLoading');
        const searchError = document.getElementById('searchError');
        const searchResultsList = document.getElementById('searchResultsList');
        
        const searchTerm = searchInput.value.trim();
        
        if (!searchTerm) {
            showNotification('Please enter a game title to search for', 'warning');
            return;
        }
        
        // Show loading
        searchLoading.style.display = 'block';
        searchResults.style.display = 'none';
        searchError.style.display = 'none';
        
        try {
            const formData = new FormData();
            formData.append('action', 'search_games');
            formData.append('search_term', searchTerm);
            formData.append('page', 1);
            
            const response = await fetch('game-approval.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                displaySearchResults(data.games, searchTerm);
            } else {
                throw new Error(data.error || 'Search failed');
            }
        } catch (error) {
            console.error('Search error:', error);
            searchError.textContent = error.message;
            searchError.style.display = 'block';
        } finally {
            searchLoading.style.display = 'none';
        }
    }

    function displaySearchResults(games, searchTerm) {
        const searchResults = document.getElementById('searchResults');
        const searchResultsList = document.getElementById('searchResultsList');
        
        // Debug: Log the games data to see what RAWG provides
        console.log('Search results:', games);
        games.forEach((game, index) => {
            console.log(`Game ${index + 1}:`, game.name, 'RAWG data:', game.debug_rawg_data);
            console.log(`DLC detection for "${game.name}":`, {
                isDlc: game.is_dlc,
                parentGameInfo: game.parent_game_info,
                canApprove: game.can_approve,
                approvalWarning: game.approval_warning
            });
        });
        
        if (games.length === 0) {
            searchResultsList.innerHTML = `
                <div class="alert alert-info">
                    <i class="ph ph-info me-2"></i>
                    No games found matching "${searchTerm}". Try a different search term.
                </div>
            `;
        } else {
            searchResultsList.innerHTML = games.map(game => {
                // Game/DLC badge and parent game info
                let typeBadge = '';
                let parentGameInfo = '';
                let approvalWarning = '';
                let canApprove = true;
                
                if (game.is_dlc) {
                    typeBadge = '<span class="badge bg-purple me-2"><i class="ph ph-puzzle-piece me-1"></i>DLC</span>';
                    
                    if (game.parent_game_info) {
                        parentGameInfo = `
                            <div class="alert alert-info alert-sm mb-2">
                                <i class="ph ph-link me-1"></i>
                                <strong>Parent Game:</strong> ${game.parent_game_info.title}
                            </div>
                        `;
                    } else {
                        approvalWarning = `
                            <div class="alert alert-warning alert-sm mb-2">
                                <i class="ph ph-warning me-1"></i>
                                ${game.approval_warning}
                            </div>
                        `;
                        canApprove = false;
                    }
                } else {
                    typeBadge = '<span class="badge bg-blue me-2"><i class="ph ph-game-controller me-1"></i>Game</span>';
                }
                
                return `
                    <div class="card bg-dark border-secondary mb-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <img src="${game.background_image || '../images/logo.png'}" 
                                         class="img-fluid rounded" alt="${game.name}"
                                         onerror="this.src='../images/logo.png'">
                                </div>
                                <div class="col-md-9">
                                    <h6 class="card-title text-light">
                                        ${typeBadge}${game.name}
                                    </h6>
                                    ${parentGameInfo}
                                    ${approvalWarning}
                                    <p class="card-text text-muted small">${game.description || 'No description available'}</p>
                                    <div class="row text-muted small">
                                        <div class="col-6">
                                            <strong>Rating:</strong> ${game.rating || 'N/A'}/5
                                        </div>
                                        <div class="col-6">
                                            <strong>Reviews:</strong> ${game.ratings_count || 'N/A'}
                                        </div>
                                    </div>
                                    <div class="row text-muted small">
                                        <div class="col-6">
                                            <strong>Metacritic:</strong> ${game.metacritic_score || 'N/A'}
                                        </div>
                                        <div class="col-6">
                                            <strong>Released:</strong> ${game.released ? new Date(game.released).getFullYear() : 'N/A'}
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        ${game.already_exists ? 
                                            `<button class="btn btn-sm btn-warning me-2" onclick="updateGameDetails('${game.rawg_id}', '${game.name.replace(/'/g, "\\'")}')">
                                                <i class="ph ph-arrows-clockwise me-1"></i>Update ${game.is_dlc ? 'DLC' : 'Game'}
                                            </button>` :
                                            canApprove ? 
                                                `<button class="btn btn-sm btn-success me-2" onclick="addGameToPending('${game.rawg_id}', '${game.name.replace(/'/g, "\\'")}', ${game.is_dlc}, ${game.parent_game_info ? game.parent_game_info.id : 'null'})">
                                                    <i class="ph ph-plus me-1"></i>Add to Pending
                                                </button>` :
                                                `<button class="btn btn-sm btn-secondary me-2" disabled>
                                                    <i class="ph ph-x me-1"></i>Cannot Add
                                                </button>`
                                        }
                                        <button class="btn btn-sm btn-info" onclick="viewGameDetails('${game.rawg_id}')">
                                            <i class="ph ph-info me-1"></i>View Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        searchResults.style.display = 'block';
    }

    // Add game to pending list
    async function addGameToPending(rawgId, gameName, isDlc = false, parentGameId = null) {
        try {
            // Directly fetch and add the game
            await fetchAndAddGame(rawgId, gameName, isDlc, parentGameId);
        } catch (error) {
            console.error('Error adding game to pending:', error);
            showNotification('Failed to add game to pending list', 'error');
        }
    }

    async function fetchAndAddGame(rawgId, gameName, isDlc = false, parentGameId = null) {
        try {
            console.log('Fetching game with ID:', rawgId, 'isDlc:', isDlc, 'parentGameId:', parentGameId);
            
            const formData = new FormData();
            formData.append('action', 'fetch_new_games');
            formData.append('specific_game_id', rawgId);
            formData.append('is_dlc', isDlc);
            if (parentGameId) {
                formData.append('parent_game_id', parentGameId);
            }
            
            const response = await fetch('game-approval.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            console.log('Response from server:', data);
            
            if (data.success) {
                const itemType = isDlc ? 'DLC' : 'game';
                showNotification(`"${gameName}" has been added to the pending list`, 'success');
                
                // Update the button to show "Added" state
                const button = document.querySelector(`button[onclick*="addGameToPending('${rawgId}"]`);
                if (button) {
                    button.innerHTML = '<i class="ph ph-check me-1"></i>Added';
                    button.className = 'btn btn-sm btn-success me-2 disabled';
                    button.style.opacity = '0.6';
                    button.onclick = null; // Remove the onclick handler
                }
                
                // Reload the games list
                loadGames(1);
                updateStats();
            } else {
                throw new Error(data.error || 'Failed to add game');
            }
        } catch (error) {
            console.error('Error fetching game:', error);
            showNotification('Failed to fetch game details: ' + error.message, 'error');
        }
    }

    function viewGameDetails(rawgId) {
        // Open RAWG page in new tab
        window.open(`https://rawg.io/games/${rawgId}`, '_blank');
    }
window.updateGameDetails = async function(rawgId, gameName) {
    try {
        console.log('Updating game with ID:', rawgId);
        
        const formData = new FormData();
        formData.append('action', 'update_game');
        formData.append('rawg_id', rawgId);
        
        const response = await fetch('game-approval.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        console.log('Update response from server:', data);
        
        if (data.success) {
            showNotification(`"${gameName}" has been updated with latest details`, 'success');
            
            // Update the button to show "Updated" state
            const button = document.querySelector(`button[onclick*="updateGameDetails('${rawgId}"]`);
            if (button) {
                button.innerHTML = '<i class="ph ph-check me-1"></i>Updated';
                button.className = 'btn btn-sm btn-success me-2 disabled';
                button.style.opacity = '0.6';
                button.onclick = null; // Remove the onclick handler
            }
            
            // Reload the games list
            loadGames(1);
            updateStats();
        } else {
            throw new Error(data.error || 'Failed to update game');
        }
    } catch (error) {
        console.error('Error updating game:', error);
        showNotification('Failed to update game details: ' + error.message, 'error');
    }
}

    window.applyFilters = function() {
        // Clear selections when changing filters
        selectedGames.clear();
        updateSelectedCount();
        
        // Reload games with new filter
        loadGames(1);
    }

    console.log('Main script completed setup');
} catch (error) {
    console.error('Error in main script:', error);
        }
</script>
</body>
</html>