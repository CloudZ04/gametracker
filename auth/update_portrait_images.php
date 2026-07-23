<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();
ignore_user_abort(true);
@set_time_limit(0);
ini_set('max_execution_time', '0');

require_once '../includes/db.php';
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

function sendJsonAndExit(array $payload, int $statusCode = 200): void {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($payload);
    exit;
}

function getFreshIGDBAccessToken(string $clientId, string $clientSecret): array {
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
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'error' => $curlError ?: 'Failed to fetch Twitch token', 'token' => ''];
    }

    $decoded = json_decode($raw, true);
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded) || empty($decoded['access_token'])) {
        $msg = is_array($decoded) ? json_encode($decoded) : substr((string)$raw, 0, 500);
        return ['ok' => false, 'error' => "Twitch token request failed (HTTP {$httpCode}): {$msg}", 'token' => ''];
    }

    return ['ok' => true, 'error' => '', 'token' => (string)$decoded['access_token']];
}

function igdbPost(string $endpoint, string $query, string $clientId, string $accessToken): array {
    $ch = curl_init("https://api.igdb.com/v4/{$endpoint}");
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
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'http_code' => $httpCode, 'error' => $curlError ?: 'cURL request failed', 'data' => []];
    }

    $decoded = json_decode($raw, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'http_code' => $httpCode, 'error' => is_array($decoded) ? json_encode($decoded) : substr((string)$raw, 0, 500), 'data' => []];
    }

    if (!is_array($decoded)) {
        return ['ok' => false, 'http_code' => $httpCode, 'error' => 'Invalid JSON from IGDB', 'data' => []];
    }

    return ['ok' => true, 'http_code' => $httpCode, 'error' => '', 'data' => $decoded];
}

function remoteFileExists(string $url): bool {
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

function buildBestIgdbImageUrl(string $imageId, string $size = 't_cover_big'): string {
    $base = "https://images.igdb.com/igdb/image/upload/{$size}/{$imageId}";
    $extensions = ['webp', 'jpg', 'png', 'jpeg'];
    foreach ($extensions as $ext) {
        $candidate = "{$base}.{$ext}";
        if (remoteFileExists($candidate)) {
            return $candidate;
        }
    }
    return "{$base}.jpg";
}

function buildTitleVariants(string $title): array {
    $variants = [];
    $clean = trim($title);
    if ($clean === '') return [];

    $variants[] = $clean;

    // Remove common platform/edition noise that hurts IGDB matching.
    $v = preg_replace('/\s*\([^)]*\)\s*/', ' ', $clean); // Remove (...) chunks
    $v = preg_replace('/\b(Game of the Year|GOTY|Deluxe Edition|Ultimate Edition|Definitive Edition|Remastered|Complete Edition|Anniversary Edition)\b/i', '', $v);
    $v = preg_replace('/\s+/', ' ', (string)$v);
    $v = trim((string)$v);
    if ($v !== '') $variants[] = $v;

    if (str_contains($clean, ':')) {
        $beforeColon = trim((string)explode(':', $clean)[0]);
        if ($beforeColon !== '') $variants[] = $beforeColon;
    }

    // De-duplicate while preserving order.
    $unique = [];
    foreach ($variants as $item) {
        if (!in_array($item, $unique, true)) $unique[] = $item;
    }
    return $unique;
}

function normalizeTitle(string $value): string {
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9 ]+/i', ' ', $value);
    $value = preg_replace('/\s+/', ' ', (string)$value);
    return trim((string)$value);
}

function hasBadEditionHint(string $title): bool {
    return (bool)preg_match('/\b(bundle|pack|add-?on|dlc|expansion|season pass|soundtrack|mobile|ios|android|cloud version|definitive edition|gold edition|ultimate edition|deluxe edition)\b/i', $title);
}

function scoreIgdbCandidate(string $inputTitle, array $candidate): float {
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

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        sendJsonAndExit([
            'success' => false,
            'error' => 'Portrait refresh failed',
            'details' => $error['message']
        ], 500);
    }
});

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    sendJsonAndExit(['success' => false, 'error' => 'Unauthorized'], 401);
}

$clientId = IGDB_CLIENT_ID;
$clientSecret = IGDB_CLIENT_SECRET;

$tokenResult = getFreshIGDBAccessToken($clientId, $clientSecret);
if (!$tokenResult['ok']) {
    sendJsonAndExit([
        'success' => false,
        'error' => 'Failed to authenticate with IGDB',
        'details' => $tokenResult['error']
    ], 500);
}
$accessToken = $tokenResult['token'];

if (!function_exists('curl_init')) {
    sendJsonAndExit(['success' => false, 'error' => 'cURL extension is not enabled on this server'], 500);
}

