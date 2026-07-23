<?php
ob_start();

// Define status colors
$status_colors = [
    'Want to Play' => 'linear-gradient(to right, #0a9999,rgb(82, 138, 221))',
    'Playing' => 'linear-gradient(to right, #2e8b57,rgb(133, 211, 88))',
    'Beaten' => 'linear-gradient(to right, #5f2c82,rgb(105, 89, 197))',
    'Completed' => 'linear-gradient(to right,rgba(255, 217, 0, 0.85),rgb(255, 174, 0))',
    'Shelved' => 'linear-gradient(to right, #c96a25,rgb(221, 138, 82))',
    'Abandoned' => 'linear-gradient(to right, #c0392b,rgb(170, 83, 83))'
];

// Define collection descriptions for own profile
$collection_descriptions = [
    'Want to Play' => "Your gaming wishlist - discover new adventures, track upcoming releases, and plan your next gaming journey.",
    'Playing' => "Games you're actively exploring right now. Track your progress, unlock achievements, and immerse yourself in these ongoing adventures.",
    'Beaten' => "Games you've conquered by completing the main story. Each one represents a journey well-traveled and a tale well-told.",
    'Completed' => "Games you've mastered with 100% completion - all achievements, side quests, and collectibles conquered. True gaming perfection!",
    'Shelved' => "Games on pause - taking a break but not forgotten. They'll be here waiting when you're ready to return to their worlds.",
    'Abandoned' => "Games that didn't click or lost their appeal. Not every adventure is for everyone, and that's perfectly okay!"
];

