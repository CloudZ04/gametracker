<?php
// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database connection
require_once '../includes/db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fetching Games...</title>
    <!-- External CSS dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            font-family: 'Orbitron', sans-serif;
            color: #ccc;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 2rem;
        }
        .message-box {
            background: rgba(30, 30, 47, 0.9);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px #b200ff;
            animation: bounce 1s ease;
            max-width: 600px;
        }
        .message-box h1 {
            margin-bottom: 1rem;
        }
        @keyframes bounce {
            0%   { transform: scale(0.8); opacity: 0; }
            50%  { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(1); }
        }
        .spinner-border {
            width: 4rem;
            height: 4rem;
        }
        #tick {
            font-size: 4rem;
            color: #9b59b6;
            display: none;
            animation: pop 0.5s ease;
        }
        @keyframes pop {
            0% { transform: scale(0.5); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>

<!-- Main container for status messages and loading indicators -->
<div class="message-box">
    <h1 id="status-message">⌛ Fetching Games...</h1>

    <!-- Loading section with spinner and success indicator -->
    <div class="mt-3" id="loading-section">
        <!-- Loading spinner -->
        <div id="spinner" class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <!-- Success checkmark (hidden by default) -->
        <div id="tick">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 6L9 17L4 12" stroke="#9b59b6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>

        <!-- Status message and navigation button -->
        <p class="mt-3" id="result-message">Please wait...</p>
        <a href="explore.php" class="btn btn-primary mt-3" id="goHomeBtn" style="display:none;">Go to Home</a>
    </div>
</div>

<!-- JavaScript for handling the game fetching process -->
<script>
// Make API call to fetch-games-process.php
fetch('fetch-games-process.php')
    .then(response => response.json())
    .then(data => {
        // Handle successful response
        console.log("✅ Fetch complete:", data.message);
        // Hide spinner and show success checkmark
        document.getElementById('spinner').style.display = 'none';
        document.getElementById('tick').style.display = 'block';
        // Update status messages
        document.getElementById('status-message').textContent = data.message;
        document.getElementById('result-message').textContent = 'Games have been fetched and added to your collection.';
        // Show navigation button
        document.getElementById('goHomeBtn').style.display = 'inline-block';
    })
    .catch(error => {
        // Handle error response
        console.error("❌ Fetch error:", error);
        // Hide loading indicators
        document.getElementById('spinner').style.display = 'none';
        document.getElementById('tick').style.display = 'none';
        // Update status messages for error
        document.getElementById('status-message').textContent = '⚠️ Error importing games';
        document.getElementById('result-message').textContent = 'Please check the console or your connection and try again.';
    });
</script>

</body>
</html>
