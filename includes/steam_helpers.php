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
    
    // Merge achievement data
    $achievements = [];
    if (isset($playerAchData['playerstats']['achievements']) && isset($schemaData['game']['availableGameStats']['achievements'])) {
        $schemaAchievements = [];
        foreach ($schemaData['game']['availableGameStats']['achievements'] as $ach) {
            $schemaAchievements[$ach['name']] = $ach;
        }
        
        foreach ($playerAchData['playerstats']['achievements'] as $ach) {
            $schema = $schemaAchievements[$ach['apiname']] ?? [];
            $achievements[] = [
                'api_name' => $ach['apiname'],
                'name' => $schema['displayName'] ?? $ach['apiname'],
                'description' => $schema['description'] ?? '',
                'icon' => $schema['icon'] ?? '',
                'unlocked' => $ach['achieved'],
                'unlock_time' => $ach['unlocktime'] > 0 ? date('Y-m-d H:i:s', $ach['unlocktime']) : null
            ];
        }
    } else {
        writeLog("Missing achievement data in response. Player achievements exists: " . 
                 (isset($playerAchData['playerstats']['achievements']) ? 'yes' : 'no') . 
                 ", Schema achievements exists: " . 
                 (isset($schemaData['game']['availableGameStats']['achievements']) ? 'yes' : 'no'));
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
        
        // Insert new achievements
        $stmt = $conn->prepare("INSERT INTO steam_achievements (game_id, user_id, achievement_api_name, achievement_name, achievement_description, achievement_icon, unlocked, unlock_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $totalAchievements = count($achievements);
        
        foreach ($achievements as $ach) {
            $apiName = (string)$ach['api_name'];
            $name = (string)$ach['name'];
            $description = (string)($ach['description'] ?? '');
            $icon = (string)($ach['icon'] ?? '');
            $unlocked = (string)((int)!empty($ach['unlocked']));
            $unlockTime = $ach['unlock_time'] ?? null;
            if ($unlockTime === null) {
                $unlockTime = '';
            }

            $stmt->bind_param("iissssss", 
                $gameId,
                $userId,
                $apiName,
                $name,
                $description,
                $icon,
                $unlocked,
                $unlockTime
            );
            $stmt->execute();
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
    
    // Only games in this user's collection with a usable Steam app id
    $stmt = $conn->prepare("
        SELECT DISTINCT g.id, g.steam_app_id, g.title
        FROM user_game_status ugs
        JOIN games g ON g.id = ugs.game_id
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

    foreach ($games as $game) {
        $steamAppId = (int)$game['steam_app_id'];
        if ($steamAppId <= 0) {
            $skipped++;
            continue;
        }

        writeLog("Processing collection game ID: {$game['id']} ({$game['title']}), Steam App ID: {$steamAppId}");
        $status = updateSteamAchievements($conn, $userId, (int)$game['id'], $steamAppId, STEAM_API_KEY, true);

        if ($status === 'updated') {
            $updated++;
        } else {
            $skipped++;
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
        'message' => "Updated {$updated} collection game(s), skipped {$skipped}. Existing achievements outside collections were kept.",
    ];
} 

function searchSteamGame($title, $apiKey) {
    writeLog("Searching Steam for game: $title");
    
    try {
        // First try the Steam Store API
        $searchUrl = "https://api.steampowered.com/ISteamApps/GetAppList/v2/";
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'GameTracker/1.0'
            ]
        ]);
        
        $response = @file_get_contents($searchUrl, false, $context);
        if ($response === false) {
            writeLog("Error fetching Steam app list: " . error_get_last()['message']);
            return null;
        }
        
        $data = json_decode($response, true);
        if (!$data || !isset($data['applist']['apps'])) {
            writeLog("Invalid response format from Steam API");
            return null;
        }
        
        // Clean up the search title
        $searchTitle = strtolower(trim($title));
        $searchTitle = preg_replace('/[^\w\s]/', '', $searchTitle);
        
        // Search for exact or close matches
        $matches = [];
        foreach ($data['applist']['apps'] as $app) {
            if (empty($app['name']) || empty($app['appid'])) continue;
            
            $appTitle = strtolower(trim($app['name']));
            $appTitle = preg_replace('/[^\w\s]/', '', $appTitle);
            
            if ($appTitle === $searchTitle) {
                // Exact match
                writeLog("Found exact match: {$app['appid']} - {$app['name']}");
                return (string)$app['appid']; // Convert to string to ensure clean JSON
            }
            
            // Check if the search title is contained in the app title
            if (strpos($appTitle, $searchTitle) !== false || strpos($searchTitle, $appTitle) !== false) {
                $matches[] = $app;
            }
        }
        
        // If we found matches, return the first one
        if (!empty($matches)) {
            writeLog("Found similar match: {$matches[0]['appid']} - {$matches[0]['name']}");
            return (string)$matches[0]['appid']; // Convert to string to ensure clean JSON
        }
        
        writeLog("No matches found for: $title");
        return null;
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

function findAndUpdateSteamIds($conn, $apiKey) {
    try {
        writeLog("Starting bulk Steam ID update");
        
        // Get all games without Steam App IDs
        $stmt = $conn->prepare("SELECT id, title FROM games WHERE steam_app_id IS NULL");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $updateCount = 0;
        $totalGames = $result->num_rows;
        writeLog("Found $totalGames games without Steam App IDs");
        
        while ($game = $result->fetch_assoc()) {
            try {
                writeLog("Processing game: {$game['title']}");
                $steamAppId = searchSteamGame($game['title'], $apiKey);
                
                if ($steamAppId) {
                    if (updateGameSteamId($conn, $game['id'], $steamAppId)) {
                        $updateCount++;
                    }
                }
                
                // Add a small delay to avoid rate limiting
                usleep(100000); // 100ms delay
            } catch (Exception $e) {
                writeLog("Error processing game {$game['title']}: " . $e->getMessage());
                continue; // Skip this game but continue with others
            }
        }
        
        writeLog("Updated $updateCount out of $totalGames games with Steam App IDs");
        return $updateCount;
    } catch (Exception $e) {
        writeLog("Error in findAndUpdateSteamIds: " . $e->getMessage());
        throw $e; // Re-throw to be caught by the main error handler
    }
} 