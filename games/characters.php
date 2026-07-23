<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/db.php';

// --- Input ---
$gameId = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;
if ($gameId <= 0) { http_response_code(400); echo "Missing game_id"; exit; }

// --- Load game ---
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$g = $conn->prepare("SELECT id, title FROM games WHERE id = ?");
$g->bind_param("i", $gameId);
$g->execute();
$game = $g->get_result()->fetch_assoc();
if (!$game) { http_response_code(404); echo "Game not found"; exit; }

// --- Query characters ordered by your importance_score ---
/*
 We pull per-character media for THIS game via a correlated subquery:
   - prefer kind='headshot'
   - prefer is_primary=1
   - else use biggest (width*height)
   - fallback to created_at newest
 If none found for this game, we fallback to characters.portrait_image_url / image_url in PHP.
*/
$sql = "
SELECT
  c.id            AS character_id,
  c.name          AS character_name,
  c.slug          AS character_slug,
  c.portrait_image_url,
  c.image_url,
  gc.role,
  gc.is_playable,
  gc.is_featured,
  gc.importance_score,

  (
    SELECT cm.url
    FROM character_media cm
    WHERE cm.character_id = c.id
      AND cm.game_id = ?
      AND cm.kind = 'headshot'
    ORDER BY cm.is_primary DESC, (cm.width * cm.height) DESC, cm.created_at DESC
    LIMIT 1
  ) AS headshot_url

FROM game_characters gc
JOIN characters c ON c.id = gc.character_id
WHERE gc.game_id = ?
ORDER BY gc.importance_score DESC, c.name ASC
";

