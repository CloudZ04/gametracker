<?php
// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';

// API credentials for game data fetching
$api_key = '58aed2d9aedd4274ab81d91356e775f2'; // RAWG API key
$clientId = 'avrcrn7yp1lyhkkve1et2ha4rwvhzo';  // IGDB client ID
$clientSecret = '4rsurue3p8kv0l0kua3orx9y6oxjwf'; // IGDB client secret (Twitch app secret)

// Pagination settings for RAWG API
$page_size = 20;
$total_pages = 3;
$current_page = 1;

// Load existing game IDs so we only insert games NOT already on the site (no duplicates)
$existingRawgIds = [];
$res = $conn->query("
    SELECT rawg_id FROM games WHERE rawg_id IS NOT NULL
    UNION
    SELECT rawg_id FROM dlcs WHERE rawg_id IS NOT NULL
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $existingRawgIds[(int)$row['rawg_id']] = true;
    }
}

/**
 * Fetches detailed game information from RAWG API
 * @param string $slug Game identifier from RAWG
 * @param string $api_key RAWG API key
 * @return array Game details or empty array if request fails
 */
function fetchRAWGDetail($slug, $api_key) {
    $url = "https://api.rawg.io/api/games/$slug?key=$api_key";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response ? json_decode($response, true) : [];
}

function getFreshIGDBAccessToken($clientId, $clientSecret) {
    $ch = curl_init('https://id.twitch.tv/oauth2/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'grant_type' => 'client_credentials'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $raw = curl_exec($ch);
    curl_close($ch);
    if ($raw === false) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? ($decoded['access_token'] ?? null) : null;
}

function remoteFileExists($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode >= 200 && $httpCode < 400;
}

function buildBestIgdbImageUrl($imageId, $size = 't_cover_big') {
    $base = "https://images.igdb.com/igdb/image/upload/{$size}/{$imageId}";
    foreach (['webp', 'jpg', 'png', 'jpeg'] as $ext) {
        $candidate = "{$base}.{$ext}";
        if (remoteFileExists($candidate)) {
            return $candidate;
        }
    }
    return "{$base}.jpg";
}

function detectDlcMetadata($title, $detailData) {
    $parentRawgId = null;
    $parentTitle = null;
    $score = 0;
    $titleLower = strtolower((string)$title);
    $hasStrongDlcSignal = false;
    $hasParentSignal = false;

    $editionPattern = '/\b(special edition|definitive edition|game of the year|goty|remaster(?:ed)?|redux|director\'?s cut|complete edition|ultimate edition|gold edition|enhanced edition|anniversary edition|vr|cloud version)\b/i';
    $dlcPattern = '/\b(dlc|expansion|expansion pack|add-?on|addon|season pass|content pack|episode|chapter|story pack|map pack|character pack|game add-on pack)\b/i';

    $hasEditionTerm = preg_match($editionPattern, $titleLower) === 1;
    if ($hasEditionTerm) {
        $score -= 3;
    }
    if (preg_match($dlcPattern, $titleLower)) {
        $score += 3;
        $hasStrongDlcSignal = true;
    }

    if (!empty($detailData['parent_game']) && is_array($detailData['parent_game'])) {
        $parentRawgId = isset($detailData['parent_game']['id']) ? (int)$detailData['parent_game']['id'] : null;
        $parentTitle = $detailData['parent_game']['name'] ?? null;
        $score += 1;
        $hasParentSignal = true;
    }
    if (isset($detailData['parents_count']) && (int)$detailData['parents_count'] > 0) {
        $score += 1;
        $hasParentSignal = true;
    }
    if (!empty($detailData['tags']) && is_array($detailData['tags'])) {
        foreach ($detailData['tags'] as $tag) {
            $tagName = strtolower((string)($tag['name'] ?? ''));
            if (in_array($tagName, ['dlc', 'expansion', 'add-on', 'downloadable content'], true)) {
                $score += 3;
                $hasStrongDlcSignal = true;
                break;
            }
        }
    }
    if (!empty($detailData['genres']) && is_array($detailData['genres'])) {
        foreach ($detailData['genres'] as $genre) {
            $genreName = strtolower((string)($genre['name'] ?? ''));
            if (strpos($genreName, 'expansion') !== false || strpos($genreName, 'dlc') !== false) {
                $score += 2;
                $hasStrongDlcSignal = true;
                break;
            }
        }
    }

    $isDlc = ($hasStrongDlcSignal && $score >= 2) || ($hasParentSignal && !$hasEditionTerm);
    if ($isDlc && !$parentTitle && preg_match('/^(.+?)\s*[-:]\s*(.+)$/', $title, $matches)) {
        $candidate = trim($matches[1]);
        if ($candidate !== '') {
            $parentTitle = $candidate;
        }
    }

    return [
        'is_dlc' => $isDlc,
        'parent_rawg_id' => $parentRawgId,
        'parent_title' => $parentTitle
    ];
}

function findParentGameId($conn, $parentRawgId, $parentTitle) {
    if (!empty($parentRawgId)) {
        $stmt = $conn->prepare("SELECT id FROM games WHERE rawg_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $parentRawgId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!empty($row['id'])) {
                return (int)$row['id'];
            }
        }
    }

    if (!empty($parentTitle)) {
        $stmt = $conn->prepare("SELECT id FROM games WHERE title = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $parentTitle);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!empty($row['id'])) {
                return (int)$row['id'];
            }
        }

        $like = $parentTitle . '%';
        $stmt = $conn->prepare("SELECT id FROM games WHERE title LIKE ? ORDER BY release_date DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $like);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!empty($row['id'])) {
                return (int)$row['id'];
            }
        }
    }

    return null;
}

