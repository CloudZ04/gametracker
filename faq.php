<?php
require_once 'includes/db.php';
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - GameTracker.gg</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="includes/styles.css">
    <style>
        .hero-section {
            position: relative;
            padding: 4rem 0 2rem;
            background: linear-gradient(to right, rgba(21, 21, 30, 0.9), rgba(21, 21, 30, 0.7));
            border-bottom: 1px solid rgba(127, 0, 255, 0.3);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .hero-title {
            font-family: 'Orbitron', sans-serif;
            color: #ffffff;
            margin-bottom: 1rem;
            font-size: clamp(2.1rem, 4.6vw, 2.8rem);
            line-height: 1.1;
            font-weight: 500;
        }

        .hero-title-accent {
            color: #b200ff;
            font-weight: 700;
        }

        .faq-section {
            background: #1e1e2f;
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .faq-section:hover {
            border-color: rgba(127, 0, 255, 0.3);
            box-shadow: 0 0 20px rgba(127, 0, 255, 0.1);
        }

        .faq-section h2 {
            font-family: 'Orbitron', sans-serif;
            color: #b200ff;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .faq-item {
            background: rgba(30, 30, 47, 0.5);
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .faq-item:hover {
            border-color: rgba(127, 0, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .faq-question {
            font-family: 'Exo 2', sans-serif;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.75rem;
            font-size: 1.1rem;
        }

        .faq-answer {
            color: #a8a8b3;
            line-height: 1.6;
            margin-bottom: 0;
        }

        .faq-answer a {
            color: #b200ff;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .faq-answer a:hover {
            color: #9933ff;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1 class="hero-title"><span class="hero-title-accent">Frequently</span> <span>Asked Questions</span></h1>
            <p class="text-light">Find answers to common questions about GameTracker.gg</p>
        </div>
    </section>

    <div class="container">
        <!-- General Questions -->
        <div class="faq-section">
            <h2><i class="bi bi-info-circle me-2"></i>General Questions</h2>
            
            <div class="faq-item">
                <div class="faq-question">What is GameTracker.gg?</div>
                <div class="faq-answer">
                    GameTracker.gg is a platform that helps you track your gaming journey. You can maintain your game collection, 
                    track your progress, write reviews, and connect with other gamers. It's designed to be your personal gaming diary 
                    and achievement showcase.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Is GameTracker.gg free to use?</div>
                <div class="faq-answer">
                    Yes! GameTracker.gg is completely free to use. Create an account and start tracking your games right away.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Do I need to create an account?</div>
                <div class="faq-answer">
                    Yes, you need an account to track games, write reviews, and access most features. However, you can browse games 
                    and read reviews without an account.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">A game I play is not on the website, can you add it?</div>
                <div class="faq-answer">
                    Due to the near infinite games on the market, it is almost impossible to add every game in the world. We add the most popular games on 
                    the market, but you can request a game to be added on this page <a href="games/request-games.php">here</a>, or by contacting us.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Why create GameTracker.gg?</div>
                <div class="faq-answer">
                    I originally created this website for a college project, but I decided to continue working on it and make it a full-fledged website. Some reasons I decided to continue working on this website are listed below: <br><br>

                    <ul>
                        <li>While there are websites like IGDB, RAWG, GGApp.io, etc. I feel they are not the easiest to use, and do not have the features I felt like I needed.</li>
                        <li>This is supposed to be a website for myself, and possibly others, who want to track their progression on many games, as much as possible, with the ability to request features and feel listened to.</li>
                        <li>Many websites don't allow you to track your games, achievements, and more all in one place.</li>
                        <li>I am still relevantly new to the code the backend was built on, especially when it comes to markdown styling, and styling descriptions within databases, similar to how steam has a unique page for each game, but able to style each game. This also allows me to help practice my skills.</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Features & Functionality -->
        <div class="faq-section">
            <h2><i class="bi bi-gear me-2"></i>Features & Functionality</h2>
            
            <div class="faq-item">
                <div class="faq-question">How do I add games to my collection?</div>
                <div class="faq-answer">
                    You can add games to your collection by:
                    <ol>
                        <li>Searching for a game in the Explore section</li>
                        <li>Clicking on the game you want to add</li>
                        <li>Selecting your play status (Want to Play, Playing, Completed, etc.)</li>
                    </ol>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">What are the different game statuses?</div>
                <div class="faq-answer">
                    GameTracker offers several status options:
                    <ul>
                        <li><strong>Want to Play</strong> - Games you're interested in playing</li>
                        <li><strong>Playing</strong> - Games you're currently playing</li>
                        <li><strong>Beaten</strong> - Games you've finished the main story</li>
                        <li><strong>Completed</strong> - Games you've 100% completed</li>
                        <li><strong>Shelved</strong> - Games you've paused but plan to return to</li>
                        <li><strong>Abandoned</strong> - Games you've stopped playing</li>
                    </ul>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Can I track achievements?</div>
                <div class="faq-answer">
                    Yes! If you connect your Steam account, GameTracker will automatically sync and display your Steam achievements 
                    for supported games. Go to your profile settings to connect your Steam account. Should achievements not show, you can refresh them by clicking the refresh button on your profile page under the achievements tab.
                </div>
            </div>
        </div>

        <!-- Account Management -->
        <div class="faq-section">
            <h2><i class="bi bi-person-gear me-2"></i>Account Management</h2>
            
            <div class="faq-item">
                <div class="faq-question">How do I change my profile picture?</div>
                <div class="faq-answer">
                    You can change your profile picture by:
                    <ol>
                        <li>Going to your Profile</li>
                        <li>Clicking "Edit Profile"</li>
                        <li>Uploading a new image</li>
                    </ol>
                    Supported formats are JPG, PNG, and GIF (non-animated). Animated GIFs could come later in development, should the need arise.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">How do I connect my Steam account?</div>
                <div class="faq-answer">
                    To connect your Steam account:
                    <ol>
                        <li>Go to your Profile</li>
                        <li>Click the "Connect Steam" button</li>
                        <li>Log in to Steam when prompted</li>
                        <li>Authorize GameTracker to access your Steam data</li>
                    </ol>
                    Your achievements will sync automatically after connecting.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Can I delete my account?</div>
                <div class="faq-answer">
                    Yes, you can delete your account by contacting our support team. Please note that this action is permanent 
                    and will remove all your data including game collections, reviews, and achievements. Once your account is deleted, this action cannot be reversed.
                </div>
            </div>
        </div>

        <!-- Privacy & Data -->
        <div class="faq-section">
            <h2><i class="bi bi-shield-lock me-2"></i>Privacy & Data</h2>
            
            <div class="faq-item">
                <div class="faq-question">What data does GameTracker collect?</div>
                <div class="faq-answer">
                    We collect basic account information (email, username), your game collection data, reviews, and if connected, 
                    your Steam achievements and Steam ID. For full details, please read our <a href="privacy-policy.php">Privacy Policy</a>.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Can I make my profile private?</div>
                <div class="faq-answer">
                    Currently, all profiles are public. We're working on privacy settings that will allow you to control who can 
                    see your profile and collection.
                </div>
            </div>
        </div>

        <!-- Still Need Help? -->
        <div class="faq-section">
            <h2><i class="bi bi-question-circle me-2"></i>Still Need Help?</h2>
            
            <div class="faq-item">
                <div class="faq-question">How can I get additional support?</div>
                <div class="faq-answer">
                    If you couldn't find the answer to your question here, please check our <a href="terms.php">Terms of Service</a> 
                    or contact our support team for assistance.
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html> 