<?php
require_once '../includes/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle delete
if (isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    $stmt = $conn->prepare("DELETE FROM patch_notes WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = 'Patch note deleted successfully!';
    } else {
        $error = 'Failed to delete patch note.';
    }
}

// Get all patch notes
$query = "SELECT * FROM patch_notes ORDER BY release_date DESC";
$result = $conn->query($query);

$patch_notes = [];
while ($row = $result->fetch_assoc()) {
    $patch_notes[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Patch Notes | GameTracker.gg</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/styles.css">
    <style>
        .patch-note-row {
            background: var(--card-bg);
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .patch-note-row:hover {
            border-color: var(--primary-color);
            box-shadow: 0 2px 10px rgba(127, 0, 255, 0.1);
        }

        .patch-note-version {
            font-family: 'Orbitron', sans-serif;
            color: var(--primary-color);
            font-weight: bold;
        }

        .patch-note-title {
            font-weight: 600;
            color: white;
        }

        .patch-note-date {
            color: #888;
            font-size: 0.9rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
<?php include '../includes/nav.php'; ?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 style="font-family: 'Orbitron', sans-serif;">
            <span style="color: var(--primary-color);">Manage</span> Patch Notes
        </h2>
        <a href="add-patch-note.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add New Patch Note
        </a>
    </div>

    <?php if (isset($message)): ?>
        <div class="alert alert-success" role="alert">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($patch_notes)): ?>
        <div class="text-center py-5">
            <i class="bi bi-file-earmark-text" style="font-size: 4rem; color: rgba(127, 0, 255, 0.3);"></i>
            <h4 class="mt-3">No Patch Notes</h4>
            <p class="text-muted">Start by adding your first patch note!</p>
            <a href="add-patch-note.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add First Patch Note
            </a>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($patch_notes as $patch): ?>
            <div class="col-12">
                <div class="patch-note-row">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <div class="patch-note-version"><?= htmlspecialchars($patch['version']) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="patch-note-title"><?= htmlspecialchars($patch['title']) ?></div>
                        </div>
                        <div class="col-md-3">
                            <div class="patch-note-date">
                                <i class="bi bi-calendar3"></i>
                                <?= date('M j, Y', strtotime($patch['release_date'])) ?>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="action-buttons">
                                <a href="../patch-note-detail.php?version=<?= urlencode($patch['version']) ?>" class="btn btn-outline-primary btn-sm" target="_blank">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="edit-patch-note.php?id=<?= $patch['id'] ?>" class="btn btn-outline-warning btn-sm">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDelete(<?= $patch['id'] ?>, '<?= htmlspecialchars($patch['version']) ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="background: var(--card-bg); border: 1px solid rgba(127, 0, 255, 0.1);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(127, 0, 255, 0.1);">
                <h5 class="modal-title" style="color: white;">Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="color: #ccc;">
                Are you sure you want to delete patch note <strong id="deleteVersion"></strong>? This action cannot be undone.
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(127, 0, 255, 0.1);">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="delete_id" id="deleteId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, version) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteVersion').textContent = version;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
</body>
</html> 