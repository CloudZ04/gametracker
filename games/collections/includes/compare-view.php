<?php if (!$is_own_profile): ?>
<div class="col-md-4 offset-md-4">
    <div class="stat-item">
        <button class="btn btn-primary" id="compareBtn">
            <i class="bi bi-arrow-left-right me-2"></i>Compare Collections
        </button>
    </div>
</div>
<?php endif; ?>

<!-- Compare View Container -->
<div id="compareView" class="container-fluid py-4" style="display: none;">
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

<!-- Compare View JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!$is_own_profile): ?>
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
});
</script> 