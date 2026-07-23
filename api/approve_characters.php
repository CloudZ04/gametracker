<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
while (ob_get_level()) { ob_end_clean(); }

require_once '../includes/db.php';

try {
  $raw = file_get_contents('php://input');
  $body = json_decode($raw, true) ?: [];

  $gameId = isset($body['game_id']) ? (int)$body['game_id'] : 0;
  $list   = isset($body['characters']) && is_array($body['characters']) ? $body['characters'] : [];

  if ($gameId <= 0) { http_response_code(400); echo json_encode(['error'=>'Missing/invalid game_id']); exit; }

  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
  $conn->set_charset('utf8mb4');

  // simple debug log for visibility in UI
  $debugLogs = [];
  $debugLogs[] = 'approve_characters: this endpoint links approved characters and does not run Wikipedia/scoring.';

  // prepared statements
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
  $selCharId = $conn->prepare("SELECT id FROM characters WHERE giantbomb_id = ?");
  $linkStmt  = $conn->prepare("
    INSERT INTO game_characters (game_id, character_id, role, is_playable, source_score)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      role = COALESCE(VALUES(role), role),
      is_playable = VALUES(is_playable),
      source_score = GREATEST(source_score, VALUES(source_score))
  ");
  $insMedia  = $conn->prepare("
    INSERT INTO character_media (character_id, game_id, kind, url, width, height, variant, credit, is_primary)
    VALUES (?, ?, 'headshot', ?, NULL, NULL, NULL, 'Giant Bomb', 1)
    ON DUPLICATE KEY UPDATE url = VALUES(url)
  ");

  $conn->begin_transaction();

  // fetch game context for Wikipedia enrichment
  $gq = $conn->prepare("SELECT title, release_date FROM games WHERE id = ?");
  $gq->bind_param("i", $gameId);
  $gq->execute();
  $ginfo = $gq->get_result()->fetch_assoc();
  $gameTitle = $ginfo['title'] ?? '';
  $releaseYear = !empty($ginfo['release_date']) ? date('Y', strtotime($ginfo['release_date'])) : null;

  // upsert+link ONLY the approved ones
  $approvedLocalIds = [];
  $created = 0; $linked = 0; $mediaAdded = 0;
  $idx = 0; // list order influences base score

  foreach ($list as $c) {
    $name = isset($c['name']) ? (string)$c['name'] : null;
    $gbId = isset($c['gb_id']) ? (int)$c['gb_id'] : null;
    $guid = isset($c['guid']) ? (string)$c['guid'] : null;
    $img  = isset($c['image_url']) ? (string)$c['image_url'] : null;
    if (!$name || !$gbId) continue;

    $slug = $guid ?: null;   // store guid-like string in slug (handy); optional
    $bio  = null; $portrait = $img;

    $insChar->bind_param("ssisss", $name, $slug, $gbId, $img, $portrait, $bio);
    $insChar->execute();
    if ($conn->affected_rows > 0) $created++;

    $selCharId->bind_param("i", $gbId);
    $selCharId->execute();
    $row = $selCharId->get_result()->fetch_assoc();
    if (!$row) continue;

    $localId = (int)$row['id'];
    $approvedLocalIds[] = $localId;

    // Wikipedia enrichment
    $wikiLogs = [];
    $wikiInfo = approve_searchWikipediaForCharacter($name, $gameTitle, $releaseYear, $wikiLogs);
    foreach ($wikiLogs as $L) { $debugLogs[] = $L; }

    // scoring
    $wikiBoost = 0;
    if ($wikiInfo) { $wikiBoost = approve_analyzeWikipediaContent($wikiInfo, $name, $debugLogs); $debugLogs[] = "Wikipedia boost for $name: +$wikiBoost points"; }
    else { $debugLogs[] = "No Wikipedia data found for $name"; }

    // Rank using ONLY Wikipedia data (no Giant Bomb order influence)
    $baseScore = 0;
    $totalScore = $wikiBoost;
    if     ($totalScore >= 16) { $role = 'main';       $srcScore = 25; }
    elseif ($totalScore >= 10) { $role = 'supporting'; $srcScore = 12; }
    elseif ($totalScore >= 4)  { $role = 'minor';      $srcScore = 6;  }
    else                       { $role = 'cameo';      $srcScore = 2;  }
    if ($role === 'main' && $wikiBoost < 8) { $debugLogs[] = "Downgraded $name from main to supporting due to low/no Wikipedia evidence"; $role = 'supporting'; $srcScore = 12; }

    // playable detection (proximity based)
    $isPlayable = 0;
    if (!empty($wikiInfo['extract'])) {
      $txtRaw = $wikiInfo['extract'];
      $fullName = strtolower($name);
      $firstName = strtolower(explode(' ', $name)[0]);
      $mk = function(string $needle){ $n = preg_quote($needle, '/'); return [
        '/(player|players)\s+(control|controls|controlled)\s+[^\.\n]{0,60}\b' . $n . '\b/i',
        '/play[s]?\s+as\s+[^\.\n]{0,60}\b' . $n . '\b/i',
        '/\b' . $n . '\b[^\.\n]{0,60}(is\s+playable|playable\s+character|player\s+character)/i',
        '/\b' . $n . '\b[^\.\n]{0,60}(the\s+player\s+character)/i'
      ]; };
      $matched = false; $note = '';
      foreach ($mk($fullName) as $rgx) { if (preg_match($rgx, $txtRaw)) { $matched = true; $note = 'full-name proximity'; break; } }
      if (!$matched) { foreach ($mk($firstName) as $rgx) { if (preg_match($rgx, $txtRaw)) { $matched = true; $note = 'first-name proximity'; break; } } }
      if ($matched) { $isPlayable = 1; $debugLogs[] = "Playable detection for $name: YES ($note)"; }
      else { $debugLogs[] = "Playable detection for $name: NO (no proximity match)"; }
    } else {
      $debugLogs[] = "Playable detection skipped for $name (no wiki text)";
    }

    $linkStmt->bind_param("iisii", $gameId, $localId, $role, $isPlayable, $srcScore);
    $linkStmt->execute();
    $linked++;
    $idx++;

    if ($img) {
      $insMedia->bind_param("iis", $localId, $gameId, $img);
      $insMedia->execute();
      $mediaAdded++;
    }
  }

  // now REMOVE any previously linked characters for this game
  // that are NOT in the approved set
  $removedLinks = 0; $removedMedia = 0;

  // 1) delete media for removed links
  if (count($approvedLocalIds) > 0) {
    $in = implode(',', array_fill(0, count($approvedLocalIds), '?'));
    $types = str_repeat('i', count($approvedLocalIds) + 1); // + game_id
    $sqlMed = "DELETE FROM character_media
               WHERE game_id = ? AND character_id NOT IN ($in)";
    $stmtMed = $conn->prepare($sqlMed);

    // bind params dynamically
    $params = array_merge([$types, $gameId], $approvedLocalIds);
    $tmp = [];
    foreach ($params as $k => $v) { $tmp[$k] = &$params[$k]; }
    call_user_func_array([$stmtMed, 'bind_param'], $tmp);

    $stmtMed->execute();
    $removedMedia = $stmtMed->affected_rows;
    $stmtMed->close();
  } else {
    // none approved -> remove all media for this game
    $stmtMedAll = $conn->prepare("DELETE FROM character_media WHERE game_id = ?");
    $stmtMedAll->bind_param("i", $gameId);
    $stmtMedAll->execute();
    $removedMedia = $stmtMedAll->affected_rows;
    $stmtMedAll->close();
  }

  // 2) delete links not approved
  if (count($approvedLocalIds) > 0) {
    $in = implode(',', array_fill(0, count($approvedLocalIds), '?'));
    $types = str_repeat('i', count($approvedLocalIds) + 1);
    $sql = "DELETE FROM game_characters
            WHERE game_id = ? AND character_id NOT IN ($in)";
    $stmtDel = $conn->prepare($sql);

    $params = array_merge([$types, $gameId], $approvedLocalIds);
    $tmp = [];
    foreach ($params as $k => $v) { $tmp[$k] = &$params[$k]; }
    call_user_func_array([$stmtDel, 'bind_param'], $tmp);

    $stmtDel->execute();
    $removedLinks = $stmtDel->affected_rows;
    $stmtDel->close();
  } else {
    // none approved -> clear all links for this game
    $stmtDelAll = $conn->prepare("DELETE FROM game_characters WHERE game_id = ?");
    $stmtDelAll->bind_param("i", $gameId);
    $stmtDelAll->execute();
    $removedLinks = $stmtDelAll->affected_rows;
    $stmtDelAll->close();
  }

  // stamp the game
  $stamp = $conn->prepare("UPDATE games SET characters_synced_at = NOW() WHERE id = ?");
  $stamp->bind_param("i", $gameId);
  $stamp->execute();

  $conn->commit();

  echo json_encode([
    'message'        => 'Approved characters saved (replace mode)',
    'game_id'        => $gameId,
    'approved_count' => count($approvedLocalIds),
    'created_count'  => $created,
    'linked_count'   => $linked,
    'removed_links'  => $removedLinks,
    'removed_media'  => $removedMedia,
    'debug_logs'     => $debugLogs
  ]);
  exit;

} catch (Throwable $e) {
  try { if (isset($conn)) $conn->rollback(); } catch (Throwable $e2) {}
  http_response_code(500);
  echo json_encode(['error'=>'Server error', 'detail'=>$e->getMessage()]);
  exit;
}

// --- Wikipedia helpers (approve_* to avoid name clash with sync) ---
function approve_searchWikipediaForCharacter($characterName, $gameTitle, $releaseYear, &$debugLogs) {
  $patterns = [];
  if ($releaseYear) {
    $patterns[] = $gameTitle . " ($releaseYear video game)";
    $patterns[] = $gameTitle . " ($releaseYear)";
  }
  $patterns[] = $gameTitle . " (video game)";
  $patterns[] = $gameTitle;
  foreach ($patterns as $p) {
    $debugLogs[] = "Trying Wikipedia pattern: $p";
    $page = approve_searchWikipediaPage($p, $debugLogs);
    if ($page && !empty($page['extract'])) {
      $content = strtolower($page['extract']);
      $full = strtolower($characterName);
      $first = strtolower(explode(' ', $characterName)[0]);
      if (strpos($content, $full) !== false) { $debugLogs[] = "Found character '$characterName' in Wikipedia page (full name)"; return $page; }
      $occ = preg_match_all('/\\b' . preg_quote($first, '/') . '\\b/i', $page['extract'], $m);
      $debugLogs[] = "First-name occurrences for '" . $first . "': " . (int)$occ;
      if ($occ && $occ >= 1) { $debugLogs[] = "First-name heuristic matched (accepted first-name-only for $characterName)"; return $page; }
      $debugLogs[] = "Character '$characterName' not found in Wikipedia content";
    }
  }
  $debugLogs[] = "FAILED: No Wikipedia data found for any pattern";
  return null;
}

function approve_searchWikipediaPage($query, &$debugLogs) {
  $url = "https://en.wikipedia.org/api/rest_v1/page/summary/" . urlencode($query);
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_HTTPHEADER=>["User-Agent: gametracker/1.0 (+localhost)"], CURLOPT_FOLLOWLOCATION=>true]);
  $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  $debugLogs[] = "Direct Wikipedia lookup for '$query': HTTP $code";
  if ($code === 200 && $res) {
    $data = json_decode($res, true);
    if ($data && (!isset($data['type']) || $data['type'] !== 'https://mediawiki.org/wiki/HyperSwitch/errors/not_found')) {
      $title = $data['title'] ?? $query;
      $debugLogs[] = "Direct hit: '$title' — fetching full extract";
      $exUrl = "https://en.wikipedia.org/w/api.php?action=query&format=json&prop=extracts&explaintext=1&titles=" . urlencode($title);
      $ch = curl_init($exUrl);
      curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_HTTPHEADER=>["User-Agent: gametracker/1.0 (+localhost)"], CURLOPT_FOLLOWLOCATION=>true]);
      $exRes = curl_exec($ch); $exCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
      $debugLogs[] = "Direct extract HTTP $exCode";
      if ($exCode === 200 && $exRes) {
        $jd = json_decode($exRes, true);
        if (isset($jd['query']['pages'])) { $pages = $jd['query']['pages']; $page = reset($pages); if (!empty($page['extract'])) return ['title'=>$title,'extract'=>$page['extract']]; }
      }
    }
  }
  $searchUrl = "https://en.wikipedia.org/w/api.php?action=query&format=json&list=search&srsearch=" . urlencode($query) . "&srlimit=3&srnamespace=0";
  $ch = curl_init($searchUrl);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_HTTPHEADER=>["User-Agent: gametracker/1.0 (+localhost)"], CURLOPT_FOLLOWLOCATION=>true]);
  $sRes = curl_exec($ch); $sCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  $debugLogs[] = "Wikipedia search API for '$query': HTTP $sCode";
  if ($sCode === 200 && $sRes) {
    $sd = json_decode($sRes, true);
    if ($sd && isset($sd['query']['search']) && !empty($sd['query']['search'])) {
      $title = $sd['query']['search'][0]['title'];
      $debugLogs[] = "Selected search result: $title";
      $exUrl = "https://en.wikipedia.org/w/api.php?action=query&format=json&prop=extracts&explaintext=1&titles=" . urlencode($title);
      $ch = curl_init($exUrl);
      curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_HTTPHEADER=>["User-Agent: gametracker/1.0 (+localhost)"], CURLOPT_FOLLOWLOCATION=>true]);
      $exRes = curl_exec($ch); $exCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
      $debugLogs[] = "Fetch full extract for '$title': HTTP $exCode";
      if ($exCode === 200 && $exRes) { $jd = json_decode($exRes, true); if (isset($jd['query']['pages'])) { $pages = $jd['query']['pages']; $page = reset($pages); if (!empty($page['extract'])) return ['title'=>$title,'extract'=>$page['extract']]; } }
    }
  }
  return null;
}

