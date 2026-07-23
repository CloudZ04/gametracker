<?php
// Include database connection and start session
require_once '../includes/db.php';
session_start();

// Security check: Ensure user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../explore.php');
    exit();
}

// Get game ID from URL parameter
$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: manage-games.php');
    exit();
}

// Fetch game details from database
$stmt = $conn->prepare("SELECT * FROM games WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$game = $result->fetch_assoc();

// Redirect if game not found
if (!$game) {
    header('Location: manage-games.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize input data
    $title = $conn->real_escape_string($_POST['title']);
    $release_date_input = $_POST['release_date'];
    $release_year = $_POST['release_year'];
    $platforms = $conn->real_escape_string($_POST['platforms']);
    $genre = $conn->real_escape_string($_POST['genre']);
    $description = $conn->real_escape_string($_POST['description'] ?? '');
    $image_url = '';
    $portrait_image_url = '';

    // Handle main image upload
    if (!empty($_FILES['image_upload']['name'])) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        $filename = basename($_FILES['image_upload']['name']);
        $target_file = $target_dir . time() . "_" . $filename;
        if (move_uploaded_file($_FILES['image_upload']['tmp_name'], $target_file)) {
            $image_url = $conn->real_escape_string($target_file);
        } else {
            $error = "❌ Failed to upload image.";
        }
    } elseif (!empty($_POST['image_url'])) {
        $image_url = $conn->real_escape_string($_POST['image_url']);
    } else {
        $image_url = $game['image_url'];
    }

    // Handle portrait image upload
    if (!empty($_FILES['portrait_upload']['name'])) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        $filename = basename($_FILES['portrait_upload']['name']);
        $target_file = $target_dir . time() . "_portrait_" . $filename;
        if (move_uploaded_file($_FILES['portrait_upload']['tmp_name'], $target_file)) {
            $portrait_image_url = $conn->real_escape_string($target_file);
        } else {
            $error = "❌ Failed to upload portrait image.";
        }
    } elseif (!empty($_POST['portrait_image_url'])) {
        $portrait_image_url = $conn->real_escape_string($_POST['portrait_image_url']);
    } else {
        $portrait_image_url = $game['portrait_image_url'];
    }

    // Handle TBA (To Be Announced) status
    $is_tba = 0;
    $tba_year = null;

    if (empty($release_date_input) && !empty($release_year) && preg_match('/^\d{4}$/', $release_year)) {
        $is_tba = 1;
        $tba_year = (int)$release_year;
        $release_date = null;
    } else {
        $release_date = $release_date_input;
        $tba_year = null;
        $is_tba = 0;
    }

    // Update game in database if no errors
    if (empty($error)) {
        $sql = "UPDATE games 
                SET title = ?, release_date = ?, tba_year = ?, is_tba = ?, platforms = ?, genre = ?, image_url = ?, portrait_image_url = ?, description = ?
                WHERE id = ?";

        $update = $conn->prepare($sql);
        $update->bind_param("ssiisssssi",
            $title,
            $release_date,
            $tba_year,
            $is_tba,
            $platforms,
            $genre,
            $image_url,
            $portrait_image_url,
            $description,
            $id
        );

        if ($update->execute()) {
            $success = "✅ Game updated successfully!";
            // Update local game data for display
            $game = [
                'id' => $id,
                'title' => $title,
                'release_date' => $release_date,
                'tba_year' => $tba_year,
                'is_tba' => $is_tba,
                'platforms' => $platforms,
                'genre' => $genre,
                'image_url' => $image_url,
                'portrait_image_url' => $portrait_image_url,
                'description' => $description
            ];
        } else {
            $error = "❌ Failed to update game.";
        }
    }
}

