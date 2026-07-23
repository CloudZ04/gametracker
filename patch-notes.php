<?php
require_once 'includes/db.php';
session_start();

// Get all patch notes ordered by release date (newest first)
$query = "SELECT id, version, title, release_date, image_url FROM patch_notes ORDER BY release_date DESC";
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
    <title>Patch Notes | GameTracker.gg</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="includes/styles.css">
    <style>
        .patch-note-card {
            background: var(--card-bg); 
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .patch-note-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary-color);
            box-shadow: 0 4px 20px rgba(127, 0, 255, 0.2);
        }

        .patch-note-banner {
            position: relative;
            width: 100%;
            min-height: 210px;
            padding: 1rem;
            background:
                radial-gradient(circle at 18% 10%, rgba(178, 0, 255, 0.28), transparent 45%),
                radial-gradient(circle at 90% 92%, rgba(110, 45, 255, 0.2), transparent 40%),
                linear-gradient(145deg, #121226 0%, #1b1b35 55%, #171733 100%);
            border-bottom: 1px solid rgba(178, 0, 255, 0.35);
            overflow: hidden;
        }

        .patch-note-banner-layer {
            position: absolute;
            inset: 16px;
            border: 1px solid rgba(215, 125, 255, 0.45);
            border-radius: 8px;
            pointer-events: none;
            box-shadow: inset 0 0 12px rgba(198, 86, 255, 0.15);
        }

        .patch-note-banner::before {
            content: "";
            position: absolute;
            inset: 10px;
            border: 2px solid rgba(186, 32, 255, 0.95);
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(178, 0, 255, 0.35), inset 0 0 16px rgba(178, 0, 255, 0.12);
            pointer-events: none;
        }

        .patch-note-banner::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, transparent, rgba(190, 70, 255, 0.85), transparent) top center / 150px 2px no-repeat,
                linear-gradient(90deg, transparent, rgba(160, 70, 255, 0.65), transparent) bottom center / 120px 1px no-repeat;
            opacity: 0.75;
            pointer-events: none;
        }

        .patch-note-banner-notch {
            position: absolute;
            top: 50%;
            width: 10px;
            height: 46px;
            transform: translateY(-50%);
            border-top: 2px solid rgba(190, 70, 255, 0.85);
            border-bottom: 2px solid rgba(190, 70, 255, 0.85);
            pointer-events: none;
            opacity: 0.85;
        }

        .patch-note-banner-notch.left {
            left: 10px;
            border-left: 2px solid rgba(190, 70, 255, 0.85);
            border-radius: 6px 0 0 6px;
        }

        .patch-note-banner-notch.right {
            right: 10px;
            border-right: 2px solid rgba(190, 70, 255, 0.85);
            border-radius: 0 6px 6px 0;
        }

        .patch-note-banner-inner {
            position: relative;
            z-index: 1;
            min-height: 178px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 0.35rem;
            padding: 1.5rem 1.25rem;
        }

        .patch-note-banner-inner::before {
            content: "";
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(
                to bottom,
                rgba(255, 255, 255, 0.02) 0px,
                rgba(255, 255, 255, 0.02) 1px,
                rgba(255, 255, 255, 0) 3px,
                rgba(255, 255, 255, 0) 6px
            );
            mix-blend-mode: screen;
            opacity: 0.22;
            pointer-events: none;
            animation: patchScan 8s linear infinite;
        }

        @keyframes patchScan {
            0% { transform: translateY(0); }
            100% { transform: translateY(6px); }
        }

        .patch-note-banner-label {
            margin: 0;
            font-family: 'Orbitron', sans-serif;
            font-size: 1.06rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.78);
        }

        .patch-note-banner-version {
            margin: 0;
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(2.35rem, 4.8vw, 3.05rem);
            letter-spacing: 0.06em;
            line-height: 1.1;
            color: #cc46ff;
            -webkit-text-stroke: 1px rgba(245, 220, 255, 0.16);
            text-shadow: 0 0 22px rgba(178, 0, 255, 0.45), 0 0 3px rgba(255, 255, 255, 0.25);
        }

        .patch-note-banner-title {
            margin: 0;
            font-family: 'Exo 2', sans-serif;
            font-size: 1.08rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            color: rgba(255, 255, 255, 0.86);
            text-wrap: balance;
        }

        .patch-note-content {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .patch-note-version {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.1rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-weight: bold;
        }

        .patch-note-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: white;
        }

        .patch-note-date {
            font-size: 0.9rem;
            color: #888;
            margin-top: auto;
        }

        .hero-section {
            position: relative;
            padding: 4rem 0 2rem;
            background: linear-gradient(to right, rgba(21, 21, 30, 0.9), rgba(21, 21, 30, 0.7));
            border-bottom: 1px solid rgba(127, 0, 255, 0.3);
            margin-bottom: 2rem;
        }

        .hero-title {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(2.1rem, 4.6vw, 2.8rem);
            line-height: 1.1;
            margin-bottom: 1rem;
            font-weight: 500;
            color: #fff;
        }

        .hero-title-accent {
            color: #b200ff;
            font-weight: 700;
        }

        .admin-controls {
            background: rgba(127, 0, 255, 0.1);
            border: 1px solid rgba(127, 0, 255, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .admin-controls h5 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
<?php include 'includes/nav.php'; ?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <h1 class="hero-title"><span class="hero-title-accent">Patch</span> <span>Notes</span></h1>
        <p class="text-light">Track the evolution of GameTracker.gg through our development updates.</p>
    </div>
</section>

<div class="container my-5">
    <?php if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
    <!-- Admin Controls -->
    <div class="admin-controls">
        <h5><i class="bi bi-gear"></i> Admin Controls</h5>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPatchNoteModal">
                <i class="bi bi-plus-circle"></i> Add Patch Note
            </button>
            <a href="auth/manage-patch-notes.php" class="btn btn-outline-primary">
                <i class="bi bi-pencil"></i> Manage Patch Notes
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Patch Notes Grid -->
    <div class="row g-4">
        <?php foreach ($patch_notes as $patch): ?>
        <div class="col-lg-4 col-md-6">
            <a href="patch-note-detail.php?version=<?= urlencode($patch['version']) ?>" class="text-decoration-none">
                <div class="patch-note-card">
                    <div class="patch-note-banner" aria-label="Patch note visual for <?= htmlspecialchars($patch['version']) ?>">
                        <div class="patch-note-banner-layer"></div>
                        <span class="patch-note-banner-notch left"></span>
                        <span class="patch-note-banner-notch right"></span>
                        <div class="patch-note-banner-inner">
                            <p class="patch-note-banner-label">Patch Notes</p>
                            <p class="patch-note-banner-version"><?= htmlspecialchars($patch['version']) ?></p>
                            <p class="patch-note-banner-title"><?= htmlspecialchars($patch['title']) ?></p>
                        </div>
                    </div>
                    <div class="patch-note-content">
                        <div class="patch-note-version"><?= htmlspecialchars($patch['version']) ?></div>
                        <div class="patch-note-title"><?= htmlspecialchars($patch['title']) ?></div>
                        <div class="patch-note-date">
                            <i class="bi bi-calendar3"></i>
                            <?= date('F j, Y', strtotime($patch['release_date'])) ?>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($patch_notes)): ?>
    <div class="text-center py-5">
        <i class="bi bi-file-earmark-text" style="font-size: 4rem; color: rgba(127, 0, 255, 0.3);"></i>
        <h4 class="mt-3">No Patch Notes Yet</h4>
        <p class="text-muted">Check back soon for updates!</p>
    </div>
    <?php endif; ?>
</div>

<!-- Add Patch Note Modal -->
<?php if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
<div class="modal fade" id="addPatchNoteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background: linear-gradient(135deg, rgba(30, 30, 47, 0.95), rgba(45, 45, 68, 0.95)); border: 1px solid rgba(127, 0, 255, 0.2); backdrop-filter: blur(10px); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(127, 0, 255, 0.2); background: rgba(127, 0, 255, 0.1);">
                <h5 class="modal-title" style="color: white;">
                    <i class="bi bi-plus-circle"></i> Add New Patch Note
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addPatchNoteForm" enctype="multipart/form-data">
                <div class="modal-body" style="color: #ccc;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="modal_version" class="form-label">Version *</label>
                            <input type="text" class="form-control" id="modal_version" name="version" required>
                                                         <div class="form-text" style="color: #aaa;">Format: 0.1.0, 0.1.1, 0.2.0, etc.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="modal_release_date" class="form-label">Release Date *</label>
                            <input type="date" class="form-control" id="modal_release_date" name="release_date" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_title" class="form-label">Title *</label>
                        <input type="text" class="form-control" id="modal_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_image" class="form-label">Image (Optional)</label>
                        <input type="file" class="form-control" id="modal_image" name="image" accept="image/*">
                                                 <div class="form-text" style="color: #aaa;">Recommended: 800x400px, JPG, PNG, GIF, or WEBP</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_content" class="form-label">Content *</label>
                        <textarea class="form-control" id="modal_content" name="content" required rows="10"></textarea>
                                                 <div class="form-text" style="color: #aaa;">Supports Markdown formatting. Use # for headings, ** for bold, etc.</div>
                        
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="collapse" data-bs-target="#modalMarkdownHelp">
                                <i class="bi bi-question-circle"></i> Markdown Help
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm ms-2" onclick="previewModalContent()">
                                <i class="bi bi-eye"></i> Preview
                            </button>
                        </div>
                        
                        <div class="collapse mt-2" id="modalMarkdownHelp">
                            <div class="card" style="background: rgba(30, 30, 47, 0.8); border: 1px solid rgba(127, 0, 255, 0.2);">
                                <div class="card-body">
                                    <h6 class="text-white">Markdown Formatting:</h6>
                                    <ul class="text-muted small">
                                        <li><code># Heading 1</code> - Large heading</li>
                                        <li><code>## Heading 2</code> - Medium heading</li>
                                        <li><code>### Heading 3</code> - Small heading</li>
                                        <li><code>**bold text**</code> - Bold text</li>
                                        <li><code>*italic text*</code> - Italic text</li>
                                        <li><code>`code`</code> - Inline code</li>
                                        <li><code>- item</code> - Bullet list</li>
                                        <li><code>1. item</code> - Numbered list</li>
                                        <li><code>> quote</code> - Blockquote</li>
                                        <li><code>[link text](url)</code> - Links</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(127, 0, 255, 0.1);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Patch Note
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background: linear-gradient(135deg, rgba(30, 30, 47, 0.95), rgba(45, 45, 68, 0.95)); border: 1px solid rgba(127, 0, 255, 0.2); backdrop-filter: blur(10px); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(127, 0, 255, 0.2); background: rgba(127, 0, 255, 0.1);">
                <h5 class="modal-title" style="color: white;">Content Preview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="previewContent" style="color: #ccc;">
                <!-- Preview content will be inserted here -->
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
<?php if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
// Handle form submission
document.getElementById('addPatchNoteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('auth/add-patch-note-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Patch note added successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('addPatchNoteModal')).hide();
            location.reload();
        } else {
            showToast(data.message || 'Failed to add patch note', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while adding the patch note', 'error');
    });
});

function previewModalContent() {
    const content = document.getElementById('modal_content').value;
    const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
    
    // Send content to server for parsing
    fetch('auth/preview-markdown.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ content: content })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('previewContent').innerHTML = data.html;
            previewModal.show();
        } else {
            showToast('Failed to generate preview', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error generating preview', 'error');
    });
}
<?php endif; ?>
</script>
</body>
</html> 