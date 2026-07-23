<?php
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/steam_helpers.php';
session_start();

// Steam OpenID URL
define('STEAM_LOGIN_URL', 'https://steamcommunity.com/openid/login');
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/1hnd/gametracker');

// If this is the return from Steam
if (isset($_GET['openid_claimed_id'])) {
    $steamID = str_replace('https://steamcommunity.com/openid/id/', '', $_GET['openid_claimed_id']);
    
    // Update user's steam_id in database
    $stmt = $conn->prepare("UPDATE users SET steam_id = ? WHERE id = ?");
    $stmt->bind_param("si", $steamID, $_SESSION['user_id']);
    $stmt->execute();

    // Don't store in session anymore, only in database
    // $_SESSION['steam_id'] = $steamID;  // Remove this line

    // Fetch user's Steam info
    $steamUserUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?key=" . STEAM_API_KEY . "&steamids=" . $steamID;
    $steamUserInfo = json_decode(file_get_contents($steamUserUrl), true);
    
    if ($steamUserInfo && !empty($steamUserInfo['response']['players'][0])) {
        $playerInfo = $steamUserInfo['response']['players'][0];
        // Store additional Steam info if needed
        // $steamName = $playerInfo['personaname'];
        // $steamAvatar = $playerInfo['avatarfull'];
    }
    
    // Fetch and store owned games
    $ownedGames = fetchSteamOwnedGames($steamID, STEAM_API_KEY);
    if ($ownedGames && isset($ownedGames['response']['games'])) {
        foreach ($ownedGames['response']['games'] as $game) {
            $stmt = $conn->prepare("INSERT INTO steam_owned_games (user_id, steam_app_id, playtime_minutes, last_played) 
                                  VALUES (?, ?, ?, FROM_UNIXTIME(?))
                                  ON DUPLICATE KEY UPDATE 
                                  playtime_minutes = VALUES(playtime_minutes),
                                  last_played = VALUES(last_played)");
            $lastPlayed = $game['rtime_last_played'] ?? null;
            $stmt->bind_param("isii", $_SESSION['user_id'], $game['appid'], $game['playtime_forever'], $lastPlayed);
            $stmt->execute();
        }
    }
    
    // Update achievements for all games
    updateAllSteamAchievements($conn, $_SESSION['user_id']);
    
    header('Location: profile.php#achievements');
    exit();
}

// Generate OpenID parameters
$params = array(
    'openid.ns'         => 'http://specs.openid.net/auth/2.0',
    'openid.mode'       => 'checkid_setup',
    'openid.return_to'  => SITE_URL . '/auth/steam_connect.php',
    'openid.realm'      => SITE_URL,
    'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
    'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
);

// Redirect to Steam login
$steamLoginUrl = STEAM_LOGIN_URL . '?' . http_build_query($params);
header('Location: ' . $steamLoginUrl);
exit(); 