// Format description for display
$desc = str_replace(["\\r\\n", "\\n", "\\r", "\r\n"], "\n", $game['description']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Game - GameTracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/styles.css">
    <script src="https://cdn.tiny.cloud/1/pyoz0tmz9ckkdx3jg0ix289lh7fbecut58fsjqxd53d2ge90/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
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

        /* Form container styling */
        .form-container {
            background: #1e1e2f;
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            padding: 2rem;
        }

        .form-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(127, 0, 255, 0.3);
            border-color: rgba(127, 0, 255, 0.3);
        }

        .form-container h2 {
            font-family: 'Orbitron', sans-serif;
            color: var(--primary-color);
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        /* Form styling */
        .form-label {
            color: #ffffff;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-control {
            background: rgba(30, 30, 47, 0.8);
            border: 1px solid rgba(127, 0, 255, 0.2);
            color: #ffffff;
            border-radius: 8px;
            padding: 0.75rem;
        }

        .form-control:focus {
            background: rgba(30, 30, 47, 0.9);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(127, 0, 255, 0.25);
            color: #ffffff;
        }

        .form-control::placeholder {
            color: #a8a8b3;
        }

        /* Description textarea and TinyMCE styling */
        textarea[name="description"] {
            cursor: text;
            min-height: 120px;
        }

        /* TinyMCE editor styling */
        .tox .tox-edit-area__iframe {
            cursor: text !important;
        }

        .tox-tinymce {
            border: 1px solid rgba(127, 0, 255, 0.2) !important;
            border-radius: 8px !important;
            overflow: hidden;
        }

        .tox .tox-editor-header {
            background: rgba(30, 30, 47, 0.9) !important;
            border-bottom: 1px solid rgba(127, 0, 255, 0.2) !important;
        }

        .tox:not(.tox-tinymce-inline) .tox-editor-header {
            box-shadow: none !important;
        }

        /* Preview images */
        .preview-container {
            background: rgba(30, 30, 47, 0.5);
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            min-height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .preview-container img {
            max-height: 200px;
            border-radius: 8px;
        }

        .preview-placeholder {
            color: #a8a8b3;
            font-style: italic;
        }

        /* Alert styling */
        .alert {
            border-radius: 8px;
            border: none;
        }

        .alert-success {
            background: rgba(0, 255, 153, 0.1);
            color: #00ff99;
            border: 1px solid rgba(0, 255, 153, 0.3);
        }

        .alert-danger {
            background: rgba(255, 0, 0, 0.1);
            color: #ff6b6b;
            border: 1px solid rgba(255, 0, 0, 0.3);
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .hero-section {
                padding: 4rem 0 2rem;
            }

            .hero-section h1 {
                font-size: 2.5rem;
            }

            .form-container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
    <script>
        tinymce.init({
            selector: 'textarea[name="description"]',
            height: 300,
            menubar: false,
            plugins: 'link lists',
            toolbar: 'undo redo | bold italic underline | bullist numlist | link | removeformat',
            content_style: "body { font-family:Exo 2,sans-serif; font-size:14px; background: #1e1e2f; color: #ffffff; } body::placeholder { color: #a8a8b3 !important; } p { color: #ffffff; } .mce-content-body[data-mce-placeholder]:not(.mce-visualblocks)::before { color: #a8a8b3 !important; opacity: 1 !important; }",
            branding: false,
            skin: 'oxide-dark',
            content_css: 'dark',
            placeholder: 'Enter game description...'
        });
    </script>
</head>

<body>

<?php include '../includes/nav.php'; ?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto text-center">
                <h1 class="mb-3"><i class="ph ph-pencil-simple me-3"></i>Edit Game</h1>
                <p class="lead">Update game details and information for "<?= htmlspecialchars($game['title']) ?>" in the GameTracker database.</p>
            </div>
        </div>
    </div>
</section>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="form-container">
                <h2><i class="ph ph-game-controller me-2"></i>Game Details</h2>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="ph ph-check-circle me-2"></i><?= $success ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="ph ph-warning-circle me-2"></i><?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label"><i class="ph ph-text-aa me-2"></i>Game Title</label>
                        <input type="text" name="title" class="form-control" placeholder="Enter game title" value="<?= htmlspecialchars($game['title']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="ph ph-calendar me-2"></i>Release Date (Full)</label>
                        <input type="date" name="release_date" class="form-control" value="<?= htmlspecialchars($game['release_date']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="ph ph-calendar-blank me-2"></i>OR Release Year (e.g. 2025)</label>
                        <input type="number" name="release_year" min="2024" max="2100" class="form-control" placeholder="2025" value="<?= htmlspecialchars($game['tba_year'] ?? substr($game['release_date'], 0, 4)) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="ph ph-device-mobile me-2"></i>Platforms</label>
                        <input type="text" name="platforms" class="form-control" placeholder="PC, PlayStation 5, Xbox Series X/S" value="<?= htmlspecialchars($game['platforms']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="ph ph-tag me-2"></i>Genres</label>
                        <input type="text" name="genre" class="form-control" placeholder="Action, Adventure, RPG" value="<?= htmlspecialchars($game['genre']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="ph ph-link me-2"></i>Image URL (optional)</label>
                        <input type="text" id="image_url" name="image_url" class="form-control" placeholder="https://example.com/image.jpg" value="<?= htmlspecialchars($game['image_url']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="ph ph-upload me-2"></i>Or Upload a New Image</label>
                        <input type="file" id="image_upload" name="image_upload" class="form-control" accept="image/*">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Main Image Preview</label>
                        <div class="preview-container">
                            <?php if (!empty($game['image_url'])): ?>
                                <img id="previewImage" src="<?= htmlspecialchars($game['image_url']) ?>" alt="Preview">
                                <span id="previewPlaceholder" class="preview-placeholder d-none">Image preview will appear here</span>
                            <?php else: ?>
                                <img id="previewImage" class="d-none" alt="Preview will appear here">
                                <span id="previewPlaceholder" class="preview-placeholder">Image preview will appear here</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="ph ph-link me-2"></i>Portrait Image URL (optional)</label>
                        <input type="text" id="portrait_url" name="portrait_image_url" class="form-control" placeholder="https://example.com/portrait.jpg" value="<?= htmlspecialchars($game['portrait_image_url']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="ph ph-upload me-2"></i>Or Upload a New Portrait Image</label>
                        <input type="file" id="portrait_upload" name="portrait_upload" class="form-control" accept="image/*">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Portrait Image Preview</label>
                        <div class="preview-container">
                            <?php if (!empty($game['portrait_image_url'])): ?>
                                <img id="previewPortrait" src="<?= htmlspecialchars($game['portrait_image_url']) ?>" alt="Portrait preview">
                                <span id="portraitPlaceholder" class="preview-placeholder d-none">Portrait preview will appear here</span>
                            <?php else: ?>
                                <img id="previewPortrait" class="d-none" alt="Portrait preview">
                                <span id="portraitPlaceholder" class="preview-placeholder">Portrait preview will appear here</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label"><i class="ph ph-article me-2"></i>Description</label>
                        <textarea name="description" class="form-control" placeholder="Enter game description..."><?= htmlspecialchars($desc) ?></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="ph ph-floppy-disk me-2"></i>Update Game
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
const preview = document.getElementById('previewImage');
const previewPlaceholder = document.getElementById('previewPlaceholder');
const fileInput = document.getElementById('image_upload');
const urlInput = document.getElementById('image_url');
const portraitPreview = document.getElementById('previewPortrait');
const portraitPlaceholder = document.getElementById('portraitPlaceholder');
const portraitFile = document.getElementById('portrait_upload');
const portraitUrl = document.getElementById('portrait_url');

function showMainPreview(src) {
    if (src) {
        preview.src = src;
        preview.classList.remove('d-none');
        previewPlaceholder.classList.add('d-none');
    } else {
        preview.classList.add('d-none');
        previewPlaceholder.classList.remove('d-none');
    }
}

function showPortraitPreview(src) {
    if (src) {
        portraitPreview.src = src;
        portraitPreview.classList.remove('d-none');
        portraitPlaceholder.classList.add('d-none');
    } else {
        portraitPreview.classList.add('d-none');
        portraitPlaceholder.classList.remove('d-none');
    }
}

fileInput.addEventListener('change', function () {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = e => showMainPreview(e.target.result);
        reader.readAsDataURL(file);
    } else {
        showMainPreview('');
    }
});

urlInput.addEventListener('input', function () {
    showMainPreview(this.value.trim());
});

portraitFile.addEventListener('change', function () {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = e => showPortraitPreview(e.target.result);
        reader.readAsDataURL(file);
    } else {
        showPortraitPreview('');
    }
});

portraitUrl.addEventListener('input', function () {
    showPortraitPreview(this.value.trim());
});
</script>
</body>
</html>

<?php $conn->close(); ?>
