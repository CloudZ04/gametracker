<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
ignore_user_abort(true);
@set_time_limit(0);

require_once '../includes/db.php';
session_start();

header('Content-Type: application/json');

function sendJsonAndExit(array $payload, int $statusCode = 200): void {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
    }
    echo json_encode($payload);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    sendJsonAndExit(['success' => false, 'error' => 'Unauthorized'], 401);
}

if (!function_exists('curl_init')) {
    sendJsonAndExit(['success' => false, 'error' => 'cURL extension is required'], 500);
}

$isDryRun = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';
$includeAll = isset($_GET['include_all']) && $_GET['include_all'] === '1';

function fetchRawgDetailById(int $rawgId, string $apiKey): array {
    $url = "https://api.rawg.io/api/games/{$rawgId}?key={$apiKey}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response === false) {
        return [];
    }
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : [];
}

function fetchSteamAppType(int $steamAppId): ?string {
    if ($steamAppId <= 0) {
        return null;
    }
    $url = "https://store.steampowered.com/api/appdetails?appids={$steamAppId}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response === false) {
        return null;
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded) || empty($decoded[(string)$steamAppId]['success'])) {
        return null;
    }
    $type = $decoded[(string)$steamAppId]['data']['type'] ?? null;
    return is_string($type) ? strtolower($type) : null;
}

function getFreshIGDBAccessToken(string $clientId, string $clientSecret): ?string {
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

function normalizeTitleForMatch(string $value): string {
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9 ]+/i', ' ', $value);
    $value = preg_replace('/\s+/', ' ', (string)$value);
    return trim((string)$value);
}

function scoreIgdbTitleCandidate(string $inputTitle, array $candidate): float {
    $name = (string)($candidate['name'] ?? '');
    if ($name === '') {
        return -1000.0;
    }
    $inputNorm = normalizeTitleForMatch($inputTitle);
    $candNorm = normalizeTitleForMatch($name);
    $score = 0.0;
    if ($inputNorm === $candNorm) {
        $score += 100.0;
    } elseif (str_starts_with($candNorm, $inputNorm) || str_starts_with($inputNorm, $candNorm)) {
        $score += 35.0;
    }
    similar_text($inputNorm, $candNorm, $percent);
    $score += $percent * 0.55;
    return $score;
}

function fetchIGDBCategoryByTitle(string $title, string $clientId, string $accessToken): ?int {
    if ($accessToken === '') {
        return null;
    }
    $safeTitle = str_replace(['\\', '"'], ['\\\\', '\\"'], $title);
    $query = "search \"{$safeTitle}\"; fields id,name,category; limit 25;";
    $ch = curl_init('https://api.igdb.com/v4/games');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Client-ID: {$clientId}",
        "Authorization: Bearer {$accessToken}",
        'Content-Type: text/plain'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    $raw = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $httpCode < 200 || $httpCode >= 300) {
        return null;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || empty($decoded)) {
        return null;
    }
    $best = null;
    $bestScore = -1000.0;
    foreach ($decoded as $candidate) {
        if (!isset($candidate['category'])) {
            continue;
        }
        $score = scoreIgdbTitleCandidate($title, $candidate);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $candidate;
        }
    }
    if ($best === null || $bestScore < 72.0) {
        return null;
    }
    return (int)$best['category'];
}

