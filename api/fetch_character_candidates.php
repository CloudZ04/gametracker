<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
while (ob_get_level()) { ob_end_clean(); }

require_once '../includes/db.php';
require_once '../includes/config.php';

try {
  $raw  = file_get_contents('php://input');
  $body = json_decode($raw, true) ?: [];

  $gameId     = isset($body['game_id']) ? (int)$body['game_id'] : 0;
  $guessTitle = isset($body['guess_title']) && $body['guess_title'] !== '' ? $body['guess_title'] : null;

  if ($gameId <= 0) { http_response_code(400); echo json_encode(['error'=>'Missing/invalid game_id']); exit; }

  // --- CONFIG (hard-coded like your RAWG) ---
  $GB_KEY  = GIANTBOMB_API_KEY;
  $GB_BASE = 'https://www.giantbomb.com/api';
  $UA      = 'gametracker/1.0 (+localhost)';
  if (empty($GB_KEY)) { http_response_code(500); echo json_encode(['error'=>'Giant Bomb API key not set']); exit; }

  // --- DB ---
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
  $conn->set_charset('utf8mb4');

  // --- HTTP helper ---
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

  // --- Load game row ---
  $stmt = $conn->prepare("SELECT id, title, giantbomb_guid FROM games WHERE id = ?");
  $stmt->bind_param("i", $gameId);
  $stmt->execute();
  $game = $stmt->get_result()->fetch_assoc();
  if (!$game) { http_response_code(404); echo json_encode(['error'=>'Game not found']); exit; }

  $title = $game['title'];
  $guid  = $game['giantbomb_guid'];
  $searchTitle = $guessTitle ?: $title;

  // --- Resolve GUID if missing ---
  $diag = ['resolved_from'=>'db', 'search_title'=>$searchTitle];
  if (!$guid) {
    $url = $GB_BASE . "/search/?api_key=" . urlencode($GB_KEY)
         . "&format=json&resources=game&query=" . urlencode($searchTitle);
    [$search, $scode] = $gb_get($url);
    if ($scode !== 200 || empty($search['results'][0]['guid'])) {
      http_response_code(502);
      echo json_encode(['error'=>'Could not resolve Giant Bomb GUID','http_code'=>$scode,'query'=>$searchTitle]); exit;
    }
    $guid = $search['results'][0]['guid'];
    $diag['resolved_from'] = 'search';

    $upd = $conn->prepare("UPDATE games SET giantbomb_guid = ? WHERE id = ?");
    $upd->bind_param("si", $guid, $gameId);
    $upd->execute();
  }

  // --- Fetch characters for the game (no filtering) ---
  $detailUrl = $GB_BASE . "/game/" . rawurlencode($guid) . "/?api_key="
             . urlencode($GB_KEY) . "&format=json&field_list=characters";
  [$detail, $dcode] = $gb_get($detailUrl);
  if ($dcode !== 200 || !isset($detail['results'])) {
    http_response_code(502);
    echo json_encode(['error'=>'Failed to fetch game detail','http_code'=>$dcode]); exit;
  }

  $chars = $detail['results']['characters'] ?? [];
  $diag['gb_characters_total'] = is_array($chars) ? count($chars) : 0;

  if (empty($chars)) {
    echo json_encode(['items'=>[], 'giantbomb_guid'=>$guid, 'diagnostics'=>$diag]); exit;
  }

  // --- Build candidates and enrich images via /character/{GUID} if needed ---
  $items = [];
  foreach ($chars as $c) {
    $name = $c['name'] ?? null;
    if (!$name) continue;

    $gbId  = isset($c['id']) ? (int)$c['id'] : null;
    $guidChar = $c['guid'] ?? null;
    if (!$guidChar && !empty($c['site_detail_url'])) {
      $path = parse_url($c['site_detail_url'], PHP_URL_PATH);
      if ($path) $guidChar = ltrim(basename($path), '/'); // e.g., "3005-658"
    }

    // Try stub image first
    $bestImg = null;
    if (!empty($c['image'])) {
      $img = $c['image'];
      $bestImg = $img['medium_url'] ?? $img['small_url'] ?? $img['icon_url'] ?? null;
    }

    // If no image on stub, hit character detail for image
    if (!$bestImg && $guidChar) {
      $charUrl = $GB_BASE . "/character/" . rawurlencode($guidChar)
               . "/?api_key=" . urlencode($GB_KEY)
               . "&format=json&field_list=image";
      [$cdetail, $ccode] = $gb_get($charUrl);
      if ($ccode === 200 && !empty($cdetail['results']['image'])) {
        $imgObj  = $cdetail['results']['image'];
        $bestImg = $imgObj['medium_url'] ?? $imgObj['small_url'] ?? $imgObj['icon_url'] ?? null;
      }
      usleep(7000);
    }

    $items[] = [
      'name'       => $name,
      'gb_id'      => $gbId,
      'guid'       => $guidChar,
      'image_url'  => $bestImg
    ];

    usleep(4000);
  }

  echo json_encode([
    'items'          => $items,
    'giantbomb_guid' => $guid,
    'count'          => count($items),
    'diagnostics'    => $diag
  ]);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>'Server error', 'detail'=>$e->getMessage()]);
  exit;
}
