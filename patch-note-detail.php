<?php
require_once 'includes/db.php';
require_once 'parsedown/Parsedown.php';
session_start();

$version = $_GET['version'] ?? '';

if (empty($version)) {
    header('Location: patch-notes.php');
    exit();
}

// Get the specific patch note
$stmt = $conn->prepare("SELECT * FROM patch_notes WHERE version = ?");
$stmt->bind_param("s", $version);
$stmt->execute();
$result = $stmt->get_result();
$patch_note = $result->fetch_assoc();

if (!$patch_note) {
    header('Location: patch-notes.php');
    exit();
}

// Get previous and next patch notes for navigation
$prev_stmt = $conn->prepare("SELECT version, title FROM patch_notes WHERE release_date > ? ORDER BY release_date ASC LIMIT 1");
$prev_stmt->bind_param("s", $patch_note['release_date']);
$prev_stmt->execute();
$prev_patch = $prev_stmt->get_result()->fetch_assoc();

$next_stmt = $conn->prepare("SELECT version, title FROM patch_notes WHERE release_date < ? ORDER BY release_date DESC LIMIT 1");
$next_stmt->bind_param("s", $patch_note['release_date']);
$next_stmt->execute();
$next_patch = $next_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($patch_note['version']) ?> - <?= htmlspecialchars($patch_note['title']) ?> | GameTracker.gg</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="includes/styles.css">
    <style>
        .patch-note-header {
            background: linear-gradient(135deg, #1e1e2f 0%, #2d2d44 100%);
            padding: 3rem 0;
            position: relative;
        }

        .patch-note-content {
            background: var(--card-bg);
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            padding: 2rem;
            margin: 2rem 0;
        }

        .patch-note-version {
            font-family: 'Orbitron', sans-serif;
            font-size: 2rem;
            color: var(--primary-color);
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .patch-note-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
            margin-bottom: 1rem;
        }

        .patch-note-date {
            font-size: 1rem;
            color: #888;
            margin-bottom: 2rem;
        }

        .patch-note-banner {
            position: relative;
            width: 100%;
            min-height: 260px;
            margin-bottom: 2rem;
            padding: 1.1rem;
            background:
                radial-gradient(circle at 16% 12%, rgba(178, 0, 255, 0.28), transparent 45%),
                radial-gradient(circle at 90% 90%, rgba(90, 40, 255, 0.2), transparent 42%),
                linear-gradient(145deg, #121226 0%, #1b1b35 55%, #171733 100%);
            border-radius: 10px;
            border: 1px solid rgba(178, 0, 255, 0.32);
            overflow: hidden;
        }

        .patch-note-banner-layer {
            position: absolute;
            inset: 18px;
            border: 1px solid rgba(215, 125, 255, 0.45);
            border-radius: 10px;
            pointer-events: none;
            box-shadow: inset 0 0 12px rgba(198, 86, 255, 0.15);
        }

        .patch-note-banner::before {
            content: "";
            position: absolute;
            inset: 12px;
            border: 2px solid rgba(186, 32, 255, 0.95);
            border-radius: 11px;
            box-shadow: 0 0 24px rgba(178, 0, 255, 0.36), inset 0 0 16px rgba(178, 0, 255, 0.12);
            pointer-events: none;
        }

        .patch-note-banner::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, transparent, rgba(190, 70, 255, 0.88), transparent) top center / 180px 2px no-repeat,
                linear-gradient(90deg, transparent, rgba(160, 70, 255, 0.68), transparent) bottom center / 140px 1px no-repeat;
            opacity: 0.78;
            pointer-events: none;
        }

        .patch-note-banner-notch {
            position: absolute;
            top: 50%;
            width: 12px;
            height: 56px;
            transform: translateY(-50%);
            border-top: 2px solid rgba(190, 70, 255, 0.85);
            border-bottom: 2px solid rgba(190, 70, 255, 0.85);
            pointer-events: none;
            opacity: 0.9;
        }

        .patch-note-banner-notch.left {
            left: 12px;
            border-left: 2px solid rgba(190, 70, 255, 0.85);
            border-radius: 7px 0 0 7px;
        }

        .patch-note-banner-notch.right {
            right: 12px;
            border-right: 2px solid rgba(190, 70, 255, 0.85);
            border-radius: 0 7px 7px 0;
        }

        .patch-note-banner-inner {
            position: relative;
            z-index: 1;
            min-height: 222px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 0.45rem;
            padding: 1.2rem 1.4rem;
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
            font-size: 1.16rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.8);
        }

        .patch-note-banner-version {
            margin: 0;
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(2.6rem, 5.6vw, 3.7rem);
            letter-spacing: 0.08em;
            line-height: 1.08;
            color: #cc46ff;
            -webkit-text-stroke: 1px rgba(245, 220, 255, 0.18);
            text-shadow: 0 0 22px rgba(178, 0, 255, 0.45), 0 0 3px rgba(255, 255, 255, 0.25);
        }

        .patch-note-banner-title {
            margin: 0;
            font-family: 'Exo 2', sans-serif;
            font-size: 1.2rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: rgba(255, 255, 255, 0.88);
            text-wrap: balance;
            max-width: 700px;
        }

        .patch-note-text {
            line-height: 1.8;
            color: #ccc;
            font-size: 1.1rem;
        }

        .patch-note-text h1,
        .patch-note-text h2,
        .patch-note-text h3,
        .patch-note-text h4,
        .patch-note-text h5,
        .patch-note-text h6 {
            color: white;
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .patch-note-text h1 {
            font-size: 2rem;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
        }

        .patch-note-text h2 {
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .patch-note-text h3 {
            font-size: 1.3rem;
        }

        .patch-note-text p {
            margin-bottom: 1rem;
        }

        .patch-note-text ul,
        .patch-note-text ol {
            margin-bottom: 1rem;
            padding-left: 2rem;
        }

        .patch-note-text li {
            margin-bottom: 0.5rem;
        }

        .patch-note-text strong {
            color: white;
            font-weight: 600;
        }

        .patch-note-text em {
            color: #aaa;
        }

        .patch-note-text code {
            background: rgba(127, 0, 255, 0.1);
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: var(--primary-color);
        }

        .patch-note-text pre {
            background: rgba(30, 30, 47, 0.8);
            border: 1px solid rgba(127, 0, 255, 0.2);
            border-radius: 8px;
            padding: 1rem;
            overflow-x: auto;
            margin: 1rem 0;
        }

        .patch-note-text pre code {
            background: none;
            padding: 0;
            color: #ccc;
        }

        .patch-note-text blockquote {
            border-left: 4px solid var(--primary-color);
            padding-left: 1rem;
            margin: 1rem 0;
            color: #aaa;
            font-style: italic;
        }

        .patch-note-text a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .patch-note-text a:hover {
            text-decoration: underline;
        }

        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }

        .nav-btn {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-btn:hover {
            background: var(--primary-color);
            color: white;
            text-decoration: none;
        }

        .nav-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .back-btn {
            background: transparent;
            border: 2px solid #6c757d;
            color: #6c757d;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .back-btn:hover {
            background: #6c757d;
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
<?php include 'includes/nav.php'; ?>

<!-- Patch Note Header -->
<section class="patch-note-header">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <div class="patch-note-version"><?= htmlspecialchars($patch_note['version']) ?></div>
                <div class="patch-note-title"><?= htmlspecialchars($patch_note['title']) ?></div>
                <div class="patch-note-date">
                    <i class="bi bi-calendar3"></i>
                    <?= date('F j, Y', strtotime($patch_note['release_date'])) ?>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container my-5">
    <a href="patch-notes.php" class="back-btn">
        <i class="bi bi-arrow-left"></i>
        Back to Patch Notes
    </a>

    <div class="patch-note-content">
        <div class="patch-note-banner" aria-label="Patch note visual for <?= htmlspecialchars($patch_note['version']) ?>">
            <div class="patch-note-banner-layer"></div>
            <span class="patch-note-banner-notch left"></span>
            <span class="patch-note-banner-notch right"></span>
            <div class="patch-note-banner-inner">
                <p class="patch-note-banner-label">Patch Notes</p>
                <p class="patch-note-banner-version"><?= htmlspecialchars($patch_note['version']) ?></p>
                <p class="patch-note-banner-title"><?= htmlspecialchars($patch_note['title']) ?></p>
            </div>
        </div>

        <div class="patch-note-text">
            <?php
            $parsedown = new Parsedown();
            $parsedown->setSafeMode(true);
            echo $parsedown->text($patch_note['content']);
            ?>
        </div>
    </div>

    <!-- Navigation -->
    <div class="navigation-buttons">
        <?php if ($prev_patch): ?>
            <a href="patch-note-detail.php?version=<?= urlencode($prev_patch['version']) ?>" class="nav-btn">
                <i class="bi bi-arrow-left"></i>
                <?= htmlspecialchars($prev_patch['version']) ?>
            </a>
        <?php else: ?>
            <span class="nav-btn disabled">
                <i class="bi bi-arrow-left"></i>
                Previous
            </span>
        <?php endif; ?>

        <?php if ($next_patch): ?>
            <a href="patch-note-detail.php?version=<?= urlencode($next_patch['version']) ?>" class="nav-btn">
                <?= htmlspecialchars($next_patch['version']) ?>
                <i class="bi bi-arrow-right"></i>
            </a>
        <?php else: ?>
            <span class="nav-btn disabled">
                Next
                <i class="bi bi-arrow-right"></i>
            </span>
        <?php endif; ?>
    </div>
</div>

</body>
</html> 