function detectDlcMetadata(string $title, array $rawgDetail): array {
    $isDlc = false;
    $parentRawgId = null;
    $parentTitle = null;
    $source = 'none';
    $score = 0;
    $reasons = [];

    $titleLower = strtolower($title);
    // Hard non-DLC blocklist (editions/collections are not DLC in your DB model).
    // Steam/IGDB can still explicitly verify DLC and override this later.
    $editionPattern = '/\b(special edition|definitive edition|game of the year|goty|remaster(?:ed)?|redux|director\'?s cut|complete edition|ultimate edition|gold edition|enhanced edition|anniversary edition|vr|cloud version|collection|trilogy|anthology|bundle)\b/i';
    $dlcPattern = '/\b(dlc|expansion|expansion pack|add-?on|addon|season pass|content pack|episode|chapter|story pack|map pack|character pack|game add-on pack)\b/i';

    $blockedNonDlc = preg_match($editionPattern, $titleLower) === 1;
    if ($blockedNonDlc) {
        $score -= 2;
        $reasons[] = 'non_dlc_edition_term';
    }

    $hasStrongDlcSignal = false;
    $hasParentSignal = false;

    if (preg_match($dlcPattern, $titleLower)) {
        $score += 3;
        $reasons[] = 'title_dlc_keyword';
        $hasStrongDlcSignal = true;
    }

    // Explicit RAWG parent is useful, but not sufficient on its own.
    if (!empty($rawgDetail['parent_game']) && is_array($rawgDetail['parent_game'])) {
        $parentRawgId = isset($rawgDetail['parent_game']['id']) ? (int)$rawgDetail['parent_game']['id'] : null;
        $parentTitle = $rawgDetail['parent_game']['name'] ?? null;
        $score += 1;
        $reasons[] = 'rawg_parent_game';
        $hasParentSignal = true;
    }

    // parents_count is a weak hint only.
    if (isset($rawgDetail['parents_count']) && (int)$rawgDetail['parents_count'] > 0) {
        $score += 1;
        $reasons[] = 'rawg_parents_count';
        $hasParentSignal = true;
    }

    if (!empty($rawgDetail['tags']) && is_array($rawgDetail['tags'])) {
        foreach ($rawgDetail['tags'] as $tag) {
            $tagName = strtolower((string)($tag['name'] ?? ''));
            if (in_array($tagName, ['dlc', 'expansion', 'add-on', 'downloadable content'], true)) {
                $score += 3;
                $reasons[] = 'rawg_tags';
                $hasStrongDlcSignal = true;
                break;
            }
        }
    }

    if (!empty($rawgDetail['genres']) && is_array($rawgDetail['genres'])) {
        foreach ($rawgDetail['genres'] as $genre) {
            $genreName = strtolower((string)($genre['name'] ?? ''));
            if (strpos($genreName, 'expansion') !== false || strpos($genreName, 'dlc') !== false) {
                $score += 2;
                $reasons[] = 'rawg_genres';
                $hasStrongDlcSignal = true;
                break;
            }
        }
    }

    // Heuristic result (pre Steam/IGDB verification)
    // - If blockedNonDlc, do not classify as DLC here.
    $isDlc = !$blockedNonDlc && (($hasStrongDlcSignal && $score >= 2) || $hasParentSignal);
    // If it's blocked, don't mark as review-needed (Steam/IGDB can still verify separately).
    $reviewNeeded = (!$blockedNonDlc) && (!$isDlc) && ($hasParentSignal || $score >= 2);
    $source = empty($reasons) ? 'none' : implode(',', $reasons);

    if (($isDlc || $reviewNeeded) && !$parentTitle && preg_match('/^(.+?)\s*[-:]\s*(.+)$/', $title, $matches)) {
        $guess = trim($matches[1]);
        if ($guess !== '') {
            $parentTitle = $guess;
        }
    }

    return [
        'is_dlc' => $isDlc, // heuristic result before Steam/IGDB verification
        'review_needed' => $reviewNeeded,
        'confidence_score' => $score,
        'blocked_non_dlc' => $blockedNonDlc,
        'parent_rawg_id' => $parentRawgId,
        'parent_title' => $parentTitle,
        'source' => $source
    ];
}

function findParentGameId(mysqli $conn, int $currentGameId, ?int $parentRawgId, ?string $parentTitle): ?int {
    if (!empty($parentRawgId)) {
        $stmt = $conn->prepare("SELECT id FROM games WHERE rawg_id = ? AND id != ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("ii", $parentRawgId, $currentGameId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!empty($row['id'])) {
                return (int)$row['id'];
            }
        }
    }

    if (!empty($parentTitle)) {
        $stmt = $conn->prepare("SELECT id FROM games WHERE title = ? AND id != ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("si", $parentTitle, $currentGameId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!empty($row['id'])) {
                return (int)$row['id'];
            }
        }

        $like = $parentTitle . '%';
        $stmt = $conn->prepare("SELECT id FROM games WHERE title LIKE ? AND id != ? ORDER BY release_date DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("si", $like, $currentGameId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!empty($row['id'])) {
                return (int)$row['id'];
            }
        }
    }

    return null;
}

