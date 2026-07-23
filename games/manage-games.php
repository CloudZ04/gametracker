<?php
// Include database connection and start session
require_once '../includes/db.php';
session_start();

// Security check: Ensure user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../explore.php');
    exit();
}

// Get search and filter parameters from URL
$search = $_GET['search'] ?? '';
$platform = $_GET['platform'] ?? '';
$genre = $_GET['genre'] ?? '';
$duplicates_only = isset($_GET['duplicates_only']) && $_GET['duplicates_only'] === '1';

// Build WHERE clause for database query based on filters
$where = [];
if (!empty($search)) {
    $where[] = "title LIKE '%" . $conn->real_escape_string($search) . "%'";
}
if (!empty($platform)) {
    $where[] = "platforms LIKE '%" . $conn->real_escape_string($platform) . "%'";
}
if (!empty($genre)) {
    $where[] = "genre LIKE '%" . $conn->real_escape_string($genre) . "%'";
}
// Build query: duplicates_only uses a JOIN so we only get games whose title appears more than once
if ($duplicates_only) {
    $base_where = $where; // search, platform, genre
    $where_sql = !empty($base_where) ? " AND " . implode(' AND ', $base_where) : "";
    $sql = "SELECT g.* FROM games g
            INNER JOIN (SELECT title FROM games GROUP BY title HAVING COUNT(*) > 1) dup ON g.title = dup.title
            WHERE 1=1" . $where_sql . "
            ORDER BY g.title ASC, g.release_date ASC";
    $result = $conn->query($sql);
} else {
    $sql = "SELECT * FROM games";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    $sql .= " ORDER BY release_date ASC";
    $result = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Games - GameTracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/styles.css">
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

        /* Filter container styling */
        .filter-container {
            background: #1e1e2f;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(127, 0, 255, 0.1);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .custom-select,
        .search-input {
            background-color: rgba(30, 30, 47, 0.5);
            border: 1px solid rgba(127, 0, 255, 0.3);
            color: white;
        }

        .custom-select:focus,
        .search-input:focus {
            border-color: #b200ff;
            box-shadow: 0 0 0 0.25rem rgba(127, 0, 255, 0.25);
            background-color: rgba(30, 30, 47, 0.8);
        }

        .custom-select::placeholder,
        .search-input::placeholder {
            color: #a8a8b3;
        }

        .input-group-text {
            background: rgba(30, 30, 47, 0.5);
            border: 1px solid rgba(127, 0, 255, 0.3);
            color: #a8a8b3;
        }

        /* Form styling */
        .form-label {
            color: #ffffff;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 7 7 7-7'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            padding-right: 2.5rem;
        }

        .form-select option {
            background: #1e1e2f;
            color: #ffffff;
        }

        /* Table container styling */
        .table-container {
            background: #1e1e2f;
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .table-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(127, 0, 255, 0.3);
            border-color: rgba(127, 0, 255, 0.3);
        }

        /* Table styling */
        .table {
            margin-bottom: 0;
            color: #ffffff;
        }

        .table thead th {
            background: rgba(30, 30, 47, 0.9);
            border-bottom: 1px solid rgba(127, 0, 255, 0.3);
            color: var(--primary-color);
            font-family: 'Orbitron', sans-serif;
            font-weight: 500;
            padding: 1rem;
            border: none;
        }

        .table tbody td {
            background: rgba(30, 30, 47, 0.5);
            border-bottom: 1px solid rgba(127, 0, 255, 0.1);
            padding: 1rem;
            vertical-align: middle;
            border: none;
            color: #ffffff;
        }

        .table tbody tr:hover td {
            background: rgba(30, 30, 47, 0.8);
            color: #ffffff;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            flex-wrap: nowrap;
            white-space: nowrap;
        }

        .btn-edit, .btn-delete {
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            border: none;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            min-width: 70px;
            justify-content: center;
        }

        .btn-edit {
            background: var(--primary-color);
        }

        .btn-edit:hover {
            background:rgb(135, 4, 179);
            color: white;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: #dc3545;
        }

        .btn-delete:hover {
            background: #c82333;
            color: white;
            transform: translateY(-1px);
        }

        /* No results styling */
        .no-results {
            background: rgba(30, 30, 47, 0.5);
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 8px;
            padding: 3rem;
            text-align: center;
            color: #a8a8b3;
        }

        .no-results i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .hero-section {
                padding: 4rem 0 2rem;
            }

            .hero-section h1 {
                font-size: 2.5rem;
            }

            .filter-container {
                margin: 1rem;
                padding: 1rem;
            }

            .table-container {
                margin: 1rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .table thead {
                display: none;
            }

            .table tbody tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid rgba(127, 0, 255, 0.2);
                border-radius: 8px;
                overflow: hidden;
            }

            .table tbody td {
                display: block;
                text-align: left;
                padding: 0.75rem 1rem;
                border-bottom: 1px solid rgba(127, 0, 255, 0.1);
            }

            .table tbody td:before {
                content: attr(data-label) ": ";
                font-weight: bold;
                color: var(--primary-color);
            }

            .table tbody td:last-child {
                border-bottom: none;
            }
        }

        /* Make search bar text and placeholder white */
        input[type="text"].form-control, .search-input {
            color: #fff !important;
        }
        input[type="text"].form-control::placeholder, .search-input::placeholder {
            color: #fff !important;
            opacity: 1;
        }
    </style>
</head>

<body>

<?php include '../includes/nav.php'; ?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto text-center">
                <h1 class="mb-3"><i class="ph ph-wrench me-3"></i>Manage Games</h1>
                <p class="lead">View, edit, and organize all games in the GameTracker database with powerful search and filtering tools.</p>
            </div>
        </div>
    </div>
</section>

<div class="container-fluid py-4">
    <!-- Search and Filter -->
    <div class="row justify-content-center">
        <div class="col-12 col-lg-11">
            <div class="filter-container">
                <form method="get" class="row g-3">
                    <div class="col-12 col-md-6 col-lg-4">
                        <label for="search" class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0 text-light">
                                <i class="ph ph-magnifying-glass"></i>
                            </span>
                            <input type="text" name="search" id="search" class="form-control search-input border-start-0" 
                                   placeholder="Search game titles..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <label for="platform" class="form-label">Platform</label>
                        <select name="platform" id="platform" class="form-select custom-select">
                            <option value="">All Platforms</option>
                            <option value="PlayStation" <?= $platform == 'PlayStation' ? 'selected' : '' ?>>PlayStation</option>
                            <option value="Xbox" <?= $platform == 'Xbox' ? 'selected' : '' ?>>Xbox</option>
                            <option value="PC" <?= $platform == 'PC' ? 'selected' : '' ?>>PC</option>
                            <option value="Switch" <?= $platform == 'Switch' ? 'selected' : '' ?>>Nintendo Switch</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <label for="genre" class="form-label">Genre</label>
                        <select name="genre" id="genre" class="form-select custom-select">
                            <option value="">All Genres</option>
                            <option value="RPG" <?= $genre == 'RPG' ? 'selected' : '' ?>>RPG</option>
                            <option value="Action" <?= $genre == 'Action' ? 'selected' : '' ?>>Action</option>
                            <option value="Shooter" <?= $genre == 'Shooter' ? 'selected' : '' ?>>Shooter</option>
                            <option value="Adventure" <?= $genre == 'Adventure' ? 'selected' : '' ?>>Adventure</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-lg-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="ph ph-funnel me-2"></i>Apply Filters
                        </button>
                    </div>
                    <div class="col-6 col-md-3 col-lg-2 d-flex align-items-end">
                        <?php
                        $base_params = array_filter([
                            'search' => $search,
                            'platform' => $platform,
                            'genre' => $genre,
                        ]);
                        $dup_link_params = $base_params;
                        $dup_link_params['duplicates_only'] = '1';
                        $all_link_params = $base_params;
                        ?>
                        <?php if ($duplicates_only): ?>
                            <a href="manage-games.php?<?= http_build_query($all_link_params) ?>" class="btn btn-outline-secondary w-100">
                                <i class="ph ph-list-bullets me-2"></i>Show all
                            </a>
                        <?php else: ?>
                            <a href="manage-games.php?<?= http_build_query($dup_link_params) ?>" class="btn btn-outline-warning w-100" title="Show only games that share the same title (duplicates)">
                                <i class="ph ph-copy me-2"></i>Duplicates only
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-11">
            <?php if ($duplicates_only): ?>
                <div class="alert alert-info d-flex align-items-center mb-3" style="background: rgba(178, 0, 255, 0.15); border: 1px solid rgba(178, 0, 255, 0.3); color: #e0e0e0;">
                    <i class="ph ph-copy me-2" style="font-size: 1.5rem;"></i>
                    <span>Showing only games whose title appears more than once. Delete the duplicate(s) you don’t want to keep.</span>
                </div>
            <?php endif; ?>
            <?php if ($result->num_rows > 0): ?>
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><i class="ph ph-hash me-2"></i>ID</th>
                                    <th><i class="ph ph-game-controller me-2"></i>Title</th>
                                    <th><i class="ph ph-calendar me-2"></i>Release Date</th>
                                    <th><i class="ph ph-device-mobile me-2"></i>Platforms</th>
                                    <th><i class="ph ph-tag me-2"></i>Genres</th>
                                    <th><i class="ph ph-gear me-2"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td data-label="ID"><?= htmlspecialchars($row['id']) ?></td>
                                        <td data-label="Title"><?= htmlspecialchars($row['title']) ?></td>
                                        <td data-label="Release Date"><?= htmlspecialchars($row['release_date']) ?: 'TBA' ?></td>
                                        <td data-label="Platforms"><?= htmlspecialchars($row['platforms']) ?></td>
                                        <td data-label="Genres"><?= htmlspecialchars($row['genre']) ?></td>
                                        <td data-label="Actions">
                                            <div class="action-buttons">
                                                <a href="edit-game.php?id=<?= $row['id'] ?>" class="btn btn-edit">
                                                    <i class="ph ph-pencil me-1"></i>Edit
                                                </a>
                                                <a href="#" class="btn btn-delete" data-bs-toggle="modal" data-bs-target="#deleteGameModal" data-game-id="<?= $row['id'] ?>" data-game-title="<?= htmlspecialchars($row['title']) ?>">
                                                    <i class="ph ph-trash me-1"></i>Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <?php if ($duplicates_only): ?>
                        <i class="ph ph-check-circle"></i>
                        <h4>No duplicates found</h4>
                        <p>No games share the same title. <a href="manage-games.php" class="text-decoration-none" style="color: var(--primary-color);">Show all games</a>.</p>
                    <?php else: ?>
                        <i class="ph ph-magnifying-glass-minus"></i>
                        <h4>No games found</h4>
                        <p>Try adjusting your search criteria or <a href="add-game.php" class="text-decoration-none" style="color: var(--primary-color);">add a new game</a>.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Game Modal -->
<div class="modal fade" id="deleteGameModal" tabindex="-1" aria-labelledby="deleteGameModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light border border-danger">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="deleteGameModalLabel"><i class="ph ph-warning-circle me-2 text-danger"></i>Confirm Delete</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to <span class="text-danger fw-bold">delete</span> the game <span id="deleteGameTitle" class="fw-bold"></span>?</p>
        <p class="mb-0 text-danger"><small>This action cannot be undone.</small></p>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteGameBtn"><i class="ph ph-trash me-1"></i>Delete</button>
      </div>
    </div>
  </div>
</div>

<script>
let deleteGameId = null;
const deleteGameModal = document.getElementById('deleteGameModal');
const deleteGameTitle = document.getElementById('deleteGameTitle');
const confirmDeleteGameBtn = document.getElementById('confirmDeleteGameBtn');

deleteGameModal.addEventListener('show.bs.modal', function (event) {
  const button = event.relatedTarget;
  deleteGameId = button.getAttribute('data-game-id');
  const gameTitle = button.getAttribute('data-game-title');
  deleteGameTitle.textContent = gameTitle;
});

confirmDeleteGameBtn.addEventListener('click', function () {
  if (deleteGameId) {
    window.location.href = `delete-game.php?id=${deleteGameId}`;
  }
});
</script>

<?php include '../includes/footer.php'; ?>

</body>
</html>

<?php $conn->close(); ?>
