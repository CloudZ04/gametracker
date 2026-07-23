<?php
require_once 'includes/db.php';
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - GameTracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="includes/styles.css">
    <style>
        .support-wrap {
            max-width: 900px;
            margin: 0 auto;
            padding: 7rem 1rem 3rem;
        }

        .support-card {
            background: rgba(30, 30, 47, 0.75);
            border: 1px solid rgba(127, 0, 255, 0.25);
            border-radius: 16px;
            padding: 2rem;
        }

        .support-title {
            font-family: 'Orbitron', sans-serif;
            color: #b200ff;
        }

        .support-actions a {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
<?php include 'includes/nav.php'; ?>

<main class="support-wrap">
    <div class="support-card">
        <h1 class="support-title mb-3"><i class="bi bi-life-preserver me-2"></i>Support</h1>
        <p class="mb-3">
            This support page is now active as a placeholder while the full support workflow is being finalized.
        </p>
        <p class="mb-4">
            For now, include as much detail as possible in your report (username, affected page, and what happened), and it will be reviewed manually.
        </p>

        <div class="alert alert-info" role="alert">
            <strong>Current status:</strong> Full ticket submission is coming soon.
        </div>

        <div class="support-actions mt-4">
            <a href="faq.php" class="btn btn-outline-light"><i class="bi bi-question-circle me-1"></i>Read FAQ</a>
            <a href="terms.php" class="btn btn-outline-light"><i class="bi bi-file-text me-1"></i>Terms</a>
            <a href="privacy-policy.php" class="btn btn-outline-light"><i class="bi bi-shield-check me-1"></i>Privacy Policy</a>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
</body>
</html>