try {
    $apiKey = '58aed2d9aedd4274ab81d91356e775f2';
    $igdbClientId = 'avrcrn7yp1lyhkkve1et2ha4rwvhzo';
    $igdbClientSecret = '4rsurue3p8kv0l0kua3orx9y6oxjwf';
    $igdbToken = getFreshIGDBAccessToken($igdbClientId, $igdbClientSecret) ?? '';

    $result = $conn->query("SELECT id, title, rawg_id, steam_app_id, release_date, description, image_url, portrait_image_url, platforms, genre, avg_rating, total_reviews, source FROM games");
    if (!$result) {
        throw new RuntimeException('Failed to read games');
    }

    $insertDlc = $conn->prepare("
        INSERT INTO dlcs (title, parent_game_id, rawg_id, release_date, description, image_url, portrait_image_url, platforms, genre, avg_rating, total_reviews, source)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $deleteGame = $conn->prepare("DELETE FROM games WHERE id = ?");
    $checkDlc = $conn->prepare("SELECT id FROM dlcs WHERE rawg_id = ? LIMIT 1");
    if (!$insertDlc || !$deleteGame || !$checkDlc) {
        throw new RuntimeException('Failed to prepare SQL statements');
    }

    $stats = [
        'dry_run' => $isDryRun,
        'total_games_scanned' => 0,
        'candidate_dlcs' => 0,
        'review_needed' => 0,
        'would_move_to_dlcs' => 0,
        'moved_to_dlcs' => 0,
        'already_in_dlcs' => 0,
        'skipped_no_parent' => 0,
        'skipped_non_steam' => 0,
        'skipped_unknown_steam_type' => 0,
        'rawg_checked' => 0,
        'steam_checked' => 0,
        'igdb_checked' => 0,
        'verified_by_steam_dlc' => 0,
        'rejected_by_steam_non_dlc' => 0,
        'verified_by_igdb_dlc' => 0,
        'rejected_by_igdb_non_dlc' => 0,
        'errors' => 0
    ];
    $samples = [];
    $changes = [];

    while ($row = $result->fetch_assoc()) {
        $stats['total_games_scanned']++;
        $gameId = (int)$row['id'];
        $title = (string)($row['title'] ?? '');
        $rawgId = (int)($row['rawg_id'] ?? 0);
        $steamAppId = (int)($row['steam_app_id'] ?? 0);

        $rawgDetail = [];
        if ($rawgId > 0) {
            $rawgDetail = fetchRawgDetailById($rawgId, $apiKey);
            $stats['rawg_checked']++;
            usleep(60000);
        }

        $meta = detectDlcMetadata($title, $rawgDetail); // keep RAWG only for parent hints

        // Steam-only move policy requested:
        // - no steam_app_id => skip
        // - steam type must be "dlc" for candidate move
        if ($steamAppId <= 0) {
            $stats['skipped_non_steam']++;
            if ($includeAll) {
                $changes[] = ['title' => $title, 'action' => 'skipped_non_steam', 'detected_by' => 'steam_only_mode'];
            }
            if (count($samples) < 15) {
                $samples[] = ['title' => $title, 'action' => 'skipped_non_steam', 'detected_by' => 'steam_only_mode'];
            }
            continue;
        }

        $stats['steam_checked']++;
        $steamType = fetchSteamAppType($steamAppId);
        usleep(50000);

        if ($steamType === 'dlc') {
            $stats['verified_by_steam_dlc']++;
        } elseif (in_array($steamType, ['game', 'application', 'demo', 'video', 'music', 'mod'], true)) {
            $stats['rejected_by_steam_non_dlc']++;
            if ($includeAll) {
                $changes[] = ['title' => $title, 'action' => 'steam_non_dlc_reject', 'detected_by' => 'steam_type_' . $steamType];
            }
            continue;
        } else {
            $stats['skipped_unknown_steam_type']++;
            if ($includeAll) {
                $changes[] = ['title' => $title, 'action' => 'skipped_unknown_steam_type', 'detected_by' => 'steam_type_unknown'];
            }
            if (count($samples) < 15) {
                $samples[] = ['title' => $title, 'action' => 'skipped_unknown_steam_type', 'detected_by' => 'steam_type_unknown'];
            }
            continue;
        }

        $stats['candidate_dlcs']++;
        $parentGameId = findParentGameId($conn, $gameId, $meta['parent_rawg_id'], $meta['parent_title']);
        if (!$parentGameId) {
            $stats['skipped_no_parent']++;
            if ($includeAll) {
                $changes[] = ['title' => $title, 'action' => 'skipped_no_parent', 'detected_by' => 'steam_type_dlc'];
            }
            if (count($samples) < 15) {
                $samples[] = ['title' => $title, 'action' => 'skipped_no_parent', 'detected_by' => 'steam_type_dlc'];
            }
            continue;
        }

        if ($rawgId > 0) {
            $checkDlc->bind_param("i", $rawgId);
            $checkDlc->execute();
            $exists = $checkDlc->get_result()->fetch_assoc();
            if (!empty($exists['id'])) {
                if (!$isDryRun) {
                    $deleteGame->bind_param("i", $gameId);
                    $deleteGame->execute();
                }
                $stats['already_in_dlcs']++;
                if ($includeAll) {
                    $changes[] = ['title' => $title, 'action' => $isDryRun ? 'would_delete_duplicate_game' : 'deleted_duplicate_game', 'detected_by' => 'steam_type_dlc'];
                }
                if (count($samples) < 15) {
                    $samples[] = ['title' => $title, 'action' => $isDryRun ? 'would_delete_duplicate_game' : 'deleted_duplicate_game', 'detected_by' => 'steam_type_dlc'];
                }
                continue;
            }
        }

        $avgRating = isset($row['avg_rating']) ? (float)$row['avg_rating'] : 0.0;
        $totalReviews = isset($row['total_reviews']) ? (int)$row['total_reviews'] : 0;
        $releaseDate = $row['release_date'] ?: null;
        $description = $row['description'] ?? '';
        $imageUrl = $row['image_url'] ?? '';
        $portraitUrl = $row['portrait_image_url'] ?? '';
        $platforms = $row['platforms'] ?? '';
        $genre = $row['genre'] ?? '';
        $source = $row['source'] ?? 'auto';

        $stats['would_move_to_dlcs']++;
        if ($isDryRun) {
            if ($includeAll) {
                $changes[] = ['title' => $title, 'action' => 'would_move_to_dlcs', 'detected_by' => 'steam_type_dlc'];
            }
            if (count($samples) < 15) {
                $samples[] = ['title' => $title, 'action' => 'would_move_to_dlcs', 'detected_by' => 'steam_type_dlc'];
            }
            continue;
        }

        $conn->begin_transaction();
        try {
            $insertDlc->bind_param(
                "siissssssdis",
                $title,
                $parentGameId,
                $rawgId,
                $releaseDate,
                $description,
                $imageUrl,
                $portraitUrl,
                $platforms,
                $genre,
                $avgRating,
                $totalReviews,
                $source
            );
            $insertDlc->execute();

            $deleteGame->bind_param("i", $gameId);
            $deleteGame->execute();

            $conn->commit();
            $stats['moved_to_dlcs']++;
            if ($includeAll) {
                $changes[] = ['title' => $title, 'action' => 'moved_to_dlcs', 'detected_by' => 'steam_type_dlc'];
            }
            if (count($samples) < 15) {
                $samples[] = ['title' => $title, 'action' => 'moved_to_dlcs', 'detected_by' => 'steam_type_dlc'];
            }
        } catch (Throwable $txe) {
            $conn->rollback();
            $stats['errors']++;
        }
    }

    sendJsonAndExit([
        'success' => true,
        'message' => $isDryRun ? 'DLC dry-run scan complete' : 'DLC scan complete',
        'stats' => $stats,
        'samples' => $samples,
        'changes' => $includeAll ? $changes : []
    ]);
} catch (Throwable $e) {
    sendJsonAndExit([
        'success' => false,
        'error' => 'DLC scan failed',
        'details' => $e->getMessage()
    ], 500);
}

