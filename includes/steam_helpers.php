<?php

function writeLog($message) {
    $logFile = __DIR__ . '/steam_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

function fetchSteamOwnedGames($steamId, $apiKey) {
    writeLog("Fetching owned games for Steam ID: $steamId");
    $url = "https://api.steampowered.com/IPlayerService/GetOwnedGames/v1/?key={$apiKey}&steamid={$steamId}&include_appinfo=1";
    $response = @file_get_contents($url);
    if ($response === false) {
        writeLog("Error fetching owned games: " . error_get_last()['message']);
        return null;
    }
    
    $data = json_decode($response, true);
    writeLog("Owned games response: " . print_r($data, true));
    return $data;
}

function fetchSteamAchievements($steamId, $appId, $apiKey) {
    writeLog("Fetching achievements for Steam ID: $steamId, App ID: $appId");
    
    // Get player achievements
    $playerAchUrl = "https://api.steampowered.com/ISteamUserStats/GetPlayerAchievements/v1/?key={$apiKey}&steamid={$steamId}&appid={$appId}";
    writeLog("Fetching from URL: $playerAchUrl");
    $playerAch = @file_get_contents($playerAchUrl);
    if ($playerAch === false) {
        writeLog("Error fetching player achievements: " . error_get_last()['message']);
        return null;
    }
    $playerAchData = json_decode($playerAch, true);
    if (!empty($playerAchData['playerstats']['error'])) {
        writeLog("Steam player achievements error for app {$appId}: " . $playerAchData['playerstats']['error']);
    }
    
    // Get achievement schema (names, descriptions, icons)
    $schemaUrl = "https://api.steampowered.com/ISteamUserStats/GetSchemaForGame/v2/?key={$apiKey}&appid={$appId}";
    writeLog("Fetching schema for app {$appId}");
    $schema = @file_get_contents($schemaUrl);
    if ($schema === false) {
        writeLog("Error fetching achievement schema: " . error_get_last()['message']);
        return null;
    }
    $schemaData = json_decode($schema, true);
    
    
    $achievements = [];
    if (empty($playerAchData['playerstats']['achievements']) || !is_array($playerAchData['playerstats']['achievements'])) {
        writeLog("Missing player achievements for app {$appId}. error=" . ($playerAchData['playerstats']['error'] ?? 'none'));
        return [];
    }

    $schemaAchievements = [];
    if (!empty($schemaData['game']['availableGameStats']['achievements']) && is_array($schemaData['game']['availableGameStats']['achievements'])) {
        foreach ($schemaData['game']['availableGameStats']['achievements'] as $ach) {
            $schemaAchievements[$ach['name']] = $ach;
        }
    } else {
        writeLog("Schema missing for app {$appId}; saving with API names only");
    }

    foreach ($playerAchData['playerstats']['achievements'] as $ach) {
        $schema = $schemaAchievements[$ach['apiname']] ?? [];
        $unlockTs = (int)($ach['unlocktime'] ?? 0);
        $achievements[] = [
            'api_name' => $ach['apiname'],
            'name' => $schema['displayName'] ?? $ach['apiname'],
            'description' => $schema['description'] ?? '',
            'icon' => $schema['icon'] ?? '',
            'unlocked' => (int)($ach['achieved'] ?? 0),
            'unlock_time' => $unlockTs > 0 ? date('Y-m-d H:i:s', $unlockTs) : null
        ];
    }
    
    writeLog("Processed " . count($achievements) . " achievements");
    return $achievements;
}

function updateSteamAchievements($conn, $userId, $gameId, $steamAppId, $apiKey, $requireUnlocked = false) {
    writeLog("Updating achievements for User ID: $userId, Game ID: $gameId, Steam App ID: $steamAppId");
    
    $steamId = null;
    
    // Get user's Steam ID
    $stmt = $conn->prepare("SELECT steam_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $steamId = $row['steam_id'];
    }
    if (!$steamId) {
        writeLog("No Steam ID found for user $userId");
        return 'no_steam_id';
    }
    
    // Fetch achievements from Steam
    $achievements = fetchSteamAchievements($steamId, $steamAppId, $apiKey);
    if (!$achievements || count($achievements) === 0) {
        writeLog("No achievements fetched for game $steamAppId");
        return 'no_data';
    }

    $unlockedCount = 0;
    foreach ($achievements as $ach) {
        if (!empty($ach['unlocked'])) $unlockedCount++;
    }

    // Option A: only write when the user has unlocked at least one achievement.
    // Important: do NOT delete existing rows when skipping.
    if ($requireUnlocked && $unlockedCount < 1) {
        writeLog("Skipping game $steamAppId — 0 unlocked achievements (existing data kept)");
        return 'skipped_no_unlocks';
    }
    
    writeLog("Processing " . count($achievements) . " achievements for game $steamAppId ({$unlockedCount} unlocked)");
    
    // Begin transaction
    $conn->begin_transaction();
    try {
        // Replace achievements for THIS user/game only
        $stmt = $conn->prepare("DELETE FROM steam_achievements WHERE user_id = ? AND game_id = ?");
        $stmt->bind_param("ii", $userId, $gameId);
        $stmt->execute();
        
        // Insert new achievements (unlock_time NULL for locked achievements — empty string breaks DATETIME inserts)
        $stmt = $conn->prepare("INSERT INTO steam_achievements (game_id, user_id, achievement_api_name, achievement_name, achievement_description, achievement_icon, unlocked, unlock_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $totalAchievements = count($achievements);
        
        foreach ($achievements as $ach) {
            $apiName = (string)$ach['api_name'];
            $name = (string)$ach['name'];
            $description = (string)($ach['description'] ?? '');
            $icon = (string)($ach['icon'] ?? '');
            $unlocked = (int)!empty($ach['unlocked']);
            $unlockTime = !empty($ach['unlock_time']) ? (string)$ach['unlock_time'] : null;

            $stmt->bind_param("iissssis", 
                $gameId,
                $userId,
                $apiName,
                $name,
                $description,
                $icon,
                $unlocked,
                $unlockTime
            );
            if (!$stmt->execute()) {
                throw new Exception('Insert failed for ' . $apiName . ': ' . $stmt->error);
            }
        }
        
        // Update achievement stats
        $completionPercentage = ($totalAchievements > 0) ? ($unlockedCount / $totalAchievements) * 100 : 0;
        
        $stmt = $conn->prepare("INSERT INTO steam_achievement_stats (game_id, user_id, total_achievements, unlocked_achievements, completion_percentage) 
                               VALUES (?, ?, ?, ?, ?) 
                               ON DUPLICATE KEY UPDATE 
                               total_achievements = VALUES(total_achievements),
                               unlocked_achievements = VALUES(unlocked_achievements),
                               completion_percentage = VALUES(completion_percentage)");
        $stmt->bind_param("iiiid", $gameId, $userId, $totalAchievements, $unlockedCount, $completionPercentage);
        $stmt->execute();
        
        $conn->commit();
        writeLog("Successfully updated achievements for game $steamAppId");
        return 'updated';
    } catch (Exception $e) {
        $conn->rollback();
        writeLog("Error updating Steam achievements: " . $e->getMessage());
        return 'error';
    }
}

/**
 * Refresh achievements for games in the user's collection only.
 * Existing achievement rows for games outside the collection are left untouched.
 * Only writes/updates a game when Steam reports 1+ unlocked achievements.
 */
function updateAllSteamAchievements($conn, $userId) {
    writeLog("Updating collection achievements for User ID: $userId");
    
    // Get user's Steam ID
    $stmt = $conn->prepare("SELECT steam_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!($row = $result->fetch_assoc()) || empty($row['steam_id'])) {
        writeLog("No Steam ID found for user $userId");
        return [
            'success' => false,
            'updated' => 0,
            'skipped' => 0,
            'checked' => 0,
            'message' => 'No Steam ID found for user',
        ];
    }
    $steamId = $row['steam_id'];
    writeLog("Found Steam ID: $steamId");
    
    // Only games in this user's collection with a usable Steam app id.
    // Also pull existing completion so 100% games can be skipped without hitting Steam.
    $stmt = $conn->prepare("
        SELECT DISTINCT g.id, g.steam_app_id, g.title,
               sas.total_achievements, sas.unlocked_achievements, sas.completion_percentage
        FROM user_game_status ugs
        JOIN games g ON g.id = ugs.game_id
        LEFT JOIN steam_achievement_stats sas
            ON sas.game_id = g.id AND sas.user_id = ugs.user_id
        WHERE ugs.user_id = ?
          AND g.steam_app_id IS NOT NULL
          AND g.steam_app_id != ''
          AND g.steam_app_id != '0'
          AND g.steam_app_id != '17760'
        ORDER BY g.title ASC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $games = $result->fetch_all(MYSQLI_ASSOC);
    writeLog("Found " . count($games) . " collection games with Steam App IDs");

    $updated = 0;
    $skipped = 0;
    $checked = count($games);
    $updatedTitles = [];
    $skippedDetails = [];

    foreach ($games as $game) {
        $steamAppId = (int)$game['steam_app_id'];
        if ($steamAppId <= 0) {
            $skipped++;
            $skippedDetails[] = ['title' => $game['title'], 'reason' => 'invalid_steam_app_id'];
            continue;
        }

        $total = (int)($game['total_achievements'] ?? 0);
        $unlocked = (int)($game['unlocked_achievements'] ?? 0);
        $completion = (float)($game['completion_percentage'] ?? 0);
        $alreadyComplete = $total > 0 && ($unlocked >= $total || $completion >= 100);

        // Skip 100% games — no Steam API call needed
        if ($alreadyComplete) {
            $skipped++;
            $skippedDetails[] = [
                'title' => $game['title'],
                'reason' => 'already_complete',
                'steam_app_id' => $steamAppId,
                'progress' => "{$unlocked}/{$total}",
            ];
            writeLog("Skipping {$game['title']} — already 100% ({$unlocked}/{$total})");
            continue;
        }

        writeLog("Processing collection game ID: {$game['id']} ({$game['title']}), Steam App ID: {$steamAppId}");
        $status = updateSteamAchievements($conn, $userId, (int)$game['id'], $steamAppId, STEAM_API_KEY, true);

        if ($status === 'updated') {
            $updated++;
            $updatedTitles[] = $game['title'];
        } else {
            $skipped++;
            $skippedDetails[] = ['title' => $game['title'], 'reason' => $status, 'steam_app_id' => $steamAppId];
        }

        // Small delay to be polite to Steam's API
        usleep(150000);
    }
    
    writeLog("Collection refresh complete: updated {$updated}, skipped {$skipped}, checked {$checked}");
    return [
        'success' => true,
        'updated' => $updated,
        'skipped' => $skipped,
        'checked' => $checked,
        'updated_titles' => $updatedTitles,
        'skipped_details' => array_slice($skippedDetails, 0, 20),
        'message' => "Updated {$updated} collection game(s), skipped {$skipped}. Existing achievements outside collections were kept.",
    ];
} 

/**
 * Known junk / placeholder Steam App IDs that should be re-matched.
 */
function isBadSteamAppId($steamAppId) {
    if ($steamAppId === null) {
        return true;
    }
    $id = trim((string)$steamAppId);
    if ($id === '' || $id === '0') {
        return true;
    }
    // Known placeholder / wrong IDs (Steam "Free to Play" demo junk, etc.)
    $junk = ['17760'];
    return in_array($id, $junk, true);
}

function normalizeSteamTitle($title) {
    $t = strtolower(trim((string)$title));
    // Normalize common edition / trademark noise
    $t = str_replace(['™', '®', '©'], '', $t);
    $t = preg_replace('/[^\w\s]/', ' ', $t);
    $t = preg_replace('/\s+/', ' ', $t);
    return trim($t);
}

/**
 * Download Steam's full app list once. Returns array of ['appid'=>..., 'name'=>...].
 */
function fetchSteamAppList() {
    writeLog("Downloading Steam app list (once)");
    $url = "https://api.steampowered.com/ISteamApps/GetAppList/v2/";
    $context = stream_context_create([
        'http' => [
            'timeout' => 120,
            'user_agent' => 'GameTracker/1.0'
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        $err = error_get_last();
        throw new Exception('Failed to download Steam app list: ' . ($err['message'] ?? 'unknown error'));
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['applist']['apps']) || !is_array($data['applist']['apps'])) {
        throw new Exception('Invalid Steam app list response');
    }

    writeLog("Steam app list downloaded: " . count($data['applist']['apps']) . " apps");
    return $data['applist']['apps'];
}

/**
 * Build normalized-title => candidates map for fast exact lookups.
 */
function buildSteamAppExactIndex(array $apps) {
    $index = [];
    foreach ($apps as $app) {
        if (empty($app['name']) || empty($app['appid'])) {
            continue;
        }
        $norm = normalizeSteamTitle($app['name']);
        if ($norm === '') {
            continue;
        }
        $index[$norm][] = [
            'appid' => (string)$app['appid'],
            'name' => $app['name'],
        ];
    }
    return $index;
}

/**
 * Match a local game title against a preloaded Steam app list.
 * Returns ['appid'=>string, 'name'=>string, 'match'=>'exact'|'fuzzy'] or null.
 */
function matchSteamAppFromList($title, array $apps, array $exactIndex = null) {
    $search = normalizeSteamTitle($title);
    if ($search === '') {
        return null;
    }

    if ($exactIndex === null) {
        $exactIndex = buildSteamAppExactIndex($apps);
    }

    // 1) Exact normalized match
    if (isset($exactIndex[$search])) {
        $best = $exactIndex[$search][0];
        // Prefer the shortest Steam name among exact normalized hits (usually the base game)
        foreach ($exactIndex[$search] as $candidate) {
            if (strlen($candidate['name']) < strlen($best['name'])) {
                $best = $candidate;
            }
        }
        writeLog("Exact match for \"$title\": {$best['appid']} - {$best['name']}");
        return [
            'appid' => $best['appid'],
            'name' => $best['name'],
            'match' => 'exact',
        ];
    }

    // 2) Fuzzy: require high length similarity; avoid tiny substring traps
    $searchLen = strlen($search);
    if ($searchLen < 3) {
        return null;
    }

    $best = null;
    $bestScore = 0.0;

    foreach ($apps as $app) {
        if (empty($app['name']) || empty($app['appid'])) {
            continue;
        }
        $appTitle = normalizeSteamTitle($app['name']);
        if ($appTitle === '') {
            continue;
        }
        $appLen = strlen($appTitle);

        // Skip if neither contains the other
        if (strpos($appTitle, $search) === false && strpos($search, $appTitle) === false) {
            continue;
        }

        // Reject very short side of a containment match (e.g. "Halo" vs "Halo Infinite")
        $minLen = min($searchLen, $appLen);
        $maxLen = max($searchLen, $appLen);
        if ($minLen < 5 && $minLen !== $maxLen) {
            continue;
        }

        $lenRatio = $minLen / $maxLen;
        if ($lenRatio < 0.75) {
            continue;
        }

        // similar_text as a secondary quality signal
        similar_text($search, $appTitle, $pct);
        $score = ($lenRatio * 0.55) + (($pct / 100) * 0.45);

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = [
                'appid' => (string)$app['appid'],
                'name' => $app['name'],
                'match' => 'fuzzy',
                'score' => $score,
            ];
        }
    }

    // Require a reasonably confident fuzzy match
    if ($best !== null && $bestScore >= 0.82) {
        writeLog(sprintf(
            'Fuzzy match for "%s": %s - %s (score %.2f)',
            $title,
            $best['appid'],
            $best['name'],
            $bestScore
        ));
        return $best;
    }

    writeLog("No match for: $title");
    return null;
}

/**
 * Single-title lookup (downloads app list). Prefer matchSteamAppFromList in bulk flows.
 */
function searchSteamGame($title, $apiKey = null) {
    writeLog("Searching Steam for game: $title");
    try {
        $apps = fetchSteamAppList();
        $match = matchSteamAppFromList($title, $apps);
        return $match ? $match['appid'] : null;
    } catch (Exception $e) {
        writeLog("Error in searchSteamGame: " . $e->getMessage());
        return null;
    }
}

function updateGameSteamId($conn, $gameId, $steamAppId) {
    try {
        writeLog("Updating game $gameId with Steam App ID: $steamAppId");

        $stmt = $conn->prepare("UPDATE games SET steam_app_id = ? WHERE id = ?");
        $stmt->bind_param("si", $steamAppId, $gameId);
        $success = $stmt->execute();

        if ($success) {
            writeLog("Successfully updated game with Steam App ID");
        } else {
            writeLog("Failed to update game with Steam App ID: " . $conn->error);
        }

        return $success;
    } catch (Exception $e) {
        writeLog("Error in updateGameSteamId: " . $e->getMessage());
        return false;
    }
}

/**
 * Fill missing/junk steam_app_id values by matching against Steam's app list (downloaded once).
 * Returns an associative result array (not just a count).
 */
function findAndUpdateSteamIds($conn, $apiKey = null) {
    writeLog("Starting bulk Steam ID update");

    $apps = fetchSteamAppList();
    $exactIndex = buildSteamAppExactIndex($apps);

    // Missing, empty, zero, or known junk IDs — leave valid IDs alone
    $sql = "SELECT id, title, steam_app_id
            FROM games
            WHERE steam_app_id IS NULL
               OR TRIM(steam_app_id) = ''
               OR TRIM(steam_app_id) = '0'
               OR TRIM(steam_app_id) = '17760'";
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception('Failed to query games needing Steam IDs: ' . $conn->error);
    }

    $total = $result->num_rows;
    $updated = 0;
    $exact = 0;
    $fuzzy = 0;
    $unmatched = [];
    $updatedTitles = [];

    writeLog("Found $total games needing Steam App IDs");

    while ($game = $result->fetch_assoc()) {
        try {
            writeLog("Processing game: {$game['title']} (current: " . ($game['steam_app_id'] ?? 'NULL') . ")");
            $match = matchSteamAppFromList($game['title'], $apps, $exactIndex);

            if (!$match) {
                $unmatched[] = $game['title'];
                continue;
            }

            // Don't "update" to the same junk/same value
            if ((string)$game['steam_app_id'] === (string)$match['appid']) {
                continue;
            }

            if (updateGameSteamId($conn, (int)$game['id'], $match['appid'])) {
                $updated++;
                if (($match['match'] ?? '') === 'exact') {
                    $exact++;
                } else {
                    $fuzzy++;
                }
                $updatedTitles[] = [
                    'title' => $game['title'],
                    'steam_app_id' => $match['appid'],
                    'steam_name' => $match['name'],
                    'match' => $match['match'],
                ];
            }
        } catch (Exception $e) {
            writeLog("Error processing game {$game['title']}: " . $e->getMessage());
            continue;
        }
    }

    writeLog("Updated $updated / $total games with Steam App IDs (exact=$exact, fuzzy=$fuzzy, unmatched=" . count($unmatched) . ")");

    return [
        'updated' => $updated,
        'total' => $total,
        'exact' => $exact,
        'fuzzy' => $fuzzy,
        'unmatched' => $unmatched,
        'updated_titles' => $updatedTitles,
    ];
}