function approve_analyzeWikipediaContent($wikiData, $characterName, &$debugLogs = null) {
  $contentRaw = $wikiData['extract'] ?? '';
  $content = strtolower($contentRaw);
  $first = strtolower(explode(' ', $characterName)[0]);
  $full  = strtolower($characterName);

  $boost = 0; $parts = [];

  // Mentions: +2 per occurrence (full + first counted)
  $fullCount  = preg_match_all('/\\b' . preg_quote($full, '/')  . '\\b/i', $content, $m1);
  $firstCount = preg_match_all('/\\b' . preg_quote($first, '/') . '\\b/i', $content, $m2);
  $mentionBoost = min(40, ($fullCount + $firstCount) * 2);
  $boost += $mentionBoost; $parts[] = "mentions=+$mentionBoost (2*(full=$fullCount + first=$firstCount))";

  // Role: single best bonus near name (within ~80 chars)
  $near = function(string $needle, array $kws) use ($contentRaw) {
    $n = preg_quote($needle, '/');
    foreach ($kws as $kw) {
      $k = preg_quote($kw, '/');
      if (preg_match('/(' . $n . ').{0,80}(' . $k . ')|(' . $k . ').{0,80}(' . $n . ')/is', $contentRaw)) return true;
    }
    return false;
  };
  $roleBonus = 0; $roleLabel = '';
  if ($near($full, ['protagonist','main character','primary character','central character','lead character','hero','heroine','main protagonist','player character'])) { $roleBonus=8; $roleLabel='main-keyword'; }
  elseif ($near($full, ['antagonist','villain','enemy','boss','final boss','main antagonist','primary antagonist'])) { $roleBonus=6; $roleLabel='antagonist-keyword'; }
  elseif ($near($full, ['supporting character','secondary character','side character','companion','ally','friend','mentor','guide'])) { $roleBonus=4; $roleLabel='supporting-keyword'; }
  if ($roleBonus===0) {
    if ($near($first, ['protagonist','main character','primary character','central character','lead character','hero','heroine','main protagonist','player character'])) { $roleBonus=8; $roleLabel='main-keyword(first)'; }
    elseif ($near($first, ['antagonist','villain','enemy','boss','final boss','main antagonist','primary antagonist'])) { $roleBonus=6; $roleLabel='antagonist-keyword(first)'; }
    elseif ($near($first, ['supporting character','secondary character','side character','companion','ally','friend','mentor','guide'])) { $roleBonus=4; $roleLabel='supporting-keyword(first)'; }
  }
  if ($roleBonus>0) { $boost+=$roleBonus; $parts[] = "role=+$roleBonus ($roleLabel)"; }

  // No length bonus

  if (is_array($debugLogs)) $debugLogs[] = $characterName . ' wikiBoost parts: ' . implode(', ', $parts) . ' ⇒ total=+' . $boost;
  return $boost;
}
