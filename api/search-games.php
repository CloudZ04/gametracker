<?php
require_once '../includes/db.php';
header('Content-Type: application/json');

$search = $_GET['q'] ?? '';
$isTimeline = isset($_GET['timeline']) && $_GET['timeline'] === 'true';

if (empty($search)) {
    echo json_encode(['success' => false, 'message' => 'No search term provided']);
    exit;
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

// --- NEW: RAWG fallback function ---
function fetchIGDBPortrait($gameTitle) {
    $clientId = 'avrcrn7yp1lyhkkve1et2ha4rwvhzo';
    $clientSecret = '4rsurue3p8kv0l0kua3orx9y6oxjwf';
    $accessToken = getFreshIGDBAccessToken($clientId, $clientSecret);
    if (!$accessToken) {
        return '';
    }
    $safeTitle = str_replace(['\\', '"'], ['\\\\', '\\"'], $gameTitle);

    $searchQuery = "search \"{$safeTitle}\"; fields id,name,category; limit 25;";
    $ch = curl_init("https://api.igdb.com/v4/games");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Client-ID: {$clientId}",
        "Authorization: Bearer {$accessToken}",
        "Content-Type: text/plain"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $searchQuery);
    $result = curl_exec($ch);
    curl_close($ch);
    $games = json_decode($result, true);

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
    if (!$bestGameId || $bestScore < 72.0) return '';

    $coverQuery = "fields image_id; where game = {$bestGameId}; limit 1;";
    $ch = curl_init("https://api.igdb.com/v4/covers");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Client-ID: {$clientId}",
        "Authorization: Bearer {$accessToken}",
        "Content-Type: text/plain"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $coverQuery);
    $coverResult = curl_exec($ch);
    curl_close($ch);
    $coverData = json_decode($coverResult, true);

    if (empty($coverData) || empty($coverData[0]['image_id'])) return '';
    return buildBestIgdbImageUrl((string)$coverData[0]['image_id'], 't_cover_big');
}

