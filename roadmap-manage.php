<?php
// Include database connection and start session
require_once 'includes/db.php';
session_start();

// Security check: Only allow admin users to access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: roadmap.php');
    exit();
}

// Get action and ID from GET or POST request
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$id = $_GET['id'] ?? $_POST['id'] ?? null;

// Handle POST requests for different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toggle completion status of a roadmap item
    if ($action === 'toggle' && $id) {
        $stmt = $conn->prepare("UPDATE roadmap SET is_completed = NOT is_completed WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        header("Location: roadmap.php");
        exit();
    }

    // Delete a roadmap item
    if ($action === 'delete' && $id) {
        $stmt = $conn->prepare("DELETE FROM roadmap WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        header("Location: roadmap.php");
        exit();
    }

    // Add new or edit existing roadmap item
    if ($action === 'add' || $action === 'edit') {
        // Sanitize input data
        $feature = $conn->real_escape_string($_POST['feature']);
        $phase = $conn->real_escape_string($_POST['phase']);
        $description = $conn->real_escape_string($_POST['description'] ?? '');
        $is_completed = isset($_POST['is_completed']) ? 1 : 0;
    
        // Insert new roadmap item
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO roadmap (feature, phase, is_completed, description) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssis", $feature, $phase, $is_completed, $description);
            $stmt->execute();
        } 
        // Update existing roadmap item
        elseif ($action === 'edit' && $id) {
            $stmt = $conn->prepare("UPDATE roadmap SET feature = ?, phase = ?, is_completed = ?, description = ? WHERE id = ?");
            $stmt->bind_param("ssisi", $feature, $phase, $is_completed, $description, $id);
            $stmt->execute();
        }
    
        header("Location: roadmap.php");
        exit();
    }
}

// Fetch existing roadmap item data for edit form
$item = null;
if ($action === 'edit' && $id) {
    $stmt = $conn->prepare("SELECT * FROM roadmap WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $action === 'edit' ? 'Edit' : 'Add' ?> Roadmap Item</title>
    <!-- Include required CSS and fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* Custom styling for the page */
        body {
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            color: #fff;
            font-family: 'Orbitron', sans-serif;
        }
        .form-container {
            background: #1e1e2f;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 0 20px #b200ff;
            max-width: 600px;
            margin: 3rem auto;
            font-family: 'Exo 2', sans-serif;
        }
    </style>
</head>
<body>

<?php include 'includes/nav.php'; ?>

<!-- Form container for adding/editing roadmap items -->
<div class="form-container">
    <h2 class="text-center mb-4"><?= $action === 'edit' ? '✏️ Edit Feature' : '➕ Add New Feature' ?></h2>

    <form method="POST" action="">
        <!-- Hidden fields for action and ID -->
        <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
        <?php if ($id): ?>
            <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
        <?php endif; ?>

        <!-- Feature name input -->
        <div class="mb-3">
            <label class="form-label">Feature</label>
            <input type="text" name="feature" class="form-control" required value="<?= htmlspecialchars($item['feature'] ?? '') ?>">
        </div>

        <!-- Optional description textarea -->
        <div class="mb-3">
            <label class="form-label">Optional Description</label>
            <textarea name="description" class="form-control"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
        </div>

        <!-- Phase selection dropdown -->
        <div class="mb-3">
            <label class="form-label">Phase</label>
            <select name="phase" class="form-select">
                <option value="Phase 1" <?= ($item['phase'] ?? '') === 'Phase 1' ? 'selected' : '' ?>>Phase 1</option>
                <option value="Phase 2" <?= ($item['phase'] ?? '') === 'Phase 2' ? 'selected' : '' ?>>Phase 2</option>
                <option value="Phase 3" <?= ($item['phase'] ?? '') === 'Phase 3' ? 'selected' : '' ?>>Phase 3</option>
                <option value="Phase 4" <?= ($item['phase'] ?? '') === 'Phase 4' ? 'selected' : '' ?>>Phase 4</option>
                <option value="Phase 5" <?= ($item['phase'] ?? '') === 'Phase 5' ? 'selected' : '' ?>>Phase 5</option>
            </select>
        </div>

        <!-- Completion status checkbox -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_completed" id="is_completed" <?= !empty($item['is_completed']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_completed">Mark as completed</label>
        </div>

        <!-- Submit button -->
        <div class="d-grid">
            <button type="submit" class="btn btn-success"><?= $action === 'edit' ? 'Update' : 'Add' ?> Feature</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
