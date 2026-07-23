<?php
// Include database connection and start session
require_once '../includes/db.php';
session_start();

// Security check: Verify user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../explore.php');
    exit();
}

// --- Database Statistics Queries ---
// Get total number of games in database
$total_games = $conn->query("SELECT COUNT(*) FROM games")->fetch_row()[0];

// Count games with missing information (images or description)
$missing_games = $conn->query("SELECT COUNT(*) FROM games WHERE image_url = '' OR image_url IS NULL OR portrait_image_url = '' OR portrait_image_url IS NULL OR description = '' OR description IS NULL")->fetch_row()[0];

// Get total number of registered users
$total_users = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];

// Calculate new users registered this month
$first_of_month = date('Y-m-01');
$new_users_month = $conn->query("SELECT COUNT(*) FROM users WHERE created_at >= '$first_of_month'")->fetch_row()[0];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta tags and title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - GameTracker</title>
    
    <!-- External CSS dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/styles.css">
    
    <!-- Custom styles for admin dashboard -->
    <style>
        :root {
            --primary-color: #b200ff;
        }
        
        /* Hero section styling */
        .hero-section {
            position: relative;
            padding: 6rem 0 4rem;
            background: linear-gradient(to right, rgba(21, 21, 30, 0.9), rgba(21, 21, 30, 0.7));
            border-bottom: 1px solid rgba(127, 0, 255, 0.3);
            margin-bottom: 2rem;
        }

        .hero-section h1 {
            font-family: 'Orbitron', sans-serif;
            color: var(--primary-color);
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .hero-section .lead {
            color: #ffffff;
            font-size: 1.2rem;
            margin-bottom: 0;
        }

        /* Admin cards using consistent game-card styling */
        .admin-card {
            background: #1e1e2f;
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            padding: 1.5rem;
        }

        .admin-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 0 20px rgba(127, 0, 255, 0.5);
            border-color: rgba(127, 0, 255, 0.5);
        }

        .admin-card h2 {
            font-family: 'Orbitron', sans-serif;
            color: var(--primary-color);
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            background: rgba(30, 30, 47, 0.5);
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            border-color: rgba(127, 0, 255, 0.3);
            background: rgba(30, 30, 47, 0.8);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-color);
            font-family: 'Orbitron', sans-serif;
        }

        .stat-label {
            color: #a8a8b3;
            font-size: 0.9rem;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .hero-section {
                padding: 4rem 0 2rem;
                text-align: center;
            }

            .hero-section h1 {
                font-size: 2.5rem;
            }

            .admin-card {
                margin-bottom: 2rem;
            }
        }
    </style>
</head>

<body>
<!-- Include navigation bar -->
<?php include '../includes/nav.php'; ?>

<!-- Hero Section: Welcome message and admin greeting -->
<section class="hero-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto text-center">
                <h1 class="mb-3">Admin Dashboard</h1>
                <p class="lead">Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>! Manage your site, view stats, and keep GameTracker running smoothly.</p>
            </div>
        </div>
    </div>
</section>

