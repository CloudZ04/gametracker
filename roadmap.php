<?php
// Include database connection and start session
require_once 'includes/db.php';
session_start();

// Check if current user is an admin
$isAdmin = isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin';

// Fetch and organize roadmap features by phase
$featuresByPhase = [];
$result = $conn->query("SELECT * FROM roadmap ORDER BY phase ASC, created_at ASC");

// Group features by their phase
while ($row = $result->fetch_assoc()) {
    $featuresByPhase[$row['phase']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="images/logo.svg">
    <title>Roadmap - GameTracker</title>
    <!-- Include required CSS and fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            background: #15151e;
            color: #fff;
            min-height: 100vh;
            background-image: 
                radial-gradient(circle at 10% 10%, rgba(178, 0, 255, 0.03) 0%, transparent 70%),
                radial-gradient(circle at 90% 90%, rgba(178, 0, 255, 0.03) 0%, transparent 70%);
        }

        .roadmap-box h4 {
            font-family: 'Orbitron', sans-serif;
        }

        .roadmap-box strong,
        .btn,
        .modal-body,
        .roadmap-box .admin-controls {
            font-family: 'Exo 2', sans-serif !important;
        }

        .roadmap-box {
            background-color: #1e1e2f;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 0 10px #b200ff;
        }

        .list-group-item {
            transition: background-color 0.4s ease, color 0.4s ease;
            margin-bottom: 0.5rem;
            border: none;
        }

        .completed-feature {
            background-color: rgba(0, 255, 128, 0.1);
            border-left: 4px solid #00ff80;
            color: #00ffcc;
            font-weight: 500;
            box-shadow: inset 0 0 5px rgba(0, 255, 128, 0.3);
        }

        .toggle-description {
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: color 0.2s ease;
        }

        .toggle-description:hover {
            color: #b200ff;
        }


        .description-text {
            font-family: 'Exo 2', sans-serif;
            font-size: 0.9rem;
            color: #ccc;
            margin-top: 0.5rem;
            display: none;
        }

        .description-open .description-text {
            display: block;
        }

        .arrow {
            transition: transform 0.3s ease;
            font-size: 1.1rem;
            margin-right: 0.6rem;
            color: #b200ff;
        }

        .rotate {
            transform: rotate(90deg);
        }

        .btn-purple {
            background-color: #b200ff;
            color: #fff;
            border: none;
            font-size: 1.2em;
            font-weight: 600;
        }

        .btn-purple:hover {
            background-color: #9933ff;
        }

        /* Hero Section */
        .hero-section {
            position: relative;
            padding: 4rem 0 4rem;
            background: linear-gradient(to right, rgba(21, 21, 30, 0.9), rgba(21, 21, 30, 0.7));
            border-bottom: 1px solid rgba(178, 0, 255, 0.3);
            margin-bottom: 2rem;
            text-align: center;
        }
        .hero-section h1 {
            font-family: 'Orbitron', sans-serif;
            color: #b200ff;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        .hero-section .lead {
            color: #ffffff;
            font-size: 1.15rem;
            margin-bottom: 0;
            font-family: 'Exo 2', sans-serif;
        }

        /* Roadmap Card Modernization */
        .roadmap-box {
            background: #181828;
            border-radius: 18px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(178, 0, 255, 0.1);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        .roadmap-box h4 {
            font-family: 'Orbitron', sans-serif;
            color: #fff;
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            letter-spacing: 1px;
            position: relative;
        }
        .roadmap-box h4::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background: #b200ff;
            border-radius: 2px;
            margin-top: 0.5rem;
            margin-left: 0;
        }

        .list-group-item {
            background: rgba(30, 30, 47, 0.7) !important;
            border-radius: 8px !important;
            margin-bottom: 0.4rem;
            border: none;
            color: #fff;
            font-family: 'Exo 2', sans-serif;
            font-size: 0.98rem;
            box-shadow: 0 1px 4px rgba(178, 0, 255, 0.03);
            transition: background 0.3s, color 0.3s;
            padding: 0.6rem 0.8rem;
            display: flex;
            align-items: flex-start;
        }
        .completed-feature {
            background: linear-gradient(90deg, rgba(0,255,128,0.10) 60%, rgba(127,0,255,0.07));
            border-left: 4px solid #00ff80;
            color: #00ffcc;
            font-weight: 600;
            box-shadow: 0 0 8px rgba(0,255,128,0.08);
        }
        .toggle-description {
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: color 0.2s ease;
            font-weight: 600;
            font-size: 0.98rem;
            margin-bottom: 0;
        }
        .toggle-description:hover {
            color: #b200ff;
        }
        .description-text {
            font-family: 'Exo 2', sans-serif;
            font-size: 0.92rem;
            color: #ccc;
            margin-top: 0.3rem;
            display: none;
        }
        .description-open .description-text {
            display: block;
        }
        .arrow {
            transition: transform 0.3s ease;
            font-size: 1.1rem;
            margin-right: 0.6rem;
            color: #b200ff;
        }
        .completed-feature .arrow {
            color: #00ffcc;
        }
        .rotate {
            transform: rotate(90deg);
        }
        .btn-purple {
            background-color: #b200ff;
            color: #fff;
            border: none;
            font-size: 1.1em;
            font-weight: 600;
            border-radius: 8px;
            padding: 0.5rem 1.2rem;
            box-shadow: 0 2px 8px rgba(178, 0, 255, 0.10);
            transition: background 0.2s, box-shadow 0.2s;
        }
        .btn-purple:hover {
            background-color: #9933ff;
            box-shadow: 0 4px 16px rgba(178, 0, 255, 0.18);
        }
        .btn-outline-light, .btn-outline-warning, .btn-outline-danger {
            border-radius: 8px;
            font-size: 1em;
            font-family: 'Exo 2', sans-serif;
        }
        .admin-controls {
            margin-bottom: 2rem;
        }
        @media (max-width: 768px) {
            .hero-section {
                padding: 2.5rem 0 1rem;
            }
            .roadmap-box {
                padding: 1.2rem 0.7rem;
            }
            .list-group-item {
                font-size: 0.98rem;
                padding: 0.8rem 0.7rem;
            }
            .roadmap-box h4 {
                font-size: 1.1rem;
            }
            .hero-section h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
<?php include 'includes/nav.php'; ?>

<!-- Hero section with title and description -->
<section class="hero-section">
    <div class="container">
        <h1 class="mb-2"><i class="ph ph-map-trifold me-2"></i>Development Roadmap</h1>
        <p class="lead">See what's planned, what's in progress, and what's already been completed for GameTracker.gg.</p>
    </div>
</section>

<div class="container py-4">
    <!-- Admin controls for adding new features -->
    <?php if ($isAdmin): ?>
        <div class="admin-controls text-end mb-4">
            <a href="roadmap-manage.php?action=add" class="btn btn-sm btn-purple"><i class="ph ph-plus me-1"></i>Add Feature</a>
        </div>
    <?php endif; ?>

    <!-- Display features grouped by phase -->
    <?php if (empty($featuresByPhase)): ?>
        <p class="text-muted text-center">No features yet. Stay tuned!</p>
    <?php else: ?>
        <?php foreach ($featuresByPhase as $phase => $features): ?>
            <div class="roadmap-box">
                <h4 class="mb-3"><i class="ph ph-push-pin me-2"></i><?= htmlspecialchars($phase) ?></h4>
                <ul class="list-group list-group-flush">
                    <?php foreach ($features as $feature): ?>
                        <li class="list-group-item rounded <?= $feature['is_completed'] ? 'completed-feature' : 'bg-transparent text-light' ?>" id="feature-<?= $feature['id'] ?>">
                            <div class="flex-grow-1">
                                <!-- Feature title with toggle for description -->
                                <div class="d-flex align-items-center toggle-description" data-id="<?= $feature['id'] ?>">
                                    <span class="arrow">&#9654;</span>
                                    <strong><?= htmlspecialchars($feature['feature']) ?></strong>
                                </div>
                                <?php if (!empty($feature['description'])): ?>
                                    <div class="description-text"><?= nl2br(htmlspecialchars($feature['description'])) ?></div>
                                <?php endif; ?>
                            </div>
                            <!-- Admin controls for each feature -->
                            <?php if ($isAdmin): ?>
                                <div class="d-flex flex-shrink-0 gap-2 ms-3">
                                    <form class="toggle-completion-form d-inline" data-id="<?= $feature['id'] ?>" data-completed="<?= $feature['is_completed'] ? '1' : '0' ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-light">
                                            <?= $feature['is_completed'] ? 'Mark Incomplete' : 'Mark Complete' ?>
                                        </button>
                                    </form>
                                    <a href="roadmap-manage.php?action=edit&id=<?= $feature['id'] ?>" class="btn btn-sm btn-outline-warning">Edit</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?= $feature['id'] ?>)">Delete</button>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteFeatureModal" tabindex="-1" aria-labelledby="deleteFeatureModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light border border-danger">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ph ph-warning me-2"></i>Confirm Deletion</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">Are you sure you want to permanently delete this feature?</div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Toggle feature description visibility
document.querySelectorAll('.toggle-description').forEach(el => {
    el.addEventListener('click', () => {
        const parent = el.closest('li');
        const arrow = el.querySelector('.arrow');
        const desc = parent.querySelector('.description-text');
        parent.classList.toggle('description-open');
        arrow.classList.toggle('rotate');
    });
});

// Show delete confirmation modal
function confirmDelete(id) {
    const modal = new bootstrap.Modal(document.getElementById('deleteFeatureModal'));
    document.getElementById('confirmDeleteBtn').href = `roadmap-manage.php?action=delete&id=${id}`;
    modal.show();
}

// Handle feature completion toggle (admin only)
<?php if ($isAdmin): ?>
document.querySelectorAll('.toggle-completion-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const featureId = this.dataset.id;
        const currentStatus = this.dataset.completed;
        const listItem = this.closest('li');
        const button = this.querySelector('button');

        // Send toggle request to server
        fetch('roadmap-manage.php?action=toggle', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'id=' + encodeURIComponent(featureId)
        }).then(res => {
            if (!res.ok) throw new Error("Network error");
            const markingComplete = currentStatus === "0";

            // Update UI to reflect new status
            listItem.classList.remove('completed-feature', 'bg-transparent', 'text-light');
            void listItem.offsetWidth;

            if (markingComplete) {
                listItem.classList.add('completed-feature');
            } else {
                listItem.classList.add('bg-transparent', 'text-light');
            }

            button.textContent = markingComplete ? "Mark Incomplete" : "Mark Complete";
            this.dataset.completed = markingComplete ? "1" : "0";
        });
    });
});
<?php endif; ?>
</script>
</body>
</html>
