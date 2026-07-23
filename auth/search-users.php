<?php
require_once '../includes/db.php';
session_start();

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Load suggested users (active users, excluding self and already-friends)
$suggested = [];
$suggestStmt = $conn->prepare("
    SELECT u.id, u.username, u.name, u.about, u.profile_image,
        (SELECT COUNT(*) FROM user_relationships WHERE following_id = u.id AND status = 'following') AS followers,
        (SELECT COUNT(*) FROM user_relationships WHERE follower_id = u.id AND status = 'following') AS following,
        (SELECT COUNT(DISTINCT CASE WHEN follower_id = u.id THEN following_id ELSE follower_id END)
            FROM user_relationships
            WHERE (follower_id = u.id OR following_id = u.id) AND status = 'friends') AS friends
    FROM users u
    WHERE u.id != ?
    ORDER BY followers DESC, RAND()
    LIMIT 12
");
$suggestStmt->bind_param("i", $userId);
$suggestStmt->execute();
$result = $suggestStmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['initials'] = strtoupper(substr($row['username'], 0, 1) . substr($row['username'], 1, 1));
    if ($row['profile_image']) {
        $row['profile_image'] = '/1hnd/gametracker/auth/uploads/profiles/' . basename($row['profile_image']);
    }
    $suggested[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Friends | GameTracker.gg</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/styles.css">
    <style>
        :root {
            --primary-color: #b200ff;
            --card-bg: #1e1e2f;
        }

        /* ── Hero ──────────────────────────────────── */
        .hero-section {
            padding: 3.5rem 0 2.5rem;
            background: linear-gradient(135deg, rgba(21,21,30,0.97) 0%, rgba(26,26,38,0.95) 100%);
            border-bottom: 1px solid rgba(178,0,255,0.18);
            margin-bottom: 2.5rem;
            position: relative;
        }
        .hero-section h1 { font-size: clamp(1.8rem, 4vw, 2.6rem); }
        .hero-section .lead { color: #a8a8b3; font-size: 1rem; }

        /* ── Search bar ─────────────────────────────── */
        .search-wrap {
            max-width: 640px;
            margin: 0 auto;
            position: relative;
        }
        .search-wrap .bi-search {
            position: absolute;
            left: 1.1rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(178, 0, 255, 0.6);
            font-size: 1.1rem;
            pointer-events: none;
        }
        .search-input {
            background: rgba(30, 30, 47, 0.7);
            border: 1px solid rgba(127, 0, 255, 0.3);
            color: white;
            padding: 0.9rem 1rem 0.9rem 2.9rem;
            font-size: 1rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            width: 100%;
        }
        .search-input::placeholder { color: #888; }
        .search-input:focus {
            background: rgba(30, 30, 47, 0.95);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(178, 0, 255, 0.15);
            color: white;
            outline: none;
        }

        /* ── User cards (grid) ──────────────────────── */
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.25rem;
        }
        .user-card {
            background: var(--card-bg);
            border: 1px solid rgba(127, 0, 255, 0.12);
            border-radius: 14px;
            padding: 1.5rem;
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            position: relative;
            overflow: hidden;
        }
        .user-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #b200ff, #6600cc);
            opacity: 0;
            transition: opacity 0.25s ease;
        }
        .user-card:hover {
            transform: translateY(-4px);
            border-color: rgba(178, 0, 255, 0.4);
            box-shadow: 0 10px 28px rgba(127, 0, 255, 0.2);
        }
        .user-card:hover::before { opacity: 1; }

        /* avatar */
        .user-avatar, .user-avatar-initials {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            border: 2px solid rgba(127, 0, 255, 0.35);
            flex-shrink: 0;
            transition: border-color 0.25s;
        }
        .user-card:hover .user-avatar,
        .user-card:hover .user-avatar-initials { border-color: #b200ff; }
        .user-avatar { object-fit: cover; }
        .user-avatar-initials {
            background: linear-gradient(135deg, #b200ff, #7700bb);
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* card body */
        .user-card-link {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }
        .user-card-link:hover { color: inherit; }
        .user-name {
            font-family: 'Orbitron', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            color: white;
            margin: 0 0 0.2rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .user-handle {
            font-size: 0.8rem;
            color: #8888a8;
            margin: 0;
        }
        .user-about {
            font-size: 0.83rem;
            color: #999;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin: 0;
            min-height: 2.5em;
        }
        .user-stats {
            display: flex;
            gap: 1rem;
            font-size: 0.78rem;
            color: #7777a0;
            padding-top: 0.25rem;
            border-top: 1px solid rgba(127,0,255,0.1);
        }
        .user-stats strong { color: #ccc; margin-right: 2px; }

        /* action buttons */
        .user-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn-sm-action {
            padding: 0.38rem 0.9rem;
            font-size: 0.82rem;
            border-radius: 7px;
            font-family: 'Exo 2', sans-serif;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: all 0.2s ease;
            border: 1.5px solid transparent;
            cursor: pointer;
        }
        .btn-follow-action {
            background: transparent;
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        .btn-follow-action:hover, .btn-follow-action.active {
            background: var(--primary-color);
            color: white;
        }
        .btn-friend-action {
            background: transparent;
            border-color: #4CAF50;
            color: #4CAF50;
        }
        .btn-friend-action:hover, .btn-friend-action.active {
            background: #4CAF50;
            color: white;
        }
        .btn-pending-action {
            background: rgba(108,117,125,0.2);
            border-color: #6c757d;
            color: #9b9bb3;
            cursor: not-allowed;
        }
        .btn-accept-action {
            background: transparent;
            border-color: #4CAF50;
            color: #4CAF50;
        }
        .btn-accept-action:hover { background: #4CAF50; color: white; }
        .btn-decline-action {
            background: transparent;
            border-color: #dc3545;
            color: #dc3545;
        }
        .btn-decline-action:hover { background: #dc3545; color: white; }

        /* ── Section label ──────────────────────────── */
        .section-label {
            font-family: 'Orbitron', sans-serif;
            font-size: 0.85rem;
            letter-spacing: 0.08em;
            color: var(--primary-color);
            text-transform: uppercase;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(178, 0, 255, 0.2);
        }

        /* ── Empty / error state ────────────────────── */
        .empty-state {
            text-align: center;
            padding: 3.5rem 1rem;
            background: rgba(30, 30, 47, 0.5);
            border-radius: 14px;
            border: 1px solid rgba(127, 0, 255, 0.1);
        }
        .empty-state i { font-size: 2.8rem; color: #555; margin-bottom: 1rem; }
        .empty-state h5 { color: #ccc; margin-bottom: 0.4rem; }
        .empty-state p { color: #777; margin: 0; font-size: 0.9rem; }

        /* spinner */
        .spinner-border { color: var(--primary-color) !important; }
    </style>
</head>
<body>
<?php include '../includes/nav.php'; ?>

<!-- Hero -->
<section class="hero-section">
    <div class="container position-relative" style="z-index: 1;">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="display-5 mb-0" style="font-family: 'Orbitron', sans-serif;">
                    Find <span style="color: var(--primary-color);">Friends</span>
                </h1>
                <p class="lead mt-2 mb-0">Connect with other gamers and share your gaming journey.</p>
            </div>
        </div>
    </div>
</section>

<div class="container mb-5">

    <!-- Search bar -->
    <div class="search-wrap mb-5">
        <i class="bi bi-search"></i>
        <input type="text"
               class="search-input"
               id="userSearch"
               placeholder="Search by username or display name…"
               autocomplete="off">
    </div>

    <!-- Live results (hidden until user types) -->
    <div id="searchResults" style="display:none;"></div>

    <!-- Suggested users (shown when search is empty) -->
    <div id="suggestedSection">
        <?php if (!empty($suggested)): ?>
        <div class="section-label"><i class="bi bi-people"></i> People you might know</div>
        <div class="users-grid">
            <?php foreach ($suggested as $u):
                $initials = strtoupper(substr($u['username'], 0, 1) . substr($u['username'], 1, 1));
            ?>
            <div class="user-card" id="ucard-<?= $u['id'] ?>">
                <a href="view-profile.php?username=<?= htmlspecialchars($u['username']) ?>" class="user-card-link">
                    <?php if ($u['profile_image']): ?>
                        <img src="<?= htmlspecialchars($u['profile_image']) ?>" alt="<?= htmlspecialchars($u['username']) ?>" class="user-avatar">
                    <?php else: ?>
                        <div class="user-avatar-initials"><?= htmlspecialchars($initials) ?></div>
                    <?php endif; ?>
                    <div style="min-width:0;">
                        <p class="user-name"><?= htmlspecialchars($u['name'] ?: $u['username']) ?></p>
                        <p class="user-handle">@<?= htmlspecialchars($u['username']) ?></p>
                    </div>
                </a>
                <p class="user-about"><?= htmlspecialchars($u['about'] ?: 'No bio yet.') ?></p>
                <div class="user-stats">
                    <span><strong><?= (int)$u['followers'] ?></strong> Followers</span>
                    <span><strong><?= (int)$u['friends'] ?></strong> Friends</span>
                </div>
                <div class="user-actions">
                    <button class="btn-sm-action btn-follow-action" onclick="toggleFollow(<?= $u['id'] ?>)">
                        <i class="bi bi-person-plus"></i> Follow
                    </button>
                    <button class="btn-sm-action btn-friend-action" onclick="sendFriendRequest(<?= $u['id'] ?>)">
                        <i class="bi bi-people"></i> Add Friend
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-people"></i>
            <h5>No other users yet</h5>
            <p>Be the first to invite friends to GameTracker!</p>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
const searchInput  = document.getElementById('userSearch');
const resultsDiv   = document.getElementById('searchResults');
const suggestedDiv = document.getElementById('suggestedSection');
let searchTimeout;

function renderUsers(users) {
    if (!users.length) {
        return `<div class="empty-state">
            <i class="bi bi-search"></i>
            <h5>No users found</h5>
            <p>Try a different search term.</p>
        </div>`;
    }
    return `<div class="section-label"><i class="bi bi-search"></i> Search results</div>
    <div class="users-grid">` + users.map(u => `
        <div class="user-card">
            <a href="view-profile.php?username=${encodeURIComponent(u.username)}" class="user-card-link">
                ${u.profile_image
                    ? `<img src="${u.profile_image}" alt="${u.username}" class="user-avatar">`
                    : `<div class="user-avatar-initials">${u.initials}</div>`}
                <div style="min-width:0;">
                    <p class="user-name">${u.name || u.username}</p>
                    <p class="user-handle">@${u.username}</p>
                </div>
            </a>
            <p class="user-about">${u.about || 'No bio yet.'}</p>
            <div class="user-stats">
                <span><strong>${u.followers}</strong> Followers</span>
                <span><strong>${u.friends}</strong> Friends</span>
            </div>
            <div class="user-actions">
                ${renderActions(u)}
            </div>
        </div>`).join('') + `</div>`;
}

function renderActions(u) {
    const rel = u.relationship;
    if (rel === 'friends') {
        return `<button class="btn-sm-action btn-friend-action active" onclick="removeFriend(${u.id})">
            <i class="bi bi-people-fill"></i> Friends</button>`;
    }
    if (rel === 'friend_request_sent') {
        return `<button class="btn-sm-action btn-follow-action" onclick="toggleFollow(${u.id})">
                    <i class="bi bi-person-plus"></i> Follow</button>
                <button class="btn-sm-action btn-pending-action" disabled>
                    <i class="bi bi-clock"></i> Sent</button>`;
    }
    if (rel === 'friend_request_received') {
        return `<button class="btn-sm-action btn-accept-action" onclick="respondToFriendRequest(${u.id},'accept')">
                    <i class="bi bi-check-lg"></i> Accept</button>
                <button class="btn-sm-action btn-decline-action" onclick="respondToFriendRequest(${u.id},'decline')">
                    <i class="bi bi-x-lg"></i> Decline</button>`;
    }
    const followBtn = rel === 'following'
        ? `<button class="btn-sm-action btn-follow-action active" onclick="toggleFollow(${u.id})"><i class="bi bi-person-check-fill"></i> Following</button>`
        : `<button class="btn-sm-action btn-follow-action" onclick="toggleFollow(${u.id})"><i class="bi bi-person-plus"></i> Follow</button>`;
    const friendBtn = `<button class="btn-sm-action btn-friend-action" onclick="sendFriendRequest(${u.id})"><i class="bi bi-people"></i> Add Friend</button>`;
    return followBtn + friendBtn;
}

searchInput.addEventListener('input', function () {
    clearTimeout(searchTimeout);
    const query = this.value.trim();

    if (!query) {
        resultsDiv.style.display  = 'none';
        suggestedDiv.style.display = '';
        return;
    }

    suggestedDiv.style.display = 'none';
    resultsDiv.style.display   = '';
    resultsDiv.innerHTML = `<div class="text-center py-5">
        <div class="spinner-border" role="status"><span class="visually-hidden">Loading…</span></div></div>`;

    searchTimeout = setTimeout(() => {
        fetch('../api/search-users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query })
        })
        .then(r => r.json())
        .then(data => {
            resultsDiv.innerHTML = data.success
                ? renderUsers(data.users)
                : `<div class="empty-state"><i class="bi bi-exclamation-circle"></i>
                   <h5>Something went wrong</h5><p>${data.message || 'Please try again.'}</p></div>`;
        })
        .catch(() => {
            resultsDiv.innerHTML = `<div class="empty-state"><i class="bi bi-wifi-off"></i>
                <h5>Connection error</h5><p>Check your connection and try again.</p></div>`;
        });
    }, 300);
});

function apiAction(url, body) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    }).then(r => r.json());
}

function refreshSearch() { searchInput.dispatchEvent(new Event('input')); }

function toggleFollow(uid) {
    apiAction('../api/toggle-follow.php', { user_id: uid })
        .then(d => d.success ? refreshSearch() : showToast(d.message || 'Error', 'error'));
}
function sendFriendRequest(uid) {
    apiAction('../api/send-friend-request.php', { user_id: uid })
        .then(d => d.success ? refreshSearch() : showToast(d.message || 'Error', 'error'));
}
function respondToFriendRequest(uid, action) {
    apiAction('../api/respond-friend-request.php', { user_id: uid, action })
        .then(d => d.success ? refreshSearch() : showToast(d.message || 'Error', 'error'));
}
function removeFriend(uid) {
    showConfirm('Remove this friend?', function() {
        apiAction('../api/remove-friend.php', { user_id: uid })
            .then(d => d.success ? refreshSearch() : showToast(d.message || 'Error', 'error'));
    });
}
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html> 