function normalizeTitle($value) {
    $value = strtolower((string)$value);
    $value = preg_replace('/[^a-z0-9 ]+/i', ' ', $value);
    $value = preg_replace('/\s+/', ' ', (string)$value);
    return trim((string)$value);
}

function hasBadEditionHint($title) {
    return (bool)preg_match('/\b(bundle|pack|add-?on|dlc|expansion|season pass|soundtrack|mobile|ios|android|cloud version|definitive edition|gold edition|ultimate edition|deluxe edition)\b/i', (string)$title);
}

function scoreIgdbCandidate($inputTitle, $candidate) {
    $candidateName = (string)($candidate['name'] ?? '');
    if ($candidateName === '') {
        return -1000.0;
    }

    $inputNorm = normalizeTitle($inputTitle);
    $candNorm = normalizeTitle($candidateName);
    $score = 0.0;

    if ($candNorm === $inputNorm) {
        $score += 120.0;
    } elseif (str_starts_with($candNorm, $inputNorm) || str_starts_with($inputNorm, $candNorm)) {
        $score += 35.0;
    }

    similar_text($inputNorm, $candNorm, $similarityPercent);
    $score += $similarityPercent * 0.55;

    $inputTokens = array_values(array_filter(explode(' ', $inputNorm), fn($t) => strlen($t) > 2));
    $candTokens = array_values(array_filter(explode(' ', $candNorm), fn($t) => strlen($t) > 2));
    if (!empty($inputTokens) && !empty($candTokens)) {
        $shared = count(array_intersect($inputTokens, $candTokens));
        $score += ($shared / max(count($inputTokens), 1)) * 35.0;
    }

    $category = isset($candidate['category']) ? (int)$candidate['category'] : 0;
    if (in_array($category, [0, 8, 9, 10], true)) {
        $score += 12.0;
    } elseif (in_array($category, [1, 2, 3, 7, 11], true)) {
        $score -= 55.0;
    }

    $inputHasEditionHints = hasBadEditionHint($inputTitle);
    $candidateHasEditionHints = hasBadEditionHint($candidateName);
    if (!$inputHasEditionHints && $candidateHasEditionHints) {
        $score -= 45.0;
    }

    return $score;
}

/**
 * Fetches game portrait image from IGDB API
 * Uses a two-step process:
 * 1. Search for game by title to get ID
 * 2. Use game ID to fetch cover image
 * @param string $gameTitle Game title to search for
 * @param string $clientId IGDB client ID
 * @param string $accessToken IGDB access token
 * @return string URL of the portrait image or empty string if not found
 */
function fetchIGDBPortrait($gameTitle, $clientId, $accessToken) {
    $safeTitle = str_replace(['\\', '"'], ['\\\\', '\\"'], $gameTitle);
    // Step 1: Search for game ID
    $searchQuery = "search \"$safeTitle\"; fields id, name, category; limit 25;";
    $ch = curl_init("https://api.igdb.com/v4/games");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Client-ID: $clientId",
        "Authorization: Bearer $accessToken",
        "Content-Type: text/plain"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $searchQuery);
    $result = curl_exec($ch);
    curl_close($ch);

    $games = json_decode($result, true);
    // Debug logging
    file_put_contents('igdb_debug.log', print_r($games, true), FILE_APPEND);

    if (empty($games) || !is_array($games)) return '';
    $bestGameId = null;
    $bestScore = -1000.0;
    foreach ($games as $candidate) {
        if (empty($candidate['id'])) {
            continue;
        }
        $score = scoreIgdbCandidate($gameTitle, $candidate);
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestGameId = (int)$candidate['id'];
        }
    }
    if (!$bestGameId || $bestScore < 72.0) {
        return '';
    }

    // Step 2: Fetch cover image using game ID
    $coverQuery = "fields image_id; where game = {$bestGameId};";
    $ch = curl_init("https://api.igdb.com/v4/covers");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Client-ID: $clientId",
        "Authorization: Bearer $accessToken",
        "Content-Type: text/plain"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $coverQuery);
    $coverResult = curl_exec($ch);
    curl_close($ch);

    $coverData = json_decode($coverResult, true);
    // Debug logging
    file_put_contents('igdb_debug.log', print_r($coverData, true), FILE_APPEND);

    if (!empty($coverData[0]['image_id'])) {
        return buildBestIgdbImageUrl((string)$coverData[0]['image_id'], 't_cover_big');
    }

    return '';
}