function igdbSearchGameIdByTitle($gameTitle, $clientId, $accessToken) {
    $variants = buildTitleVariants((string)$gameTitle);
    if (empty($variants)) {
        return ['id' => null, 'api_error' => null];
    }

    $lastApiError = null;

    foreach ($variants as $variant) {
        $safeTitle = str_replace(['\\', '"'], ['\\\\', '\\"'], $variant);

        // 1) search and score candidates locally to avoid wrong fuzzy matches.
        $searchQuery = "search \"{$safeTitle}\"; fields id,name,category; limit 25;";
        $res = igdbPost('games', $searchQuery, $clientId, $accessToken);
        if (!$res['ok']) {
            $lastApiError = $res;
            if (in_array($res['http_code'], [401, 403], true)) {
                return ['id' => null, 'api_error' => $res];
            }
        } elseif (!empty($res['data']) && is_array($res['data'])) {
            $best = null;
            $bestScore = -1000.0;
            foreach ($res['data'] as $candidate) {
                if (empty($candidate['id'])) {
                    continue;
                }
                $score = scoreIgdbCandidate($variant, $candidate);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $candidate;
                }
            }
            if ($best && $bestScore >= 72.0) {
                return ['id' => (int)$best['id'], 'api_error' => null];
            }
        }

        // 2) constrained where-name fallback
        $fuzzyQuery = "fields id,name,category; where name ~ *\"{$safeTitle}\"*; limit 25;";
        $res = igdbPost('games', $fuzzyQuery, $clientId, $accessToken);
        if (!$res['ok']) {
            $lastApiError = $res;
            if (in_array($res['http_code'], [401, 403], true)) {
                return ['id' => null, 'api_error' => $res];
            }
        } elseif (!empty($res['data']) && is_array($res['data'])) {
            $best = null;
            $bestScore = -1000.0;
            foreach ($res['data'] as $candidate) {
                if (empty($candidate['id'])) {
                    continue;
                }
                $score = scoreIgdbCandidate($variant, $candidate);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $candidate;
                }
            }
            if ($best && $bestScore >= 78.0) {
                return ['id' => (int)$best['id'], 'api_error' => null];
            }
        }
    }

    return ['id' => null, 'api_error' => $lastApiError];
}

function igdbCoverUrlByGameId($gameId, $clientId, $accessToken) {
    $coverQuery = "fields image_id; where game = {$gameId}; limit 1;";

    $ch = curl_init('https://api.igdb.com/v4/covers');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Client-ID: {$clientId}",
        "Authorization: Bearer {$accessToken}",
        'Content-Type: text/plain'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $coverQuery);
    $coverResult = curl_exec($ch);
    curl_close($ch);

    $coverData = json_decode($coverResult, true);
    if (empty($coverData) || empty($coverData[0]['image_id'])) {
        return '';
    }

    return buildBestIgdbImageUrl((string)$coverData[0]['image_id'], 't_cover_big');
}

function fetchIGDBPortraitByTitle($title, $clientId, $accessToken) {
    $lookup = igdbSearchGameIdByTitle($title, $clientId, $accessToken);
    if (!empty($lookup['api_error'])) {
        return ['url' => '', 'api_error' => $lookup['api_error']];
    }
    if (empty($lookup['id'])) {
        return ['url' => '', 'api_error' => null];
    }
    return ['url' => igdbCoverUrlByGameId((int)$lookup['id'], $clientId, $accessToken), 'api_error' => null];
}

try {
    $result = $conn->query("SELECT id, title, portrait_image_url FROM games");
    if (!$result) {
        throw new RuntimeException('Failed to read games from database');
    }

    $total = 0;
    $updated = 0;
    $notFound = 0;
    $errors = 0;
    $igdbApiErrors = 0;
    $sampleIgdbError = null;

    $updateStmt = $conn->prepare("UPDATE games SET portrait_image_url = ? WHERE id = ?");
    if (!$updateStmt) {
        throw new RuntimeException('Failed to prepare update statement');
    }

    while ($row = $result->fetch_assoc()) {
        $total++;
        $id = (int)$row['id'];
        $title = trim($row['title'] ?? '');

        if ($title === '') {
            $errors++;
            continue;
        }

        $portrait = fetchIGDBPortraitByTitle($title, $clientId, $accessToken);
        if (!empty($portrait['api_error'])) {
            $igdbApiErrors++;
            if ($sampleIgdbError === null) {
                $sampleIgdbError = $portrait['api_error'];
            }
            usleep(50000);
            continue;
        }

        $portraitUrl = $portrait['url'] ?? '';
        if ($portraitUrl === '') {
            $notFound++;
            usleep(50000);
            continue;
        }

        $updateStmt->bind_param('si', $portraitUrl, $id);
        if ($updateStmt->execute()) {
            $updated++;
        } else {
            $errors++;
        }

        usleep(50000);
    }

    sendJsonAndExit([
        'success' => true,
        'message' => 'Portrait refresh complete',
        'stats' => [
            'total_games' => $total,
            'updated' => $updated,
            'not_found_on_igdb' => $notFound,
            'errors' => $errors,
            'igdb_api_errors' => $igdbApiErrors
        ],
        'igdb_sample_error' => $sampleIgdbError
    ]);
} catch (Throwable $e) {
    sendJsonAndExit([
        'success' => false,
        'error' => 'Portrait refresh failed',
        'details' => $e->getMessage()
    ], 500);
}

