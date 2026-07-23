<?php
require_once 'includes/db.php';
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Terms of Service - GameTracker.gg</title>
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

        .terms-header {
            background: rgba(0, 0, 0, 0.3);
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-bottom: 1px solid rgba(127, 0, 255, 0.2);
        }

        .terms-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #fff;
        }

        .terms-content {
            background: rgba(30, 30, 47, 0.5);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 3rem;
            border: 1px solid rgba(127, 0, 255, 0.2);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
        }

        .terms-section {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(127, 0, 255, 0.1);
        }

        .terms-section:last-child {
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

        .highlight-box {
            background: rgba(127, 0, 255, 0.1);
            padding: 1.5rem;
            border-radius: 10px;
            margin: 1.5rem 0;
            border: 1px solid rgba(127, 0, 255, 0.3);
        }

        .highlight-box a{
            color: #b200ff;
            text-decoration: none;
        }

        .warning-text {
            color: #ff9966;
            font-weight: 600;
        }

        /* Sidebar styling */
        .sidebar {
            position: sticky;
            top: 100px;
            background: rgba(30, 30, 47, 0.8);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(127, 0, 255, 0.2);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
            max-height: calc(100vh - 200px);
            overflow-y: auto;
            backdrop-filter: blur(10px);
        }

        .sidebar-title {
            color: #b200ff;
            font-family: 'Orbitron', sans-serif;
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 10px;
        }
        
        .sidebar-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: #b200ff;
            border-radius: 2px;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.7);
            border-radius: 8px;
            margin-bottom: 0.5rem;
            padding: 0.6rem 1rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            border-left: 3px solid transparent;
            text-decoration: none;
        }
        
        .sidebar .nav-link:hover {
            background-color: rgba(127, 0, 255, 0.1);
            color: #fff;
            transform: translateX(5px);
            text-decoration: none;
        }
        
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(127, 0, 255, 0.2);
            border-left: 3px solid #b200ff;
            font-weight: 600;
        }

        /* Custom scrollbar for sidebar */
        .sidebar::-webkit-scrollbar {
            width: 8px;
            background: rgba(30, 30, 47, 0.2);
            border-radius: 8px;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #b200ff 40%, #302b63 100%);
            border-radius: 8px;
            border: 2px solid #181828;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #8a00cc 40%, #24243e 100%);
        }
        
        .sidebar::-webkit-scrollbar-corner {
            background: transparent;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .sidebar {
                position: relative;
                top: 0;
                max-height: none;
                margin-bottom: 2rem;
            }
        }
    </style>
</head>
<body data-bs-spy="scroll" data-bs-target="#terms-sidebar" data-bs-offset="100" tabindex="0">

<?php include 'includes/nav.php'; ?>

<header class="terms-header">
    <div class="container">
        <h1 class="terms-title">Terms of Service</h1>
        <p class="lead">Please read these terms carefully before using GameTracker.gg</p>
    </div>
</header>