$q = $conn->prepare($sql);
$q->bind_param("ii", $gameId, $gameId);
$q->execute();
$res = $q->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) {
  // fallback portrait if no per-game headshot
  $r['display_img'] = $r['headshot_url'] ?: ($r['portrait_image_url'] ?: $r['image_url']);
  $rows[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($game['title']) ?> — Characters</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Exo+2:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
  <link href="../includes/styles.css" rel="stylesheet">
  <style>
    /* Character page specific styles */
    .character-page {
      background: #15151e;
      min-height: 100vh;
      padding: 2rem 0 4rem;
    }
    
    .page-header {
      background: linear-gradient(135deg, #1e1e2f 0%, #2a2a3f 100%);
      border-radius: 16px;
      padding: 2rem;
      margin-bottom: 2rem;
      border: 1px solid rgba(178, 0, 255, 0.2);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
      position: relative;
      overflow: hidden;
    }
    
    .page-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(45deg, 
        transparent 30%, 
        rgba(178, 0, 255, 0.05) 50%, 
        transparent 70%
      );
      animation: shimmer 3s ease-in-out infinite;
    }
    
    @keyframes shimmer {
      0%, 100% { transform: translateX(-100%); }
      50% { transform: translateX(100%); }
    }
    
    .page-title {
      font-family: 'Orbitron', sans-serif;
      font-size: 2.5rem;
      font-weight: 600;
      color: #ffffff;
      margin: 0;
      text-shadow: 0 0 20px rgba(178, 0, 255, 0.5);
      position: relative;
      z-index: 1;
    }
    
    .game-subtitle {
      font-family: 'Exo 2', sans-serif;
      font-size: 1.1rem;
      color: #a8a8b3;
      margin: 0.5rem 0 0;
      position: relative;
      z-index: 1;
    }
    
    .back-button {
      background: linear-gradient(135deg, #b200ff 0%, #9933ff 100%);
      border: none;
      border-radius: 12px;
      padding: 0.75rem 1.5rem;
      color: white;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(178, 0, 255, 0.3);
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 1rem;
    }
    
    .back-button:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(178, 0, 255, 0.4);
      color: white;
      text-decoration: none;
    }
    
    .character-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1.5rem;
      margin-top: 2rem;
    }
    
    .character-card {
      background: linear-gradient(135deg, #1e1e2f 0%, #2a2a3f 100%);
      border: 1px solid rgba(178, 0, 255, 0.2);
      border-radius: 16px;
      overflow: hidden;
      transition: all 0.4s ease;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
      position: relative;
      animation: fadeInUp 0.6s ease forwards;
      opacity: 0;
      transform: translateY(20px);
    }
    
    .character-card:nth-child(1) { animation-delay: 0.1s; }
    .character-card:nth-child(2) { animation-delay: 0.2s; }
    .character-card:nth-child(3) { animation-delay: 0.3s; }
    .character-card:nth-child(4) { animation-delay: 0.4s; }
    .character-card:nth-child(5) { animation-delay: 0.5s; }
    .character-card:nth-child(6) { animation-delay: 0.6s; }
    .character-card:nth-child(7) { animation-delay: 0.7s; }
    .character-card:nth-child(8) { animation-delay: 0.8s; }
    
    @keyframes fadeInUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .character-card:hover {
      transform: translateY(-8px) scale(1.02);
      box-shadow: 0 15px 40px rgba(178, 0, 255, 0.3);
      border-color: rgba(178, 0, 255, 0.4);
    }
    
    .character-image-container {
      position: relative;
      height: 320px;
      overflow: hidden;
      background: linear-gradient(135deg, #0b0d1d 0%, #1a1a26 100%);
    }
    
    .character-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center top;
      transition: transform 0.5s ease;
    }
    
    .character-card:hover .character-image {
      transform: scale(1.05);
    }
    
    .character-info {
      padding: 1.5rem;
      position: relative;
    }
    
    .character-name {
      font-family: 'Orbitron', sans-serif;
      font-size: 1.3rem;
      font-weight: 600;
      color: #ffffff;
      margin-bottom: 1rem;
      line-height: 1.2;
    }
    
    .character-badges {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }
    
    .character-badge {
      font-size: 0.75rem;
      font-weight: 600;
      padding: 0.4rem 0.8rem;
      border-radius: 20px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .badge-role {
      background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
      color: white;
    }
    
    .badge-playable {
      background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
      color: white;
    }
    
    .badge-featured {
      background: linear-gradient(135deg, #f1c40f 0%, #f39c12 100%);
      color: #2c3e50;
    }
    
    .no-image-placeholder {
      height: 320px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #2a2a3f 0%, #1e1e2f 100%);
      color: #889;
      font-size: 1.1rem;
      font-weight: 500;
    }
    
    .no-image-placeholder i {
      font-size: 3rem;
      margin-bottom: 1rem;
      opacity: 0.5;
    }
    
    .empty-state {
      text-align: center;
      padding: 4rem 2rem;
      background: linear-gradient(135deg, #1e1e2f 0%, #2a2a3f 100%);
      border-radius: 16px;
      border: 1px solid rgba(178, 0, 255, 0.2);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    }
    
    .empty-state h3 {
      font-family: 'Orbitron', sans-serif;
      color: #ffffff;
      margin-bottom: 1rem;
    }
    
    .empty-state p {
      color: #a8a8b3;
      margin-bottom: 2rem;
      font-size: 1.1rem;
    }
    
    .sync-button {
      background: linear-gradient(135deg, #b200ff 0%, #9933ff 100%);
      border: none;
      border-radius: 12px;
      padding: 1rem 2rem;
      color: white;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(178, 0, 255, 0.3);
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .sync-button:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(178, 0, 255, 0.4);
      color: white;
      text-decoration: none;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .page-title {
        font-size: 2rem;
      }
      
      .character-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
      }
      
      .character-image-container {
        height: 280px;
      }
      
      .no-image-placeholder {
        height: 280px;
      }
    }
    
    @media (max-width: 576px) {
      .character-grid {
        grid-template-columns: 1fr;
      }
      
      .page-header {
        padding: 1.5rem;
      }
      
      .page-title {
        font-size: 1.8rem;
      }
    }
  </style>
</head>
<body>
  <div class="character-page">
    <div class="container">
      <a class="back-button" href="/1hnd/gametracker/games/game-detail.php?id=<?= (int)$game['id'] ?>">
        <i class="fas fa-arrow-left"></i>
        Back to Game
      </a>
      
      <div class="page-header">
        <h1 class="page-title">Characters</h1>
        <p class="game-subtitle"><?= htmlspecialchars($game['title']) ?></p>
      </div>

      <?php if (empty($rows)): ?>
        <div class="empty-state">
          <i class="fas fa-users" style="font-size: 4rem; color: #a8a8b3; margin-bottom: 1.5rem;"></i>
          <h3>No Characters Found</h3>
          <p>No characters have been added to the database for this game yet.</p>
          <a class="sync-button" href="/1hnd/gametracker/auth/sync-characters.php?game_id=<?= (int)$game['id'] ?>">
            <i class="fas fa-sync-alt"></i>
            Sync Characters
          </a>
        </div>
      <?php else: ?>
        <div class="character-grid">
          <?php foreach ($rows as $index => $c): ?>
            <div class="character-card" style="--animation-order: <?= $index ?>">
              <?php if ($c['display_img']): ?>
                <div class="character-image-container">
                  <img 
                    src="<?= htmlspecialchars($c['display_img']) ?>" 
                    alt="<?= htmlspecialchars($c['character_name']) ?>"
                    class="character-image"
                    loading="lazy"
                  >
                </div>
              <?php else: ?>
                <div class="no-image-placeholder">
                  <div style="text-align: center;">
                    <i class="fas fa-user"></i>
                    <div>No Image Available</div>
                  </div>
                </div>
              <?php endif; ?>
              
              <div class="character-info">
                <h3 class="character-name" title="Character ID: #<?= (int)$c['character_id'] ?>">
                  <?= htmlspecialchars($c['character_name']) ?>
                </h3>
                
                <div class="character-badges">
                  <?php if ($c['role']): ?>
                    <span class="character-badge badge-role"><?= htmlspecialchars($c['role']) ?></span>
                  <?php endif; ?>
                  
                  <?php if ((int)$c['is_playable'] === 1): ?>
                    <span class="character-badge badge-playable">Playable</span>
                  <?php endif; ?>
                  
                  <?php if ((int)$c['is_featured'] === 1): ?>
                    <span class="character-badge badge-featured">Featured</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  
</body>
</html>
