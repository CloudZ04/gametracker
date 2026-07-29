<?php
require_once '../includes/db.php';
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../explore.php');
    exit();
}

$gameId = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;
$game = null;
$characters = [];

if ($gameId > 0) {
    $g = $conn->prepare("SELECT id, title, image_url FROM games WHERE id = ?");
    $g->bind_param("i", $gameId);
    $g->execute();
    $game = $g->get_result()->fetch_assoc();

    if ($game) {
        $q = $conn->prepare("
            SELECT c.id, c.name, c.image_url, c.portrait_image_url, c.bio,
                   gc.role, gc.is_playable, gc.is_featured, gc.importance_score
            FROM game_characters gc
            JOIN characters c ON c.id = gc.character_id
            WHERE gc.game_id = ?
            ORDER BY gc.importance_score DESC, c.name ASC
        ");
        $q->bind_param("i", $gameId);
        $q->execute();
        $characters = $q->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Characters | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/styles.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            color: #fff;
            font-family: 'Exo 2', sans-serif;
            min-height: 100vh;
        }
        .page-wrap { max-width: 1100px; margin: 0 auto; padding: 2rem 1rem 4rem; }
        .panel {
            background: rgba(30, 30, 47, 0.95);
            border: 1px solid rgba(178, 0, 255, 0.2);
            border-radius: 14px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 24px rgba(0,0,0,0.25);
        }
        .panel h2, .panel h3 {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.15rem;
            margin-bottom: 1rem;
        }
        .form-control, .form-select {
            background: #1a1a2e;
            border: 1px solid rgba(178, 0, 255, 0.25);
            color: #fff;
        }
        .form-control:focus, .form-select:focus {
            background: #1a1a2e;
            border-color: #b200ff;
            color: #fff;
            box-shadow: 0 0 0 0.2rem rgba(178, 0, 255, 0.2);
        }
        .form-control::placeholder { color: #888; }
        .form-label { color: #cfcfe0; font-size: 0.9rem; }
        .btn-primary {
            background: #b200ff;
            border-color: #b200ff;
        }
        .btn-primary:hover {
            background: #9933ff;
            border-color: #9933ff;
        }
        .search-results {
            max-height: 260px;
            overflow-y: auto;
            border: 1px solid rgba(178, 0, 255, 0.2);
            border-radius: 10px;
            margin-top: 0.5rem;
            display: none;
        }
        .search-result-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.65rem 0.85rem;
            cursor: pointer;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .search-result-item:hover { background: rgba(178, 0, 255, 0.12); }
        .search-result-item img {
            width: 40px; height: 40px; object-fit: cover; border-radius: 6px;
        }
        .char-row {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            padding: 0.85rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .char-row:last-child { border-bottom: none; }
        .char-thumb {
            width: 64px; height: 64px; object-fit: cover; border-radius: 8px;
            background: #12122a; flex-shrink: 0;
        }
        .char-meta { flex: 1; min-width: 0; }
        .char-name { font-weight: 600; margin-bottom: 0.2rem; }
        .char-badges { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 0.35rem; }
        .badge-role {
            background: rgba(178, 0, 255, 0.2);
            border: 1px solid rgba(178, 0, 255, 0.35);
            color: #e2a9ff;
            font-weight: 500;
        }
        .selected-game {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            background: rgba(178, 0, 255, 0.08);
            border-radius: 10px;
            border: 1px solid rgba(178, 0, 255, 0.2);
        }
        .selected-game img {
            width: 56px; height: 56px; object-fit: cover; border-radius: 8px;
        }
        .form-check-input:checked {
            background-color: #b200ff;
            border-color: #b200ff;
        }
    </style>
</head>
<body>
<?php include '../includes/nav.php'; ?>

<div class="page-wrap">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 style="font-family:'Orbitron',sans-serif;font-size:1.5rem;margin:0;">
            <i class="bi bi-people me-2"></i>Manage Characters
        </h1>
        <a href="admin.php" class="btn btn-outline-light btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Admin
        </a>
    </div>

    <div class="panel">
        <h2>1. Select a Game</h2>
        <label class="form-label" for="gameSearch">Search by title</label>
        <input type="text" id="gameSearch" class="form-control" placeholder="Start typing a game title...">
        <div id="searchResults" class="search-results"></div>

        <?php if ($game): ?>
            <div class="selected-game mt-3">
                <?php if (!empty($game['image_url'])): ?>
                    <img src="<?= htmlspecialchars($game['image_url']) ?>" alt="">
                <?php endif; ?>
                <div>
                    <div class="fw-semibold"><?= htmlspecialchars($game['title']) ?></div>
                    <div class="text-muted small">Game ID: <?= (int)$game['id'] ?></div>
                </div>
                <a class="btn btn-outline-light btn-sm ms-auto" href="<?= BASE_URL ?>games/characters.php?game_id=<?= (int)$game['id'] ?>" target="_blank">
                    View public page
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($game): ?>
    <div class="panel">
        <h2>2. Add Character</h2>
        <form id="addCharacterForm">
            <input type="hidden" name="game_id" value="<?= (int)$game['id'] ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Master Chief">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="main">Main</option>
                        <option value="antagonist">Antagonist</option>
                        <option value="supporting" selected>Supporting</option>
                        <option value="minor">Minor</option>
                        <option value="cameo">Cameo</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Image URL</label>
                    <input type="url" name="image_url" class="form-control" placeholder="https://...">
                </div>
                <div class="col-12">
                    <label class="form-label">Bio</label>
                    <textarea name="bio" class="form-control" rows="3" placeholder="Short character description..."></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Manual weight (optional)</label>
                    <input type="number" step="0.1" name="manual_weight" class="form-control" placeholder="Boosts sort order">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_playable" id="isPlayable" value="1">
                        <label class="form-check-label" for="isPlayable">Playable</label>
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_featured" id="isFeatured" value="1">
                        <label class="form-check-label" for="isFeatured">Featured</label>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">
                <i class="bi bi-plus-circle me-1"></i>Add Character
            </button>
        </form>
    </div>

    <div class="panel">
        <h3>Characters for this game (<?= count($characters) ?>)</h3>
        <?php if (empty($characters)): ?>
            <p class="text-muted mb-0">No characters linked yet. Add one above.</p>
        <?php else: ?>
            <?php foreach ($characters as $c): ?>
                <?php $thumb = $c['portrait_image_url'] ?: $c['image_url']; ?>
                <div class="char-row" data-character-id="<?= (int)$c['id'] ?>">
                    <?php if ($thumb): ?>
                        <img class="char-thumb" src="<?= htmlspecialchars($thumb) ?>" alt="">
                    <?php else: ?>
                        <div class="char-thumb d-flex align-items-center justify-content-center text-muted">
                            <i class="bi bi-person"></i>
                        </div>
                    <?php endif; ?>
                    <div class="char-meta">
                        <div class="char-name"><?= htmlspecialchars($c['name']) ?></div>
                        <div class="char-badges">
                            <?php if ($c['role']): ?>
                                <span class="badge badge-role"><?= htmlspecialchars($c['role']) ?></span>
                            <?php endif; ?>
                            <?php if ($c['is_playable']): ?>
                                <span class="badge bg-success">Playable</span>
                            <?php endif; ?>
                            <?php if ($c['is_featured']): ?>
                                <span class="badge bg-warning text-dark">Featured</span>
                            <?php endif; ?>
                            <span class="badge bg-secondary">Score: <?= htmlspecialchars((string)$c['importance_score']) ?></span>
                        </div>
                        <?php if (!empty($c['bio'])): ?>
                            <div class="small text-muted"><?= htmlspecialchars(mb_strimwidth($c['bio'], 0, 140, '…')) ?></div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm remove-char-btn"
                            data-character-id="<?= (int)$c['id'] ?>">
                        Remove
                    </button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;
const gameId = <?= (int)$gameId ?>;

const searchInput = document.getElementById('gameSearch');
const resultsBox = document.getElementById('searchResults');
let searchTimer = null;

searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    const q = searchInput.value.trim();
    if (q.length < 2) {
        resultsBox.style.display = 'none';
        resultsBox.innerHTML = '';
        return;
    }
    searchTimer = setTimeout(() => searchGames(q), 250);
});

async function searchGames(q) {
    try {
        const res = await fetch(BASE_URL + 'api/search-local-games.php?q=' + encodeURIComponent(q));
        const data = await res.json();
        const games = data.games || [];
        if (!games.length) {
            resultsBox.innerHTML = '<div class="p-3 text-muted">No games found</div>';
            resultsBox.style.display = 'block';
            return;
        }
        resultsBox.innerHTML = games.slice(0, 12).map(g => {
            const id = g.id || g.game_id;
            const title = g.title || g.name || 'Untitled';
            const img = g.image_url || g.portrait_url || '';
            return `<div class="search-result-item" data-id="${id}">
                ${img ? `<img src="${img}" alt="">` : '<div style="width:40px;height:40px;background:#222;border-radius:6px;"></div>'}
                <div>
                    <div>${escapeHtml(title)}</div>
                    <div class="small text-muted">ID: ${id}</div>
                </div>
            </div>`;
        }).join('');
        resultsBox.style.display = 'block';
        resultsBox.querySelectorAll('.search-result-item').forEach(el => {
            el.addEventListener('click', () => {
                window.location.href = BASE_URL + 'auth/manage-characters.php?game_id=' + el.dataset.id;
            });
        });
    } catch (e) {
        resultsBox.innerHTML = '<div class="p-3 text-danger">Search failed</div>';
        resultsBox.style.display = 'block';
    }
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, m => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[m]));
}

const form = document.getElementById('addCharacterForm');
if (form) {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const payload = {
            game_id: Number(fd.get('game_id')),
            name: fd.get('name'),
            image_url: fd.get('image_url') || '',
            bio: fd.get('bio') || '',
            role: fd.get('role'),
            is_playable: fd.get('is_playable') ? 1 : 0,
            is_featured: fd.get('is_featured') ? 1 : 0,
            manual_weight: fd.get('manual_weight') || ''
        };

        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        try {
            const res = await fetch(BASE_URL + 'api/add-character.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.success) {
                showToast(data.error || 'Failed to add character', 'error');
                return;
            }
            showToast(data.message || 'Character added', 'success');
            setTimeout(() => window.location.reload(), 600);
        } catch (err) {
            showToast('Request failed', 'error');
        } finally {
            btn.disabled = false;
        }
    });
}

document.querySelectorAll('.remove-char-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const characterId = Number(btn.dataset.characterId);
        showConfirm('Remove this character from the game?', async () => {
            try {
                const res = await fetch(BASE_URL + 'api/remove-character.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ game_id: gameId, character_id: characterId })
                });
                const data = await res.json();
                if (!data.success) {
                    showToast(data.error || 'Failed to remove', 'error');
                    return;
                }
                showToast(data.message || 'Removed', 'success');
                btn.closest('.char-row')?.remove();
            } catch (e) {
                showToast('Request failed', 'error');
            }
        });
    });
});
</script>
</body>
</html>