<div class="container">
    <div class="d-flex align-items-start">
        <!-- Sidebar on the left -->
        <div id="terms-sidebar" class="sidebar me-4" style="width: 280px;">
            <h4 class="sidebar-title">Table of Contents</h4>
            <nav class="nav flex-column">
                <a class="nav-link" href="#acceptance">
                    <i class="bi bi-check-circle me-2"></i>1. Acceptance of Terms
                </a>
                <a class="nav-link" href="#user-accounts">
                    <i class="bi bi-person-circle me-2"></i>2. User Accounts
                </a>
                <a class="nav-link" href="#user-content">
                    <i class="bi bi-file-text me-2"></i>3. User Content
                </a>
                <a class="nav-link" href="#prohibited">
                    <i class="bi bi-exclamation-triangle me-2"></i>4. Prohibited Activities
                </a>
                <a class="nav-link" href="#intellectual-property">
                    <i class="bi bi-shield-check me-2"></i>5. Intellectual Property
                </a>
                <a class="nav-link" href="#liability">
                    <i class="bi bi-exclamation-circle me-2"></i>6. Limitation of Liability
                </a>
                <a class="nav-link" href="#changes">
                    <i class="bi bi-arrow-clockwise me-2"></i>7. Changes to Terms
                </a>
                <a class="nav-link" href="#governing-law">
                    <i class="bi bi-building me-2"></i>8. Governing Law
                </a>
                <a class="nav-link" href="#contact">
                    <i class="bi bi-envelope me-2"></i>Contact Information
                </a>
            </nav>
        </div>

        <!-- Main content -->
        <div class="terms-content" style="flex: 1;">
            <div class="terms-section" id="acceptance">
                <h2>1. Acceptance of Terms</h2>
                <p>By accessing and using GameTracker.gg, you accept and agree to be bound by the terms and provisions of this agreement. If you do not agree to these terms, please do not use our service.</p>
                
                <div class="highlight-box">
                    <p class="mb-0">These terms apply to all users, including unregistered visitors and registered members.</p>
                </div>
            </div>

            <div class="terms-section" id="user-accounts">
                <h2>2. User Accounts</h2>
                <h3>2.1 Account Creation</h3>
                <p>To use certain features of the Service, you must register for an account. You agree to:</p>
                <ul>
                    <li>Provide accurate and complete information</li>
                    <li>Maintain and update your information</li>
                    <li>Keep your password secure</li>
                    <li>Not share your account with others</li>
                </ul>

                <h3>2.2 Account Termination</h3>
                <p>We reserve the right to terminate or suspend your account at our discretion, without prior notice, for conduct that we believe violates these terms or is harmful to other users, us, or third parties, or for any other reason.</p>
            </div>

            <div class="terms-section" id="user-content">
                <h2>3. User Content</h2>
                <p>When you create, upload, or share content on GameTracker.gg, you:</p>
                <ul>
                    <li>Retain ownership of your content</li>
                    <li>Grant us a license to use and display your content</li>
                    <li>Are responsible for the content you post</li>
                    <li>Agree not to post inappropriate or harmful content</li>
                </ul>

                <div class="highlight-box warning-text">
                    <p>We reserve the right to remove any content that violates these terms or that we find objectionable.</p>
                </div>
            </div>

            <div class="terms-section" id="prohibited">
                <h2>4. Prohibited Activities</h2>
                <p>You agree not to:</p>
                <ul>
                    <li>Use the service for any illegal purpose</li>
                    <li>Harass, abuse, or harm other users</li>
                    <li>Post false or misleading information</li>
                    <li>Attempt to gain unauthorized access</li>
                    <li>Interfere with the proper working of the service</li>
                    <li>Create multiple accounts for malicious purposes</li>
                    <li>Scrape or collect user data without permission</li>
                </ul>
            </div>

            <div class="terms-section" id="intellectual-property">
                <h2>5. Intellectual Property</h2>
                <p>The Service and its original content, features, and functionality are owned by GameTracker.gg and are protected by international copyright, trademark, and other intellectual property laws.</p>
                
                <h3>5.1 Game Information</h3>
                <p>Game information and images are provided by third-party services (RAWG and IGDB) and are used under their respective terms of service.</p>
            </div>

            <div class="terms-section" id="liability">
                <h2>6. Limitation of Liability</h2>
                <p>GameTracker.gg and its operators shall not be liable for any:</p>
                <ul>
                    <li>Indirect, incidental, or consequential damages</li>
                    <li>Loss of data or revenue</li>
                    <li>Service interruptions or errors</li>
                    <li>Third-party content or services</li>
                </ul>
            </div>

            <div class="terms-section" id="changes">
                <h2>7. Changes to Terms</h2>
                <p>We reserve the right to modify these terms at any time. We will notify users of any material changes via:</p>
                <ul>
                    <li>Site announcement</li>
                    <li>Updated "Last Modified" date</li>
                </ul>
                <p>Your continued use of the Service after changes constitutes acceptance of the new terms, so please ensure to check back regularly for any changes.</p>
            </div>

            <div class="terms-section" id="governing-law">
                <h2>8. Governing Law</h2>
                <p>These terms shall be governed by and construed in accordance with the laws of [Your Jurisdiction], without regard to its conflict of law provisions.</p>
            </div>

            <div class="terms-section" id="contact">
                <div class="highlight-box">
                    <h2>Contact Information</h2>
                    <p>Due to me not revealing my identity, an email address is not given out. For any questions about these Terms of Service, please use the support page <a href="support.php">here</a> and I will get back to you as soon as possible.</p>
                </div>
            </div>

            <p class="last-updated">Last updated: <?php echo date('F j, Y'); ?></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Smooth scrolling for anchor links
document.querySelectorAll('#terms-sidebar .nav-link').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.getAttribute('href').substring(1);
        const targetElement = document.getElementById(targetId);
        if (targetElement) {
            targetElement.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Update active state based on scroll position
window.addEventListener('scroll', function() {
    const sections = document.querySelectorAll('.terms-section');
    const navLinks = document.querySelectorAll('#terms-sidebar .nav-link');
    
    let current = '';
    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        const sectionHeight = section.clientHeight;
        if (window.pageYOffset >= sectionTop - 200) {
            current = section.getAttribute('id');
        }
    });
    
    navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === '#' + current) {
            link.classList.add('active');
        }
    });
});
</script>
</body>
</html> 