// Get the appropriate description
if ($is_own_profile) {
    $collection_description = $collection_descriptions[$status] ?? "Track and manage your $status games collection.";
} else {
    $username = htmlspecialchars($profile_user['name'] ?: $profile_user['username']);
    
    // Trim any whitespace and ensure exact matching
    $status_trimmed = trim($status);
    
    switch($status_trimmed) {
        case 'Want to Play':
            $collection_description = "Games $username is excited to dive into someday - their gaming wishlist awaits!";
            break;
        case 'Playing':
            $collection_description = "Games $username is actively conquering right now - watch their progress unfold!";
            break;
        case 'Beaten':
            $collection_description = "Games $username has conquered by completing the main story - each one a victory!";
            break;
        case 'Completed':
            $collection_description = "Games $username has mastered with 100% completion - true gaming perfection achieved!";
            break;
        case 'Shelved':
            $collection_description = "Games $username has put on pause - taking a break but not forgotten!";
            break;
        case 'Abandoned':
            $collection_description = "Games $username decided weren't for them - not every adventure clicks with everyone!";
            break;
        default:
            $collection_description = "Games $username has in their $status_trimmed collection";
    }
    
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="GameTracker | <?= $is_own_profile ? 'Your' : htmlspecialchars($profile_user['name'] . "'s") ?> <?= htmlspecialchars($status) ?> Games">
    <title>GameTracker.gg | <?= $is_own_profile ? 'Your' : htmlspecialchars($profile_user['name'] . "'s") ?> <?= htmlspecialchars($status) ?> Collection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../includes/styles.css">
    <style>
        /* Hero section styling */
        .hero-section {
            position: relative;
            padding: 4rem 0 3rem;
            background: linear-gradient(to right, rgba(21, 21, 30, 0.9), rgba(21, 21, 30, 0.7));
            border-bottom: 1px solid rgba(127, 0, 255, 0.3);
            margin-bottom: 2rem;
        }

        .hero-image-stack {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
            opacity: 0.1;
            pointer-events: none;
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .hero-image-stack img {
            height: 400px;
            object-fit: cover;
            transform: rotate(-3deg);
            border-radius: 12px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.4);
        }

        /* Game card styles */
        .game-card {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            aspect-ratio: 2/3;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .game-card.selected {
            box-shadow: 0 0 0 2px var(--primary-color);
        }

        .game-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .game-card:hover img {
            transform: scale(1.05);
        }

        .game-info-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.9), rgba(0,0,0,0.7) 50%, transparent);
            padding: 2rem 1rem 1rem;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }

        .game-card:hover .game-info-overlay {
            transform: translateY(0);
        }

        .game-title {
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .selection-checkbox {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
            background: rgba(36, 36, 36, 0.6);
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: all;
        }

        .selection-checkbox input[type="checkbox"] {
            appearance: none;
            -webkit-appearance: none;
            width: 32px;
            height: 32px;
            border: 2px solid var(--primary-color);
            border-radius: 50%;
            margin: 0;
            position: relative;
            cursor: pointer;
            background: transparent;
        }

        .selection-checkbox input[type="checkbox"]:checked {
            background-color: transparent;
        }

        .selection-checkbox input[type="checkbox"]:checked::before {
            content: '✓';
            position: absolute;
            color: #b200ff;
            font-size: 24px;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            font-weight: bold;
            line-height: 1;
        }

        /* Collection stats */
        .collection-stats {
            background: var(--card-bg);
            border: 1px solid rgba(127, 0, 255, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            font-family: 'Orbitron', sans-serif;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid rgba(127, 0, 255, 0.1);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            opacity: 0.6;
        }

        /* Compare view */
        #compareView {
            background: var(--card-bg);
            border: 1px solid rgba(127, 0, 255, 0.2);
            border-radius: 12px;
            padding: 2rem;
            margin-top: 2rem;
        }

        #compareView h4 {
            color: var(--text-color);
            font-family: 'Orbitron', sans-serif;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>

<?php include '../../includes/nav.php'; ?>

<!-- Hero Section -->
<section class="hero-section position-relative overflow-hidden">
    <div class="hero-image-stack">
        <?php foreach ($heroImages as $img): ?>
            <img src="<?= htmlspecialchars($img) ?>" alt="Game Background">
        <?php endforeach; ?>
    </div>
    <div class="container position-relative" style="z-index: 1;">
        <div class="row">
            <div class="col-lg-8">
                <h1 class="display-4 mb-3" style="font-family: 'Orbitron', sans-serif;">
                    <span style="background: <?= $status_colors[$status] ?>; -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?= $status ?></span> Games
                </h1>
                <p class="lead mb-4">
                    <?= $collection_description ?>
                </p>
                <?php if ($is_own_profile): ?>
                <div class="d-flex gap-3">
                    <button class="btn btn-primary px-4" id="selectGamesBtn">
                        <i class="bi bi-check-square me-2"></i>
                        Select Games
                    </button>
                    <a href="../add-game.php" class="btn btn-outline-light">
                        <i class="bi bi-plus-lg me-2"></i>
                        Add Games
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<div class="container py-4">
    <div class="collection-stats">
        <div class="row">
            <div class="col-md-4">
                <div class="stat-item">
                    <div class="stat-number"><?= $total_games ?></div>
                    <div class="stat-label"><?= $collection_label ?> Games</div>
                </div>
            </div>
            <?php if ($is_own_profile): ?>
            <div class="col-md-4">
                <div class="stat-item">
                    <div class="stat-number" id="selectedCount">0</div>
                    <div class="stat-label">Selected</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-item">
                    <button class="btn btn-outline-primary btn-sm" id="selectAllBtn">
                        <i class="bi bi-check2-all me-2"></i>Select All
                    </button>
                </div>
            </div>
            <?php else: ?>
            <div class="col-md-4 offset-md-4">
                <div class="stat-item">
                    <button class="btn btn-primary" id="compareBtn">
                        <i class="bi bi-arrow-left-right me-2"></i>Compare Collections
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_own_profile): ?>
    <!-- Edit Buttons Container -->
    <div id="editButtonsContainer" class="mb-4" style="display: none;">
        <div class="card" style="background: var(--card-bg); border: 1px solid rgba(127, 0, 255, 0.2);">
            <div class="card-body">
                <h6 class="card-title text-light mb-3">
                    <i class="bi bi-pencil-square me-2"></i>Edit Selected Games
                </h6>
                
                <!-- Move to Collection Buttons -->
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button class="btn" onclick="moveGames('Want to Play')" title="Move to Want to Play" style="background: linear-gradient(to right, #0a9999, rgb(82, 138, 221)); border: none; color: white; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                        <i class="ph-fill ph-list-plus" style="font-size: 1.2rem;"></i>
                    </button>
                    <button class="btn" onclick="moveGames('Playing')" title="Move to Playing" style="background: linear-gradient(to right, #2e8b57, rgb(133, 211, 88)); border: none; color: white; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                        <i class="ph-fill ph-game-controller" style="font-size: 1.2rem;"></i>
                    </button>
                    <button class="btn" onclick="moveGames('Beaten')" title="Move to Beaten" style="background: linear-gradient(to right, #5f2c82, rgb(105, 89, 197)); border: none; color: white; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-check-circle" style="font-size: 1.2rem;"></i>
                    </button>
                    <button class="btn" onclick="moveGames('Completed')" title="Move to Completed" style="background: linear-gradient(to right, rgba(255, 217, 0, 0.85), rgb(255, 174, 0)); border: none; color: white; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-trophy" style="font-size: 1.2rem;"></i>
                    </button>
                    <button class="btn" onclick="moveGames('Shelved')" title="Move to Shelved" style="background: linear-gradient(to right, #c96a25, rgb(221, 138, 82)); border: none; color: white; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-pause-circle" style="font-size: 1.2rem;"></i>
                    </button>
                    <button class="btn" onclick="moveGames('Abandoned')" title="Move to Abandoned" style="background: linear-gradient(to right, #c0392b, rgb(170, 83, 83)); border: none; color: white; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-x-circle" style="font-size: 1.2rem;"></i>
                    </button>
                </div>
                
                <!-- Remove Button -->
                <div class="border-top pt-2">
                    <button class="btn btn-danger btn-sm" onclick="removeGames()" title="Remove from Collection">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
         <!-- Confirmation Modal -->
     <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
         <div class="modal-dialog modal-dialog-centered">
             <div class="modal-content" style="background: #1a1a2e; border: 1px solid rgba(127, 0, 255, 0.2);">
                 <div class="modal-header" style="border-bottom: 1px solid rgba(127, 0, 255, 0.2); background: #1a1a2e;">
                     <h5 class="modal-title text-light" id="confirmationModalLabel">
                         <i class="bi bi-exclamation-triangle me-2"></i>Confirm Action
                     </h5>
                     <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                 </div>
                 <div class="modal-body" style="background: #1a1a2e;">
                     <p class="text-light mb-0" id="confirmationMessage"></p>
                 </div>
                 <div class="modal-footer" style="border-top: 1px solid rgba(127, 0, 255, 0.2); background: #1a1a2e;">
                     <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                     <button type="button" class="btn btn-primary" id="confirmActionBtn">Confirm</button>
                 </div>
             </div>
         </div>
     </div>
    
         <!-- Success Toast -->
     <div class="toast-container position-fixed bottom-0 end-0 p-3">
         <div id="successToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
             <div class="toast-header" style="background: #1a1a2e; border-bottom: 1px solid rgba(127, 0, 255, 0.2);">
                 <i class="bi bi-check-circle text-success me-2"></i>
                 <strong class="me-auto text-light">Success</strong>
                 <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
             </div>
             <div class="toast-body text-light" id="successMessage" style="background: #1a1a2e;">
                 Action completed successfully!
             </div>
         </div>
     </div>
    <?php endif; ?>

    <?php if (!$is_own_profile): ?>
    <!-- Compare View Container -->
    <div id="compareView" style="display: none;">
        <div class="row">
            <!-- Their Collection -->
            <div class="col-md-6">
                <h4 class="mb-4"><?= htmlspecialchars($profile_user['name'] ?: $profile_user['username']) ?>'s Collection</h4>
                <div class="row g-4" id="theirGamesGrid">
                    <!-- Their games will be moved here -->
                </div>
            </div>
            <!-- Your Collection -->
            <div class="col-md-6">
                <h4 class="mb-4">Your Collection</h4>
                <div class="row g-4" id="yourGamesGrid">
                    <!-- Your games will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($total_games > 0): ?>
        <div class="row g-4" id="gamesGrid">
            <?php foreach ($games as $game): ?>
                <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                    <div class="game-card" data-game-id="<?= $game['id'] ?>">
                        <?php if ($is_own_profile): ?>
                        <div class="selection-checkbox">
                            <input type="checkbox" class="game-checkbox" value="<?= $game['id'] ?>">
                        </div>
                        <?php endif; ?>
                        
                        <a href="../../games/game-detail.php?id=<?= $game['id'] ?>" class="text-decoration-none game-link">
                            <?php 
                            $img = !empty($game['portrait_image_url']) ? $game['portrait_image_url'] : $game['image_url'];
                            if (!empty($img)): 
                            ?>
                                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($game['title']) ?>">
                            <?php else: ?>
                                <img src="../../assets/default-game.jpg" alt="Default game image">
                            <?php endif; ?>
                            
                            <div class="game-info-overlay">
                                <h5 class="game-title"><?= htmlspecialchars($game['title']) ?></h5>
                            </div>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="ph-fill ph-game-controller"></i>
            <h3>No <?= $status ?> Games</h3>
            <?php if ($is_own_profile): ?>
                <p class="mb-4"><?= $empty_state_message ?></p>
                <a href="../../explore.php" class="btn btn-primary">
                    <i class="bi bi-search me-2"></i>Browse Games
                </a>
            <?php else: ?>
                <p class="mb-4"><?= htmlspecialchars($profile_user['name'] ?: $profile_user['username']) ?> hasn't added any games to their <?= strtolower($status) ?> collection yet.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!$is_own_profile): ?>
    // Compare functionality
    const compareBtn = document.getElementById('compareBtn');
    const normalView = document.getElementById('gamesGrid');
    const compareView = document.getElementById('compareView');
    let isComparing = false;

    compareBtn.addEventListener('click', async function() {
        isComparing = !isComparing;
        
        if (isComparing) {
            compareBtn.innerHTML = '<i class="bi bi-x-lg me-2"></i>Exit Compare';
            normalView.style.display = 'none';
            compareView.style.display = 'block';

            // Move their games to the left column
            const theirGames = document.getElementById('theirGamesGrid');
            const gameCards = normalView.querySelectorAll('.col-lg-2');
            gameCards.forEach(card => {
                const newCol = document.createElement('div');
                newCol.className = 'col-lg-4 col-md-6';
                newCol.appendChild(card.querySelector('.game-card').cloneNode(true));
                theirGames.appendChild(newCol);
            });

            // Fetch and display your games
            try {
                const response = await fetch(`get-collection.php?status=<?= urlencode($status) ?>`);
                const data = await response.json();
                const yourGames = document.getElementById('yourGamesGrid');
                
                if (data.games.length > 0) {
                    data.games.forEach(game => {
                        const gameCard = `
                            <div class="col-lg-4 col-md-6">
                                <div class="game-card">
                                    <a href="../../games/game-detail.php?id=${game.id}" class="text-decoration-none game-link">
                                        <img src="${game.portrait_image_url || game.image_url || '../../assets/default-game.jpg'}" 
                                             alt="${game.title}">
                                        <div class="game-info-overlay">
                                            <h5 class="game-title">${game.title}</h5>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        `;
                        yourGames.insertAdjacentHTML('beforeend', gameCard);
                    });
                } else {
                    yourGames.innerHTML = `
                        <div class="col-12">
                            <div class="empty-state">
                                <p>You don't have any <?= strtolower($status) ?> games yet.</p>
                            </div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error fetching your games:', error);
            }
        } else {
            compareBtn.innerHTML = '<i class="bi bi-arrow-left-right me-2"></i>Compare Collections';
            normalView.style.display = 'flex';
            compareView.style.display = 'none';
            document.getElementById('theirGamesGrid').innerHTML = '';
            document.getElementById('yourGamesGrid').innerHTML = '';
        }
    });
    <?php endif; ?>

    <?php if ($is_own_profile): ?>
    // Selection functionality
    let selectedGames = new Set();

    // Handle individual game card clicks for selection
    document.querySelectorAll('.game-card').forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't select if clicking on the game link
            if (e.target.closest('.game-link') && !e.target.closest('.selection-checkbox')) {
                return;
            }
            
            e.preventDefault();
            e.stopPropagation();
            
            const gameId = this.getAttribute('data-game-id');
            const checkbox = this.querySelector('.game-checkbox');
            
            if (selectedGames.has(gameId)) {
                selectedGames.delete(gameId);
                this.classList.remove('selected');
                checkbox.checked = false;
            } else {
                selectedGames.add(gameId);
                this.classList.add('selected');
                checkbox.checked = true;
            }
            
            updateSelectedCount();
            updateEditButtons();
        });
    });

    // Handle checkbox clicks
    document.querySelectorAll('.game-checkbox').forEach(checkbox => {
        checkbox.addEventListener('click', function(e) {
            e.stopPropagation();
            
            const gameId = this.value;
            const card = this.closest('.game-card');
            
            if (this.checked) {
                selectedGames.add(gameId);
                card.classList.add('selected');
            } else {
                selectedGames.delete(gameId);
                card.classList.remove('selected');
            }
            
            updateSelectedCount();
            updateEditButtons();
        });
    });

    // Select All functionality
    document.getElementById('selectAllBtn')?.addEventListener('click', function() {
        const allCards = document.querySelectorAll('.game-card');
        const allCheckboxes = document.querySelectorAll('.game-checkbox');
        
        if (selectedGames.size === allCards.length) {
            // Deselect all
            selectedGames.clear();
            allCards.forEach(card => card.classList.remove('selected'));
            allCheckboxes.forEach(cb => cb.checked = false);
            this.innerHTML = '<i class="bi bi-check2-all me-2"></i>Select All';
        } else {
            // Select all
            selectedGames.clear();
            allCards.forEach(card => {
                const gameId = card.getAttribute('data-game-id');
                selectedGames.add(gameId);
                card.classList.add('selected');
            });
            allCheckboxes.forEach(cb => cb.checked = true);
            this.innerHTML = '<i class="bi bi-x-square me-2"></i>Deselect All';
        }
        
        updateSelectedCount();
        updateEditButtons();
    });

    function updateSelectedCount() {
        const countElement = document.getElementById('selectedCount');
        if (countElement) {
            countElement.textContent = selectedGames.size;
        }
    }

    function updateEditButtons() {
        const editButtonsContainer = document.getElementById('editButtonsContainer');
        if (!editButtonsContainer) return;

        if (selectedGames.size > 0) {
            editButtonsContainer.style.display = 'block';
        } else {
            editButtonsContainer.style.display = 'none';
        }
    }

    // Initialize edit buttons visibility
    updateEditButtons();

        // Function to show confirmation modal
    function showConfirmation(message, onConfirm) {
        const modal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        document.getElementById('confirmationMessage').textContent = message;
        
        // Remove existing event listeners
        const confirmBtn = document.getElementById('confirmActionBtn');
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        
        // Add new event listener
        newConfirmBtn.addEventListener('click', function() {
            modal.hide();
            onConfirm();
        });
        
        modal.show();
    }

    // Function to show success toast
    function showSuccessToast(message) {
        document.getElementById('successMessage').textContent = message;
        const toast = new bootstrap.Toast(document.getElementById('successToast'));
        toast.show();
    }

    // Function to move games to different collections
    window.moveGames = function(newStatus) {
        if (selectedGames.size === 0) {
            showConfirmation('Please select at least one game to move.', function() {
                // Do nothing, just close the modal
            });
            return;
        }

        showConfirmation(`Move ${selectedGames.size} selected game(s) to ${newStatus}?`, function() {
            const gameIds = Array.from(selectedGames);
            
            fetch('update-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    game_ids: gameIds,
                    new_status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the moved games from the current view
                    gameIds.forEach(gameId => {
                        const card = document.querySelector(`[data-game-id="${gameId}"]`);
                        if (card) {
                            card.closest('.col-lg-2').remove();
                        }
                    });
                    
                    // Clear selection
                    selectedGames.clear();
                    document.querySelectorAll('.game-checkbox').forEach(cb => cb.checked = false);
                    document.querySelectorAll('.game-card').forEach(card => card.classList.remove('selected'));
                    
                    // Update counts
                    updateSelectedCount();
                    updateEditButtons();
                    
                    // Update total games count
                    const totalGamesElement = document.querySelector('.stat-number');
                    if (totalGamesElement) {
                        const currentTotal = parseInt(totalGamesElement.textContent);
                        totalGamesElement.textContent = currentTotal - gameIds.length;
                    }
                    
                    // Show success message
                    showSuccessToast(`Successfully moved ${gameIds.length} game(s) to ${newStatus}!`);
                    
                    // Hide edit buttons if no games left
                    if (document.querySelectorAll('.game-card').length === 0) {
                        location.reload(); // Reload to show empty state
                    }
                } else {
                    showConfirmation('Error: ' + (data.message || 'Failed to move games'), function() {
                        // Do nothing, just close the modal
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showConfirmation('An error occurred while moving games', function() {
                    // Do nothing, just close the modal
                });
            });
        });
    };

     // Function to remove games from collection entirely
     window.removeGames = function() {
         if (selectedGames.size === 0) {
             showConfirmation('Please select at least one game to remove.', function() {
                 // Do nothing, just close the modal
             });
             return;
         }

         showConfirmation(`Remove ${selectedGames.size} selected game(s) from your collection? This action cannot be undone.`, function() {
             const gameIds = Array.from(selectedGames);
             
             fetch('update-status.php', {
                 method: 'POST',
                 headers: {
                     'Content-Type': 'application/json',
                 },
                 body: JSON.stringify({
                     game_ids: gameIds,
                     new_status: 'remove'
                 })
             })
             .then(response => response.json())
             .then(data => {
                 if (data.success) {
                     // Remove the games from the current view
                     gameIds.forEach(gameId => {
                         const card = document.querySelector(`[data-game-id="${gameId}"]`);
                         if (card) {
                             card.closest('.col-lg-2').remove();
                         }
                     });
                     
                     // Clear selection
                     selectedGames.clear();
                     document.querySelectorAll('.game-checkbox').forEach(cb => cb.checked = false);
                     document.querySelectorAll('.game-card').forEach(card => card.classList.remove('selected'));
                     
                     // Update counts
                     updateSelectedCount();
                     updateEditButtons();
                     
                     // Update total games count
                     const totalGamesElement = document.querySelector('.stat-number');
                     if (totalGamesElement) {
                         const currentTotal = parseInt(totalGamesElement.textContent);
                         totalGamesElement.textContent = currentTotal - gameIds.length;
                     }
                     
                     // Show success message
                     showSuccessToast(`Successfully removed ${gameIds.length} game(s) from your collection!`);
                     
                     // Hide edit buttons if no games left
                     if (document.querySelectorAll('.game-card').length === 0) {
                         location.reload(); // Reload to show empty state
                     }
                 } else {
                     showConfirmation('Error: ' + (data.message || 'Failed to remove games'), function() {
                         // Do nothing, just close the modal
                     });
                 }
             })
             .catch(error => {
                 console.error('Error:', error);
                 showConfirmation('An error occurred while removing games', function() {
                     // Do nothing, just close the modal
                 });
             });
         });
     };
     <?php endif; ?>
});
</script>


</body>
</html><?php
$output = ob_get_clean();
echo $output;
?> 