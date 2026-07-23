<?php
require_once 'includes/db.php';
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Privacy Policy - GameTracker.gg</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            color: white;
            font-family: 'Exo 2', sans-serif;
            min-height: 100vh;
        }

        .privacy-header {
            background: rgba(0, 0, 0, 0.3);
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-bottom: 1px solid rgba(127, 0, 255, 0.2);
        }

        .privacy-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #fff;
        }

        .privacy-content {
            background: rgba(30, 30, 47, 0.5);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 3rem;
            border: 1px solid rgba(127, 0, 255, 0.2);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
        }

        .privacy-section {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(127, 0, 255, 0.1);
        }

        .privacy-section:last-child {
            border-bottom: none;
        }

        h2 {
            font-family: 'Orbitron', sans-serif;
            color: #b200ff;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
        }

        h3 {
            font-family: 'Orbitron', sans-serif;
            color: #9933ff;
            margin: 1.5rem 0 1rem;
            font-size: 1.4rem;
        }

        p {
            line-height: 1.8;
            margin-bottom: 1rem;
            color: rgba(255, 255, 255, 0.9);
        }

        ul {
            padding-left: 1.5rem;
            margin-bottom: 1.5rem;
        }

        li {
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .last-updated {
            font-style: italic;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 2rem;
            text-align: center;
        }

        .contact-section {
            background: rgba(127, 0, 255, 0.1);
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 2rem;
            border: 1px solid rgba(127, 0, 255, 0.3);
        }
    </style>
</head>
<body>

<?php include 'includes/nav.php'; ?>

<header class="privacy-header">
    <div class="container">
        <h1 class="privacy-title">Privacy Policy</h1>
        <p class="lead">Your privacy is important to us. This policy outlines how we collect, use, and protect your data.</p>
    </div>
</header>

<div class="container">
    <div class="privacy-content">
        <div class="privacy-section">
            <h2>1. Information We Collect</h2>
            <h3>1.1 Account Information</h3>
            <p>When you create an account on GameTracker.gg, we collect:</p>
            <ul>
                <li>Username</li>
                <li>Email address</li>
                <li>Password (encrypted)</li>
                <li>Optional profile information (avatar, bio)</li>
            </ul>

            <h3>1.2 Gaming Activity</h3>
            <p>We collect information about your gaming activities, including:</p>
            <ul>
                <li>Games you add to your collection</li>
                <li>Play status updates</li>
                <li>Wishlist items</li>
                <li>Game ratings and reviews</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2>2. How We Use Your Information</h2>
            <p>We use the collected information to:</p>
            <ul>
                <li>Provide and maintain our game tracking service</li>
                <li>Personalize your gaming experience</li>
                <li>Send important notifications about your account</li>
                <li>Improve our website and services</li>
                <li>Analyze usage patterns and trends</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2>3. Data Security</h2>
            <p>We implement various security measures to protect your personal information:</p>
            <ul>
                <li>Encryption of sensitive data</li>
                <li>Secure password hashing</li>
                <li>Regular security audits</li>
                <li>Protected database access</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2>4. Data Sharing</h2>
            <p>We do not sell or share your personal information with third parties except:</p>
            <ul>
                <li>When required by law</li>
                <li>With your explicit consent</li>
                <li>To protect our rights and safety</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2>5. Cookies and Tracking</h2>
            <p>We use cookies to:</p>
            <ul>
                <li>Maintain your login session</li>
                <li>Remember your preferences</li>
                <li>Analyze site traffic and usage</li>
                <li>Improve site performance</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2>6. Your Rights</h2>
            <p>You have the right to:</p>
            <ul>
                <li>Access your personal data</li>
                <li>Correct inaccurate data</li>
                <li>Request data deletion</li>
                <li>Export your data</li>
                <li>Opt-out of communications</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2>7. Children's Privacy</h2>
            <p>Our service is not intended for users under 13 years of age. We do not knowingly collect information from children under 13.</p>
        </div>

        <div class="contact-section">
            <h2>Contact Us</h2>
            <p>If you have any questions about this Privacy Policy, please contact us at:</p>
            <ul>
                <li>Email: privacy@gametracker.gg</li>
                <li>Address: [Your Business Address]</li>
            </ul>
        </div>

        <p class="last-updated">Last updated: <?php echo date('F j, Y'); ?></p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html> 