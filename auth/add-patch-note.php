<?php
require_once '../includes/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $version = trim($_POST['version'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $release_date = $_POST['release_date'] ?? '';
    $content = trim($_POST['content'] ?? '');
    
    // Validate inputs
    if (empty($version) || empty($title) || empty($release_date) || empty($content)) {
        $error = 'All fields are required.';
    } else {
        // Check if version already exists
        $check_stmt = $conn->prepare("SELECT id FROM patch_notes WHERE version = ?");
        $check_stmt->bind_param("s", $version);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error = 'A patch note with this version already exists.';
        } else {
            // Handle image upload
            $image_url = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../images/patch-notes/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $filename = 'patch_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
                        $image_url = 'images/patch-notes/' . $filename;
                    } else {
                        $error = 'Failed to upload image.';
                    }
                } else {
                    $error = 'Invalid image format. Allowed: JPG, PNG, GIF, WEBP';
                }
            }
            
            if (empty($error)) {
                // Insert patch note
                $stmt = $conn->prepare("INSERT INTO patch_notes (version, title, release_date, image_url, content) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $version, $title, $release_date, $image_url, $content);
                
                if ($stmt->execute()) {
                    $message = 'Patch note added successfully!';
                    // Clear form
                    $version = $title = $release_date = $content = '';
                } else {
                    $error = 'Failed to add patch note.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Patch Note | GameTracker.gg</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/styles.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-card {
            background: var(--card-bg);
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            padding: 2rem;
        }
        
        .form-label {
            color: white;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            background: rgba(30, 30, 47, 0.5);
            border: 1px solid rgba(127, 0, 255, 0.3);
            color: white;
            border-radius: 8px;
        }
        
        .form-control:focus, .form-select:focus {
            background: rgba(30, 30, 47, 0.8);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(127, 0, 255, 0.25);
            color: white;
        }
        
        .form-control::placeholder {
            color: #888;
        }
        
        textarea.form-control {
            min-height: 200px;
            resize: vertical;
        }
        
        .version-helper {
            font-size: 0.9rem;
            color: #888;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
<?php include '../includes/nav.php'; ?>

<div class="container my-5">
    <div class="form-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 style="font-family: 'Orbitron', sans-serif;">
                <span style="color: var(--primary-color);">Add</span> Patch Note
            </h2>
            <a href="manage-patch-notes.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Manage
            </a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="version" class="form-label">Version *</label>
                        <input type="text" class="form-control" id="version" name="version" value="<?= htmlspecialchars($version ?? '') ?>" required>
                        <div class="version-helper">
                            Format: 0.1.0, 0.1.1, 0.2.0, etc.
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="release_date" class="form-label">Release Date *</label>
                        <input type="date" class="form-control" id="release_date" name="release_date" value="<?= htmlspecialchars($release_date ?? '') ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="title" class="form-label">Title *</label>
                    <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($title ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label for="image" class="form-label">Image (Optional)</label>
                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                    <div class="version-helper">
                        Recommended: 800x400px, JPG, PNG, GIF, or WEBP
                    </div>
                </div>

                <div class="mb-3">
                    <label for="content" class="form-label">Content *</label>
                    <textarea class="form-control" id="content" name="content" required rows="15"><?= htmlspecialchars($content ?? '') ?></textarea>
                    <div class="version-helper">
                        Describe the changes, features, and improvements in this version. Supports Markdown formatting.
                    </div>
                    
                    <!-- Markdown Help -->
                    <div class="mt-3">
                        <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="collapse" data-bs-target="#markdownHelp">
                            <i class="bi bi-question-circle"></i> Markdown Help
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm ms-2" onclick="previewContent()">
                            <i class="bi bi-eye"></i> Preview
                        </button>
                    </div>
                    
                    <div class="collapse mt-3" id="markdownHelp">
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
                    
                    <!-- Preview Modal -->
                    <div class="modal fade" id="previewModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content" style="background: var(--card-bg); border: 1px solid rgba(127, 0, 255, 0.1);">
                                <div class="modal-header" style="border-bottom: 1px solid rgba(127, 0, 255, 0.1);">
                                    <h5 class="modal-title" style="color: white;">Content Preview</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body" id="previewContent" style="color: #ccc;">
                                    <!-- Preview content will be inserted here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Patch Note
                    </button>
                    <a href="manage-patch-notes.php" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function previewContent() {
    const content = document.getElementById('content').value;
    const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
    
    // Send content to server for parsing
    fetch('preview-markdown.php', {
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
</script>
</body>
</html> 