// Main game fetching loop
$accessToken = getFreshIGDBAccessToken($clientId, $clientSecret);
if (!$accessToken) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["message" => "Failed to authenticate with IGDB (unable to fetch access token)."]);
    exit();
}

while ($current_page <= $total_pages) {
    // Fetch games from RAWG API for 2025-2026
    $api_url = "https://api.rawg.io/api/games?dates=2025-01-01,2026-12-31&ordering=-added&page_size=$page_size&page=$current_page&key=$api_key";
    $response = file_get_contents($api_url);
    $gamesData = json_decode($response, true);

    if (!empty($gamesData['results'])) {
        foreach ($gamesData['results'] as $game) {
            // Skip if already in games or dlcs (no duplicates)
            $rawgId = (int)($game['id'] ?? 0);
            if ($rawgId && !empty($existingRawgIds[$rawgId])) {
                continue;
            }

            // Filter games to only include major platforms
            $platforms = isset($game['platforms']) ? implode(", ", array_column(array_column($game['platforms'], 'platform'), 'name')) : '';
            if (
                strpos($platforms, 'PlayStation 5') === false &&
                strpos($platforms, 'Xbox Series') === false &&
                strpos($platforms, 'Nintendo Switch') === false
            ) {
                continue;
            }

            // Fetch detailed game information
            $slug = $game['slug'];
            $detail_data = fetchRAWGDetail($slug, $api_key);
            usleep(15000); // Rate limiting delay

            // Prepare and escape game data
            $titleRaw = $game['name'] ?? '';
            $title = $conn->real_escape_string($titleRaw);
            $platforms_escaped = $conn->real_escape_string($platforms);
            $genres = isset($game['genres']) ? $conn->real_escape_string(implode(", ", array_column($game['genres'], 'name'))) : '';
            $image_url = isset($game['background_image']) ? $conn->real_escape_string($game['background_image']) : '';
            
            // Fetch portrait image from IGDB
            $portrait = fetchIGDBPortrait($titleRaw, $clientId, $accessToken);
            $portrait_image_url = $conn->real_escape_string($portrait);

            $description = isset($detail_data['description']) ? $conn->real_escape_string($detail_data['description']) : '';

            // Handle release date and TBA status
            $release_date = !empty($game['released']) ? $conn->real_escape_string($game['released']) : null;
            $is_tba = 0;
            $tba_year = 'NULL';

            if (!$release_date) {
                // Determine TBA year from available dates
                if (!empty($game['added'])) {
                    $fallback_year = substr($game['added'], 0, 4);
                } elseif (!empty($game['updated'])) {
                    $fallback_year = substr($game['updated'], 0, 4);
                } else {
                    $fallback_year = date('Y');
                }

                $tba_year = (int)$fallback_year;
                $is_tba = 1;
            }

            $source = 'auto';

            $dlcMeta = detectDlcMetadata($titleRaw, $detail_data);
            if ($dlcMeta['is_dlc']) {
                $parentGameId = findParentGameId($conn, $dlcMeta['parent_rawg_id'], $dlcMeta['parent_title']);

                // If parent cannot be resolved, skip this record to avoid polluting games table.
                if (!$parentGameId) {
                    continue;
                }

                $avgRating = isset($game['rating']) ? (float)$game['rating'] : 0.0;
                $totalReviews = isset($game['ratings_count']) ? (int)$game['ratings_count'] : 0;
                $dlcStmt = $conn->prepare("INSERT INTO dlcs (
                    title, parent_game_id, rawg_id, release_date, description, image_url, portrait_image_url, platforms, genre, avg_rating, total_reviews, source
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                if ($dlcStmt) {
                    $dlcStmt->bind_param(
                        "siissssssdis",
                        $title,
                        $parentGameId,
                        $rawgId,
                        $release_date,
                        $description,
                        $image_url,
                        $portrait_image_url,
                        $platforms_escaped,
                        $genres,
                        $avgRating,
                        $totalReviews,
                        $source
                    );
                    $dlcStmt->execute();
                    $existingRawgIds[$rawgId] = true;
                }

                continue;
            }

            // Insert game data into database using prepared statement (only new main games; existing rawg_id skipped above)
            $stmt = $conn->prepare("INSERT INTO games (
                title, release_date, tba_year, is_tba, platforms, genre, image_url, portrait_image_url, description, source, rawg_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param(
                "ssiissssssi", // s=string, i=integer; last is rawg_id
                $title,
                $release_date,
                $tba_year,
                $is_tba,
                $platforms_escaped,
                $genres,
                $image_url,
                $portrait_image_url,
                $description,
                $source,
                $rawgId
            );

            $stmt->execute();
            $existingRawgIds[$rawgId] = true; // avoid duplicate insert if same game appears on another page
        }
    }

    $current_page++;
}

$conn->close();

// Return success response
header('Content-Type: application/json');
echo json_encode(["message" => "🎮 All games imported successfully!"]);
exit();