<div class="container py-4">
    <div class="row justify-content-center">
        <!-- Admin Controls Panel: Quick access to game management -->
        <div class="col-lg-8 col-xl-6 mb-4">
            <div class="admin-card">
                <h2><i class="bi bi-gear-fill me-2"></i>Admin Controls</h2>
                <p class="text-light mb-4">Manage games and site content from here.</p>
                <div class="d-flex flex-wrap gap-3">
                    <!-- Action buttons for game management -->
                    <a href="../games/add-game.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Add New Game
                    </a>
                    <a href="../games/manage-games.php" class="btn btn-primary">
                        <i class="bi bi-tools me-2"></i>Manage Games
                    </a>
                    
                        <a href="game-approval.php" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Game Approvals
                        </a>
                        <a href="game-requests.php" class="btn btn-primary">
                            <i class="bi bi-inbox-fill me-2"></i>User Requests
                        </a>
                  
                    <button type="button" class="btn btn-primary" id="updateSteamBtn">
                        <i class="bi bi-steam me-2"></i>Update Steam IDs
                    </button>
                    <button type="button" class="btn btn-primary" id="updatePortraitsBtn">
                        <i class="bi bi-image me-2"></i>Refresh Portrait Images
                    </button>
                    <button type="button" class="btn btn-primary" id="scanDlcsBtn">
                        <i class="bi bi-diagram-3 me-2"></i>Scan & Move DLCs
                    </button>
                    <button type="button" class="btn btn-outline-light" id="scanDlcsDryRunBtn">
                        <i class="bi bi-search me-2"></i>DLC Dry Run
                    </button>
                    <a href="logout.php" class="btn btn-outline-light">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row justify-content-center">
        <!-- Statistics Panel: Display key metrics -->
        <div class="col-lg-8 col-xl-6 mb-4">
            <div class="admin-card">
                <h2><i class="bi bi-graph-up me-2"></i>Site Statistics</h2>
                
                <div class="row">
                    <!-- Total Games Stat -->
                    <div class="col-sm-6 mb-3">
                        <div class="stat-item">
                            <i class="ph ph-game-controller stat-icon" style="color: var(--primary-color);"></i>
                            <div class="stat-number"><?= $total_games ?></div>
                            <div class="stat-label">Total Games</div>
                        </div>
                    </div>
                    
                    <!-- Games Missing Info Stat -->
                    <div class="col-sm-6 mb-3">
                        <div class="stat-item">
                            <i class="ph ph-warning-circle stat-icon" style="color: #ff8c00;"></i>
                            <div class="stat-number"><?= $missing_games ?></div>
                            <div class="stat-label">Games Missing Info</div>
                        </div>
                    </div>
                    
                    <!-- Total Users Stat -->
                    <div class="col-sm-6 mb-3">
                        <div class="stat-item">
                            <i class="ph ph-users stat-icon" style="color: #00bfff;"></i>
                            <div class="stat-number"><?= $total_users ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                    </div>
                    
                    <!-- New Users This Month Stat -->
                    <div class="col-sm-6 mb-3">
                        <div class="stat-item">
                            <i class="ph ph-user-plus stat-icon" style="color: #00ff99;"></i>
                            <div class="stat-number"><?= $new_users_month ?></div>
                            <div class="stat-label">New Users This Month</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include footer -->
<?php include '../includes/footer.php'; ?>

<!-- DLC Scan Results Modal -->
<div class="modal fade" id="dlcScanResultsModal" tabindex="-1" aria-labelledby="dlcScanResultsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="background:#1e1e2f;border:1px solid rgba(127,0,255,.25);">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="dlcScanResultsModalLabel">DLC Scan Results</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <pre id="dlcScanSummary" class="p-3 rounded" style="background:rgba(255,255,255,.05);color:#fff;white-space:pre-wrap;"></pre>
                <div class="table-responsive mt-3">
                    <table class="table table-dark table-striped table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="min-width:260px;">Title</th>
                                <th style="min-width:180px;">Action</th>
                                <th>Detected By</th>
                            </tr>
                        </thead>
                        <tbody id="dlcScanResultsBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JavaScript -->
