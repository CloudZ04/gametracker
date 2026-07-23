<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// Return JSON only
header('Content-Type: application/json');
// ensure no stray output before JSON
while (ob_get_level()) { ob_end_clean(); }

require_once '../includes/db.php';

try {
  // ---------- INPUT ----------
  $raw = file_get_contents('php://input');
  $body = json_decode($raw, true) ?: [];
  $gameId = isset($body['game_id']) ? (int)$body['game_id'] : 0;
  $guessTitle = isset($body['guess_title']) && $body['guess_title'] !== '' ? $body['guess_title'] : null;

  if ($gameId <= 0) { http_response_code(400); echo json_encode(['error'=>'Missing/invalid game_id']); exit; }

  // ---------- CONFIG (hard-coded like your RAWG script) ----------
  $GB_KEY  = '47c92ad074ff53abc54209d8d75d37046496bcd8';  // <- put your real key here
  $GB_BASE = 'https://www.giantbomb.com/api';
  $UA      = 'gametracker/1.0 (+localhost)';

  if (empty($GB_KEY)) { http_response_code(500); echo json_encode(['error'=>'Giant Bomb API key not set']); exit; }

  // ---------- MYSQL ----------
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
  $conn->set_charset('utf8mb4');

  // ---------- HTTP helper ----------
  $gb_get = function(string $url) use ($UA) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 25,
      CURLOPT_HTTPHEADER => ["User-Agent: $UA"],
      CURLOPT_FOLLOWLOCATION => true
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 400 || !$res) return [null, $code ?: 500];
    return [json_decode($res, true), 200];
  };

  // ---------- LOAD GAME ----------
  $stmt = $conn->prepare("SELECT id, title, giantbomb_guid FROM games WHERE id = ?");
  $stmt->bind_param("i", $gameId);
  $stmt->execute();
  $game = $stmt->get_result()->fetch_assoc();
  if (!$game) { http_response_code(404); echo json_encode(['error'=>'Game not found']); exit; }

  $title = $game['title'];
  $guid  = $game['giantbomb_guid'];
  $searchTitle = $guessTitle ?: $title;

  // ---------- RESOLVE GUID (if missing) ----------
  if (!$guid) {
    $url = $GB_BASE . "/search/?api_key=" . urlencode($GB_KEY)
         . "&format=json&resources=game&query=" . urlencode($searchTitle);
    [$search, $code] = $gb_get($url);
    if ($code !== 200 || empty($search['results'][0]['guid'])) {
      http_response_code(502);
      echo json_encode(['error'=>'Could not resolve Giant Bomb GUID', 'http_code'=>$code, 'query'=>$searchTitle]); exit;
    }
    $guid = $search['results'][0]['guid'];

    $upd = $conn->prepare("UPDATE games SET giantbomb_guid = ? WHERE id = ?");
    $upd->bind_param("si", $guid, $gameId);
    $upd->execute();
    usleep(15000);
  }

  // ---------- FETCH GAME DETAIL (characters only) ----------
  $detailUrl = $GB_BASE . "/game/" . rawurlencode($guid) . "/?api_key="
             . urlencode($GB_KEY) . "&format=json&field_list=characters";
  [$detail, $dcode] = $gb_get($detailUrl);
  if ($dcode !== 200 || !isset($detail['results'])) {
    http_response_code(502);
    echo json_encode(['error'=>'Failed to fetch game detail', 'http_code'=>$dcode]); exit;
  }

  $chars = $detail['results']['characters'] ?? [];
  if (!$chars) {
    $conn->query("UPDATE games SET characters_synced_at = NOW() WHERE id = " . (int)$gameId);
    echo json_encode(['message'=>'No characters returned', 'linked_count'=>0, 'giantbomb_guid'=>$guid]); exit;
  }

  // ---------- PREP STATEMENTS ----------
  $insChar = $conn->prepare("
    INSERT INTO characters (name, slug, giantbomb_id, image_url, portrait_image_url, bio)
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      name = VALUES(name),
      slug = COALESCE(VALUES(slug), slug),
      image_url = COALESCE(VALUES(image_url), image_url),
      portrait_image_url = COALESCE(VALUES(portrait_image_url), portrait_image_url),
      bio = COALESCE(VALUES(bio), bio)
  ");

  $selCharId = $conn->prepare("SELECT id, image_url, portrait_image_url FROM characters WHERE giantbomb_id = ?");
  $updImg    = $conn->prepare("UPDATE characters SET image_url = COALESCE(?, image_url), portrait_image_url = COALESCE(?, portrait_image_url) WHERE id = ?");

  $linkStmt = $conn->prepare("
    INSERT INTO game_characters (game_id, character_id, role, is_playable, source_score)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      role = COALESCE(VALUES(role), role),
      is_playable = VALUES(is_playable),
      source_score = GREATEST(source_score, VALUES(source_score))
  ");

  $insMedia = $conn->prepare("
    INSERT INTO character_media (character_id, game_id, kind, url, width, height, variant, credit, is_primary)
    VALUES (?, ?, 'headshot', ?, NULL, NULL, NULL, 'Giant Bomb', 1)
    ON DUPLICATE KEY UPDATE url = VALUES(url)
  ");

// ---------- TRANSACTION: validate appearance + upsert + link + images ----------
$conn->begin_transaction();

$created = 0; $linked = 0;
$idx = 0;
$debugLogs = []; // Initialize debug logs array once

// prep statements used below
$insMedia = $conn->prepare("
  INSERT INTO character_media (character_id, game_id, kind, url, width, height, variant, credit, is_primary)
  VALUES (?, ?, 'headshot', ?, NULL, NULL, NULL, 'Giant Bomb', 1)
  ON DUPLICATE KEY UPDATE url = VALUES(url)
");

foreach ($chars as $c) {
  $name = $c['name'] ?? null;
  if (!$name) continue;

  // numeric id (e.g., 658) and GUID (e.g., "3005-658")
  $gbId     = isset($c['id']) ? (int)$c['id'] : null;
  $guidChar = $c['guid'] ?? null;

  // derive GUID from site_detail_url if missing
  if (!$guidChar && !empty($c['site_detail_url'])) {
    $path = parse_url($c['site_detail_url'], PHP_URL_PATH);
    if ($path) $guidChar = ltrim(basename($path), '/'); // "3005-XXX"
  }

// --- soft validation: match by exact GUID OR name contains base title ---
$baseTitle = strtolower(
  trim(
    preg_replace(['/\s*\(.*?\)/','/\s*-\s*.*/'], '', $title) // drop (...) and " - edition"
  )
);
// also build a looser keyword (last 2 words of title), helps with long prefixes
$words = preg_split('/\s+/', $baseTitle);
$looseKey = strtolower(implode(' ', array_slice($words, max(0, count($words)-2)))); // e.g., "black ops"

$appearsInGame = false;
$best = null; // image url from character detail if present

if ($guidChar) {
  $charUrl = $GB_BASE . "/character/" . rawurlencode($guidChar)
           . "/?api_key=" . urlencode($GB_KEY)
           . "&format=json&field_list=image,games,name";
  [$cdetail, $ccode] = $gb_get($charUrl);

  if ($ccode === 200 && !empty($cdetail['results'])) {
    $gamesList = $cdetail['results']['games'] ?? [];
    foreach ($gamesList as $ginfo) {
      $gGuid = $ginfo['guid'] ?? '';
      $gName = strtolower($ginfo['name'] ?? '');
      if ($gGuid === $guid
          || ($gName && (strpos($gName, $baseTitle) !== false || ($looseKey && strpos($gName, $looseKey) !== false)))
          || (strpos(strtolower($cdetail['results']['name'] ?? ''), $looseKey) !== false)) {
        $appearsInGame = true; break;
      }
    }
    $imgObj = $cdetail['results']['image'] ?? null;
    $best   = $imgObj ? ($imgObj['super_url'] ?? $imgObj['medium_url'] ?? $imgObj['small_url'] ?? $imgObj['icon_url'] ?? null) : null;
  }
}

if (!$appearsInGame) { usleep(6000); continue; }

  // 2) UPSERT character
  // use GUID string as slug-ish identifier if present
  $slug = $guidChar ?: ( !empty($c['site_detail_url']) ? basename(parse_url($c['site_detail_url'], PHP_URL_PATH)) : null );
  // Choose the best available image from detail or fallback from list item
  $img  = $best
      ?? ($c['image']['super_url']  ?? null)
      ?? ($c['image']['medium_url'] ?? null)
      ?? ($c['image']['small_url']  ?? null)
      ?? ($c['image']['icon_url']   ?? null);
  $bio  = null; $portrait = $img;

  $insChar->bind_param("ssisss", $name, $slug, $gbId, $img, $portrait, $bio);
  $insChar->execute();
  if ($conn->affected_rows > 0) $created++;

  // fetch local id + whether we already have images
  $selCharId->bind_param("i", $gbId);
  $selCharId->execute();
  $row = $selCharId->get_result()->fetch_assoc();
  if (!$row) { usleep(6000); continue; }

  $localId = (int)$row['id'];
  $hasImg  = !empty($row['image_url']) || !empty($row['portrait_image_url']);

  // 3) Enhanced character ranking with Wikipedia API research
  // Get release date for Wikipedia search
  $releaseYear = null;
  $dateStmt = $conn->prepare("SELECT release_date FROM games WHERE id = ?");
  $dateStmt->bind_param("i", $gameId);
  $dateStmt->execute();
  $dateResult = $dateStmt->get_result()->fetch_assoc();
  if ($dateResult && $dateResult['release_date']) {
    $releaseYear = date('Y', strtotime($dateResult['release_date']));
  }
  
  // Search Wikipedia for character information
  $wikiInfo = searchWikipediaForCharacter($name, $title, $releaseYear, $debugLogs);
  $wikiBoost = 0;
  if ($wikiInfo) {
    $wikiBoost = analyzeWikipediaContent($wikiInfo, $name, $debugLogs);
    $debugLogs[] = "Wikipedia boost for $name: +$wikiBoost points";
  } else {
    $debugLogs[] = "No Wikipedia data found for $name";
  }
  
  // Calculate importance score using ONLY Wikipedia data (no Giant Bomb order)
  $baseScore = 0;
  $totalScore = $wikiBoost;
  
  // Determine role based on calculated importance score
  if     ($totalScore >= 16) { $role = 'main';       $srcScore = 25; }
  elseif ($totalScore >= 10) { $role = 'supporting'; $srcScore = 12; }
  elseif ($totalScore >= 4)  { $role = 'minor';      $srcScore = 6;  }
  else                       { $role = 'cameo';      $srcScore = 2;  }

  // Require at least some external evidence to keep 'main'. If boost is low, downgrade to supporting.
  if ($role === 'main' && $wikiBoost < 8) {
    $debugLogs[] = "Downgraded $name from main to supporting due to low/no Wikipedia evidence";
    $role = 'supporting';
    $srcScore = 12;
  }

  // Determine playable using Wikipedia text when available, else fall back to 0
  $isPlayable = 0;
  if (!empty($wikiInfo['extract'])) {
    $txtRaw = $wikiInfo['extract'];
    $txt = strtolower($txtRaw);
    $fullName = strtolower($name);
    $firstName = strtolower(explode(' ', $name)[0]);

    // Proximity-based patterns: require the name near player-control semantics
    $makePatternsFor = function(string $needle) {
      $n = preg_quote($needle, '/');
      return [
        '/(player|players)\s+(control|controls|controlled)\s+[^\.\n]{0,60}\b' . $n . '\b/i',
        '/play[s]?\s+as\s+[^\.\n]{0,60}\b' . $n . '\b/i',
        '/\b' . $n . '\b[^\.\n]{0,60}(is\s+playable|playable\s+character|player\s+character)/i',
        '/\b' . $n . '\b[^\.\n]{0,60}(the\s+player\s+character)/i'
      ];
    };

    $matched = false; $matchNote = '';
    foreach ($makePatternsFor($fullName) as $rgx) {
      if (preg_match($rgx, $txtRaw)) { $matched = true; $matchNote = 'full-name proximity'; break; }
    }
    if (!$matched) {
      foreach ($makePatternsFor($firstName) as $rgx) {
        if (preg_match($rgx, $txtRaw)) { $matched = true; $matchNote = 'first-name proximity'; break; }
      }
    }

    if ($matched) { $isPlayable = 1; $debugLogs[] = 'Playable detection for ' . $name . ': YES (' . $matchNote . ')'; }
    else { $debugLogs[] = 'Playable detection for ' . $name . ': NO (no proximity match)'; }
  } else {
    $debugLogs[] = 'Playable detection skipped for ' . $name . ' (no wiki text)';
  }

  $linkStmt->bind_param("iisii", $gameId, $localId, $role, $isPlayable, $srcScore);
  $linkStmt->execute();
  $linked++;
  $idx++;

  // 4) Update images and add per-game media
  if ($img) {
    // Always refresh stored images when we have a better URL
    $updImg->bind_param("ssi", $img, $img, $localId);
    $updImg->execute();
  } else {
    $debugLogs[] = "No image available for $name from Giant Bomb";
  }
  if ($img) {
    $insMedia->bind_param("iis", $localId, $gameId, $img);
    $insMedia->execute();
  }

  usleep(8000);
}

$stamp = $conn->prepare("UPDATE games SET characters_synced_at = NOW() WHERE id = ?");
$stamp->bind_param("i", $gameId);
$stamp->execute();

$conn->commit();


  echo json_encode([
    'message'        => 'Sync complete',
    'game_id'        => $gameId,
    'giantbomb_guid' => $guid,
    'created_count'  => $created,
    'linked_count'   => $linked,
    'debug_logs'     => $debugLogs
  ]);
  exit;

} catch (Throwable $e) {
  try { if (isset($conn)) $conn->rollback(); } catch (Throwable $e2) {}
  http_response_code(500);
  echo json_encode(['error'=>'Unhandled server error', 'detail'=>$e->getMessage()]);
  exit;
}

// ===== WIKIPEDIA API HELPER FUNCTIONS =====

/**
 * Search Wikipedia for character information
 */
function searchWikipediaForCharacter($characterName, $gameTitle, $releaseYear, &$debugLogs) {
  // Try different search patterns with release year
  $searchPatterns = [];
  
  if ($releaseYear) {
    // Most specific: "Prey (2017 video game)"
    $searchPatterns[] = $gameTitle . " ($releaseYear video game)";
    // Alternative: "Prey (2017)"
    $searchPatterns[] = $gameTitle . " ($releaseYear)";
  }
  
  // Fallback patterns
  $searchPatterns[] = $gameTitle . " (video game)";
  $searchPatterns[] = $gameTitle;
  
  foreach ($searchPatterns as $pattern) {
    $debugLogs[] = "Trying Wikipedia pattern: $pattern";
    $gamePage = searchWikipediaPage($pattern, $debugLogs);
    if ($gamePage && !empty($gamePage['extract'])) {
      // Check if the character is mentioned in the game page
      $content = strtolower($gamePage['extract']);
      $full = strtolower($characterName);
      $first = strtolower(explode(' ', $characterName)[0]);

      if (strpos($content, $full) !== false) {
        $debugLogs[] = "Found character '$characterName' in Wikipedia page (full name)";
        return $gamePage;
      }

      // Relaxed first-name match: accept first-name-only presence in the game article
      $debugLogs[] = "Trying first-name heuristic for '" . $first . "' (full name not found)";
      $firstFound = false;
      // Count first-name word-boundary occurrences (case-insensitive)
      $occurrences = preg_match_all('/\\b' . preg_quote($first, '/') . '\\b/i', $gamePage['extract'], $m);
      $debugLogs[] = "First-name occurrences for '" . $first . "': " . (int)$occurrences;
      if ($occurrences && $occurrences >= 1) {
        $firstFound = true;
      }

      if ($firstFound) {
        $debugLogs[] = "First-name heuristic matched (accepted first-name-only for $characterName)";
        return $gamePage;
      }

      $debugLogs[] = "Character '$characterName' not found in Wikipedia content";
    }
  }
  
  $debugLogs[] = "FAILED: No Wikipedia data found for any pattern";
  return null;
}

/**
 * Search for a Wikipedia page
 */
function searchWikipediaPage($query, &$debugLogs) {
  // First try direct page lookup
  $url = "https://en.wikipedia.org/api/rest_v1/page/summary/" . urlencode($query);
  
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ["User-Agent: gametracker/1.0 (+localhost)"],
    CURLOPT_FOLLOWLOCATION => true
  ]);
  
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  
  $debugLogs[] = "Direct Wikipedia lookup for '$query': HTTP $httpCode";
  
  if ($httpCode === 200 && $response) {
    $data = json_decode($response, true);
    if ($data && (!isset($data['type']) || $data['type'] !== 'https://mediawiki.org/wiki/HyperSwitch/errors/not_found')) {
      // Fetch FULL extract for the resolved title to capture later paragraphs
      $resolvedTitle = $data['title'] ?? $query;
      $debugLogs[] = "Direct hit: '$resolvedTitle' — fetching full extract";
      $extractUrl = "https://en.wikipedia.org/w/api.php?action=query&format=json&prop=extracts&explaintext=1&titles=" . urlencode($resolvedTitle);
      $ch = curl_init($extractUrl);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ["User-Agent: gametracker/1.0 (+localhost)"],
        CURLOPT_FOLLOWLOCATION => true
      ]);
      $extractResponse = curl_exec($ch);
      $extractCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      $debugLogs[] = "Direct extract HTTP $extractCode";
      if ($extractCode === 200 && $extractResponse) {
        $extractData = json_decode($extractResponse, true);
        if (isset($extractData['query']['pages'])) {
          $pages = $extractData['query']['pages'];
          $page = reset($pages);
          if (!empty($page['extract'])) {
            return ['title' => $resolvedTitle, 'extract' => $page['extract']];
          }
        }
      }
    }
  }
  
  // If direct page lookup fails, try search API
  $searchUrl = "https://en.wikipedia.org/w/api.php?action=query&format=json&list=search&srsearch=" . urlencode($query) . "&srlimit=3&srnamespace=0";
  
  $ch = curl_init($searchUrl);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ["User-Agent: gametracker/1.0 (+localhost)"],
    CURLOPT_FOLLOWLOCATION => true
  ]);
  
  $searchResponse = curl_exec($ch);
  $searchHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  
  $debugLogs[] = "Wikipedia search API for '$query': HTTP $searchHttpCode";
  
  if ($searchHttpCode === 200 && $searchResponse) {
    $searchData = json_decode($searchResponse, true);
    if ($searchData && isset($searchData['query']['search']) && !empty($searchData['query']['search'])) {
      // Choose best-matching result favoring the video game page and year if present
      $results = $searchData['query']['search'];
      $chosen = null;
      foreach ($results as $r) {
        $t = $r['title'];
        if (stripos($t, '(video game)') !== false) {
          $chosen = $t; break;
        }
      }
      if (!$chosen) { $chosen = $results[0]['title']; }
      $pageTitle = $chosen;
      $debugLogs[] = "Selected search result: $pageTitle";

      // Fetch FULL extract to include later paragraphs, not just the short summary
      $extractUrl = "https://en.wikipedia.org/w/api.php?action=query&format=json&prop=extracts&explaintext=1&titles=" . urlencode($pageTitle);
      $ch = curl_init($extractUrl);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ["User-Agent: gametracker/1.0 (+localhost)"],
        CURLOPT_FOLLOWLOCATION => true
      ]);
      $extractResponse = curl_exec($ch);
      $extractCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      $debugLogs[] = "Fetch full extract for '$pageTitle': HTTP $extractCode";
      if ($extractCode === 200 && $extractResponse) {
        $extractData = json_decode($extractResponse, true);
        if (isset($extractData['query']['pages'])) {
          $pages = $extractData['query']['pages'];
          $page = reset($pages);
          if (!empty($page['extract'])) {
            return ['title' => $pageTitle, 'extract' => $page['extract']];
          }
        }
      }
    }
  }
  
  return null;
}

