<?php
require_once __DIR__ . '/config.php';

function getSteamAchievements($steamId, $appId) {
    $apiKey = STEAM_API_KEY;
    
    // Get user achievements
    $userAchUrl = "https://api.steampowered.com/ISteamUserStats/GetPlayerAchievements/v1/?appid={$appId}&steamid={$steamId}&key={$apiKey}";
    $userAchData = @file_get_contents($userAchUrl);
    
    if ($userAchData === false) {
        return null; // Game might not be owned or private profile
    }
    
    $userAchievements = json_decode($userAchData, true);
    
    // Get achievement schema (names, descriptions, icons)
    $schemaUrl = "https://api.steampowered.com/ISteamUserStats/GetSchemaForGame/v2/?appid={$appId}&key={$apiKey}";
    $schemaData = @file_get_contents($schemaUrl);
    if ($schemaData === false) {
        return null;
    }
    $schema = json_decode($schemaData, true);
    
    if (!isset($userAchievements['playerstats']['achievements']) || 
        !isset($schema['game']['availableGameStats']['achievements'])) {
        return null;
    }
    
    $achievements = [];
    $schemaAchievements = array_column($schema['game']['availableGameStats']['achievements'], null, 'name');
    
    foreach ($userAchievements['playerstats']['achievements'] as $ach) {
        if (isset($schemaAchievements[$ach['apiname']])) {
            $schemaAch = $schemaAchievements[$ach['apiname']];
            $achievements[] = [
                'displayName' => $schemaAch['displayName'] ?? $ach['apiname'],
                'description' => $schemaAch['description'] ?? 'No description available',
                'icon' => $schemaAch['icon'] ?? '',
                'achieved' => (bool)$ach['achieved'],
                'unlock_time' => $ach['unlocktime'] > 0 ? date('Y-m-d H:i:s', $ach['unlocktime']) : null
            ];
        } else {
            // If schema achievement not found, use basic info from user achievements
            $achievements[] = [
                'displayName' => $ach['apiname'],
                'description' => 'No description available',
                'icon' => '',
                'achieved' => (bool)$ach['achieved'],
                'unlock_time' => $ach['unlocktime'] > 0 ? date('Y-m-d H:i:s', $ach['unlocktime']) : null
            ];
        }
    }
    
    // Sort achievements: unlocked first, then alphabetically
    usort($achievements, function($a, $b) {
        if ($a['achieved'] !== $b['achieved']) {
            return $b['achieved'] <=> $a['achieved']; // Unlocked first
        }
        return $a['displayName'] <=> $b['displayName']; // Then alphabetically
    });
    
    return $achievements;
}

function getSteamGameInfo($appId) {
    $url = "https://store.steampowered.com/api/appdetails?appids={$appId}";
    $data = @file_get_contents($url);
    if ($data === false) {
        return null;
    }
    $gameInfo = json_decode($data, true);
    
    if ($gameInfo && $gameInfo[$appId]['success']) {
        return $gameInfo[$appId]['data'];
    }
    
    return null;
}

// Example usage:
/*
$steamId = '76561198xxxxxxxxx'; // User's Steam ID
$appId = '570'; // Dota 2's App ID
$achievements = getSteamAchievements($steamId, $appId);

foreach ($achievements as $achievement) {
    echo "Name: " . $achievement['name'] . "\n";
    echo "Description: " . $achievement['description'] . "\n";
    echo "Achieved: " . ($achievement['achieved'] ? 'Yes' : 'No') . "\n";
    if ($achievement['achieved']) {
        echo "Unlocked: " . date('Y-m-d H:i:s', $achievement['unlocktime']) . "\n";
    }
    echo "Icon: " . $achievement['icon'] . "\n\n";
}
*/