function fetchAndSaveFromRAWG($search, $conn) {
    $apiKey = '58aed2d9aedd4274ab81d91356e775f2';
    $url = "https://api.rawg.io/api/games?key={$apiKey}&search=" . urlencode($search) . "&page_size=5";
    
    $response = @file_get_contents($url);
    if (!$response) return;
    
    $data = json_decode($response, true);
    if (empty($data['results'])) return;
    
    foreach ($data['results'] as $game) {
        // Skip if already in DB (both games and dlcs)
        $rawgId = (int)$game['id'];
        $exists = $conn->query("SELECT id FROM games WHERE rawg_id = '{$rawgId}' LIMIT 1");
        $existsDlc = $conn->query("SELECT id FROM dlcs WHERE rawg_id = '{$rawgId}' LIMIT 1");
        if (($exists && $exists->num_rows > 0) || ($existsDlc && $existsDlc->num_rows > 0)) continue;

        // Map RAWG fields to your DB columns
        $titleRaw    = $game['name'] ?? '';
        $title       = $conn->real_escape_string($titleRaw);
        $releaseDateRaw = !empty($game['released']) ? $game['released'] : null;
        $releaseDate = $releaseDateRaw ? $conn->real_escape_string($releaseDateRaw) : null;
        $imageUrlRaw = $game['background_image'] ?? '';
        $imageUrl    = $conn->real_escape_string($imageUrlRaw);
        $portraitUrlRaw = fetchIGDBPortrait($titleRaw);
        $portraitUrl = $conn->real_escape_string($portraitUrlRaw);
        $rating      = $game['rating'] ?? 0;

        $platformsRaw = implode(', ', array_map(
            fn($p) => $p['platform']['name'], 
            $game['platforms'] ?? []
        ));
        $platforms = $conn->real_escape_string($platformsRaw);

        $genresRaw = implode(', ', array_map(
            fn($g) => $g['name'], 
            $game['genres'] ?? []
        ));
        $genres = $conn->real_escape_string($genresRaw);

        $releaseDateSql = $releaseDate ? "'{$releaseDate}'" : "NULL";

        // After getting $rawgId, $title etc but before the INSERT:
        $detailUrl = "https://api.rawg.io/api/games/{$rawgId}?key={$apiKey}";
        $detailResponse = @file_get_contents($detailUrl);
        $description = '';

        $detailData = [];
        if ($detailResponse) {
            $detailData = json_decode($detailResponse, true);
            $description = $conn->real_escape_string($detailData['description_raw'] ?? '');
        }

        $dlcMeta = detectDlcMetadata($titleRaw, $detailData);
        if ($dlcMeta['is_dlc']) {
            $parentGameId = findParentGameId($conn, $dlcMeta['parent_rawg_id'], $dlcMeta['parent_title']);
            if (!$parentGameId) {
                continue;
            }

            $avgRating = (float)$rating;
            $totalReviews = (int)($game['ratings_count'] ?? 0);
            $releaseDateValue = $releaseDateRaw ?: null;
            $descriptionRaw = $detailData['description_raw'] ?? '';
            $source = 'auto';
            $insertDlc = $conn->prepare("
                INSERT INTO dlcs (title, parent_game_id, rawg_id, release_date, description, image_url, portrait_image_url, platforms, genre, avg_rating, total_reviews, source)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if ($insertDlc) {
                $insertDlc->bind_param(
                    "siissssssdis",
                    $titleRaw,
                    $parentGameId,
                    $rawgId,
                    $releaseDateValue,
                    $descriptionRaw,
                    $imageUrlRaw,
                    $portraitUrlRaw,
                    $platformsRaw,
                    $genresRaw,
                    $avgRating,
                    $totalReviews,
                    $source
                );
                $insertDlc->execute();
            }
            continue;
        }

        // Then add main game to games table:
        $conn->query("
            INSERT INTO games (title, release_date, image_url, portrait_image_url, platforms, genre, avg_rating, rawg_id, description)
            VALUES ('{$title}', {$releaseDateSql}, '{$imageUrl}', '{$portraitUrl}', '{$platforms}', '{$genres}', '{$rating}', {$rawgId}, '{$description}')
        ");
    }
}

try {
    $searchUpper = strtoupper($search);
    $abbrevPattern = '^$';

    if (strlen($search) < 5) {
        $abbrevPattern = implode('[^ ]+ ', str_split($searchUpper)) . '[^ ]*';
    }

    $query = "SELECT id, title, image_url, release_date, platforms, genre 
              FROM games 
              WHERE (title LIKE ? OR UPPER(title) REGEXP ?)";

    if ($isTimeline) {
        $query .= " AND (release_date >= '2025-01-01' OR (is_tba = 1 AND (tba_year >= 2025 OR tba_year IS NULL)))";
    } else {
        $query .= " AND (release_date IS NOT NULL AND release_date <= CURDATE())";
    }

    $query .= " ORDER BY 
                  CASE 
                      WHEN title LIKE ? THEN 1
                      WHEN title LIKE ? THEN 2
                      ELSE 3
                  END,
                  avg_rating DESC, total_reviews DESC
                LIMIT 5";

    $stmt = $conn->prepare($query);
    $containsPattern  = "%{$search}%";
    $exactPattern     = $search;
    $startsWithPattern = "{$search}%";
    $stmt->bind_param("ssss", $containsPattern, $abbrevPattern, $exactPattern, $startsWithPattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $games = [];

    while ($row = $result->fetch_assoc()) {
        $platforms = explode(', ', $row['platforms']);
        $genres    = explode(', ', $row['genre']);
        $games[] = [
            'id'           => $row['id'],
            'title'        => $row['title'],
            'image_url'    => $row['image_url'],
            'release_date' => $row['release_date'] ? date('F j, Y', strtotime($row['release_date'])) : 'TBA',
            'platforms'    => array_slice($platforms, 0, 3),
            'genres'       => array_slice($genres, 0, 2)
        ];
    }

    // --- NEW: if local results are thin, try RAWG ---
    if (count($games) < 3 && !$isTimeline && strlen($search) >= 3) {
        fetchAndSaveFromRAWG($search, $conn);

        // Re-run the same query now DB may have new games
        $stmt->execute();
        $result = $stmt->get_result();
        $games = [];
        while ($row = $result->fetch_assoc()) {
            $platforms = explode(', ', $row['platforms']);
            $genres    = explode(', ', $row['genre']);
            $games[] = [
                'id'           => $row['id'],
                'title'        => $row['title'],
                'image_url'    => $row['image_url'],
                'release_date' => $row['release_date'] ? date('F j, Y', strtotime($row['release_date'])) : 'TBA',
                'platforms'    => array_slice($platforms, 0, 3),
                'genres'       => array_slice($genres, 0, 2)
            ];
        }
    }

    echo json_encode(['success' => true, 'games' => $games]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error performing search: ' . $e->getMessage()]);
}