/**
 * Analyze Wikipedia content to determine character importance
 */
function analyzeWikipediaContent($wikiData, $characterName, &$debugLogs = null) {
  $contentRaw = $wikiData['extract'] ?? '';
  $content = strtolower($contentRaw);
  $first = strtolower(explode(' ', $characterName)[0]);
  $full  = strtolower($characterName);

  $boost = 0; $parts = [];

  // Per-mention boosts: +2 per occurrence (full + first counted)
  $fullCount  = preg_match_all('/\b' . preg_quote($full, '/')  . '\b/i', $content, $m1);
  $firstCount = preg_match_all('/\b' . preg_quote($first, '/') . '\b/i', $content, $m2);
  $mentionBoost = min(40, ($fullCount + $firstCount) * 2);
  $boost += $mentionBoost; $parts[] = "mentions=+$mentionBoost (2*(full=$fullCount + first=$firstCount))";

  // Role indicator bonus (mutually exclusive, proximate to name within ~80 chars)
  $makeNear = function(string $needle, array $keywords) use ($contentRaw) {
    $n = preg_quote($needle, '/');
    foreach ($keywords as $kw) {
      $k = preg_quote($kw, '/');
      if (preg_match('/(' . $n . ').{0,80}(' . $k . ')|(' . $k . ').{0,80}(' . $n . ')/is', $contentRaw)) return true;
    }
    return false;
  };
  $roleBonus = 0; $roleLabel = '';
  if ($makeNear($full, ['protagonist','main character','primary character','central character','lead character','hero','heroine','main protagonist','player character'])) { $roleBonus = 8; $roleLabel = 'main-keyword'; }
  elseif ($makeNear($full, ['antagonist','villain','enemy','boss','final boss','main antagonist','primary antagonist'])) { $roleBonus = 6; $roleLabel = 'antagonist-keyword'; }
  elseif ($makeNear($full, ['supporting character','secondary character','side character','companion','ally','friend','mentor','guide'])) { $roleBonus = 4; $roleLabel = 'supporting-keyword'; }
  // try first-name proximity if full-name not found
  if ($roleBonus === 0) {
    if ($makeNear($first, ['protagonist','main character','primary character','central character','lead character','hero','heroine','main protagonist','player character'])) { $roleBonus = 8; $roleLabel = 'main-keyword(first)'; }
    elseif ($makeNear($first, ['antagonist','villain','enemy','boss','final boss','main antagonist','primary antagonist'])) { $roleBonus = 6; $roleLabel = 'antagonist-keyword(first)'; }
    elseif ($makeNear($first, ['supporting character','secondary character','side character','companion','ally','friend','mentor','guide'])) { $roleBonus = 4; $roleLabel = 'supporting-keyword(first)'; }
  }
  if ($roleBonus > 0) { $boost += $roleBonus; $parts[] = "role=+$roleBonus ($roleLabel)"; }

  // No length bonus

  if (is_array($debugLogs)) $debugLogs[] = "$characterName wikiBoost parts: " . implode(', ', $parts) . " ⇒ total=+$boost";
  return $boost;
}