<script>
    function escapeHtml(text) {
        return String(text ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function showDlcScanResultsModal(title, stats, changes) {
        const summary = 
            title + '\n' +
            'Dry run: ' + ((stats.dry_run ?? false) ? 'Yes' : 'No') + '\n' +
            'Games scanned: ' + (stats.total_games_scanned ?? 0) + '\n' +
            'DLC candidates: ' + (stats.candidate_dlcs ?? 0) + '\n' +
            'Review needed: ' + (stats.review_needed ?? 0) + '\n' +
            'Would move to dlcs: ' + (stats.would_move_to_dlcs ?? 0) + '\n' +
            'Moved to dlcs: ' + (stats.moved_to_dlcs ?? 0) + '\n' +
            'Already in dlcs: ' + (stats.already_in_dlcs ?? 0) + '\n' +
            'Skipped (no parent): ' + (stats.skipped_no_parent ?? 0) + '\n' +
            'Skipped (non-Steam): ' + (stats.skipped_non_steam ?? 0) + '\n' +
            'Skipped (unknown Steam type): ' + (stats.skipped_unknown_steam_type ?? 0) + '\n' +
            'RAWG checked: ' + (stats.rawg_checked ?? 0) + '\n' +
            'Steam checked: ' + (stats.steam_checked ?? 0) + '\n' +
            'IGDB checked: ' + (stats.igdb_checked ?? 0) + '\n' +
            'Steam DLC hits: ' + (stats.verified_by_steam_dlc ?? 0) + '\n' +
            'Steam non-DLC rejects: ' + (stats.rejected_by_steam_non_dlc ?? 0) + '\n' +
            'IGDB DLC hits: ' + (stats.verified_by_igdb_dlc ?? 0) + '\n' +
            'IGDB non-DLC rejects: ' + (stats.rejected_by_igdb_non_dlc ?? 0) + '\n' +
            'Errors: ' + (stats.errors ?? 0);

        const summaryEl = document.getElementById('dlcScanSummary');
        const tbody = document.getElementById('dlcScanResultsBody');
        const titleEl = document.getElementById('dlcScanResultsModalLabel');
        summaryEl.textContent = summary;
        titleEl.textContent = title;

        const rows = Array.isArray(changes) ? changes : [];
        tbody.innerHTML = rows.map(change => `
            <tr>
                <td>${escapeHtml(change.title || 'Unknown')}</td>
                <td><span class="badge bg-secondary">${escapeHtml(change.action || '-')}</span></td>
                <td>${escapeHtml(change.detected_by || '-')}</td>
            </tr>
        `).join('');

        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No detailed rows returned.</td></tr>';
        }

        console.group('DLC Scan Results');
        console.log(summary);
        console.table(rows);
        console.groupEnd();

        const modal = new bootstrap.Modal(document.getElementById('dlcScanResultsModal'));
        modal.show();
    }

    document.getElementById('updateSteamBtn').addEventListener('click', function(e) {
        const button = this;
        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Updating...';

        fetch('update_steam_ids.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    location.reload();
                } else {
                    showToast('Failed to update Steam IDs: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to update Steam IDs: ' + error.message, 'error');
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalHtml;
            });
    });

    document.getElementById('updatePortraitsBtn').addEventListener('click', function() {
        const button = this;
        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Refreshing...';

        fetch('update_portrait_images.php')
            .then(response => response.text())
            .then(text => {
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response: ' + text.slice(0, 220));
                }
                return data;
            })
            .then(data => {
                if (data.success) {
                    const stats = data.stats || {};
                    showToast(
                        'Portrait refresh complete — ' +
                        (stats.updated ?? 0) + ' updated, ' +
                        (stats.errors ?? 0) + ' errors out of ' +
                        (stats.total_games ?? 0) + ' games.',
                        'success', 8000
                    );
                } else {
                    showToast('Failed to refresh portraits: ' + (data.details || data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Portrait refresh error:', error);
                showToast('Failed to refresh portraits: ' + error.message, 'error');
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalHtml;
            });
    });

    document.getElementById('scanDlcsBtn').addEventListener('click', function() {
        const button = this;
        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Scanning...';

        fetch('scan_dlcs.php?include_all=1')
            .then(response => response.text())
            .then(text => {
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response: ' + text.slice(0, 220));
                }
                return data;
            })
            .then(data => {
                if (data.success) {
                    showDlcScanResultsModal('DLC Scan Results', data.stats || {}, data.changes || []);
                    location.reload();
                } else {
                    showToast('Failed to scan DLCs: ' + (data.details || data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('DLC scan error:', error);
                showToast('Failed to scan DLCs: ' + error.message, 'error');
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalHtml;
            });
    });

    document.getElementById('scanDlcsDryRunBtn').addEventListener('click', function() {
        const button = this;
        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Previewing...';

        fetch('scan_dlcs.php?dry_run=1&include_all=1')
            .then(response => response.text())
            .then(text => {
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response: ' + text.slice(0, 220));
                }
                return data;
            })
            .then(data => {
                if (data.success) {
                    showDlcScanResultsModal('DLC Dry Run Results', data.stats || {}, data.changes || []);
                } else {
                    showToast('Failed to run DLC dry run: ' + (data.details || data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('DLC dry run error:', error);
                showToast('Failed to run DLC dry run: ' + error.message, 'error');
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalHtml;
            });
    });
</script>
</body>
</html>
 