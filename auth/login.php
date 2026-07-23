<?php
// Include database connection and start session
require_once '../includes/db.php';
session_start();

// Initialize message variable for error/status display
$message = '';

// Process login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and trim input data
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = trim($_POST['password']);

    // Validate that both fields are filled
    if (!empty($username) && !empty($password)) {
        // Query database for user
        $query = $conn->query("SELECT * FROM users WHERE username = '$username'");

        if ($query->num_rows > 0) {
            $user = $query->fetch_assoc();

            // Verify password using secure password_verify function
            if (password_verify($password, $user['password'])) {
                // Set session variables for authenticated user
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_image'] = $user['profile_image'] ?? '';

                // Redirect to explore page after successful login
                header('Location: ../explore.php');
                exit();
            } else {
                $message = "Invalid username or password.";
            }
        } else {
            $message = "Invalid username or password.";
        }
    } else {
        $message = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta tags and title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GameTracker</title>
    
    <!-- External CSS dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/styles.css">
    
    <!-- Custom styles for login page -->
    <style>
        :root {
            --primary-color: #b200ff;
        }
        html, body {
            height: 100%;
        }
        body {
            min-height: 100vh;
            background: #15151e;
            font-family: 'Exo 2', 'Orbitron', sans-serif;
            color: #fff;
            display: flex;
            flex-direction: column;
        }
        .hero-section {
            position: relative;
            padding: 5rem 0 2rem;
            background: linear-gradient(to right, rgba(21, 21, 30, 0.95), rgba(21, 21, 30, 0.85));
            border-bottom: 1px solid rgba(127, 0, 255, 0.3);
            margin-bottom: 2rem;
        }
        .hero-section h1 {
            font-family: 'Orbitron', sans-serif;
            color: var(--primary-color);
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
        }
        .hero-section .lead {
            color: #ffffff;
            font-size: 1.1rem;
            margin-bottom: 0;
        }
        .form-container {
            background: #1e1e2f;
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(127, 0, 255, 0.08);
            padding: 2.5rem 2rem 2rem 2rem;
            max-width: 400px;
            margin: 0 auto;
        }
        .form-label {
            color: #fff;
            font-weight: 500;
        }
        .form-control {
            background: rgba(30, 30, 47, 0.8);
            border: 1px solid rgba(127, 0, 255, 0.2);
            color: #fff;
            border-radius: 8px;
            padding: 0.75rem;
        }
        .form-control:focus {
            background: rgba(30, 30, 47, 0.95);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(127, 0, 255, 0.15);
            color: #fff;
        }
        .form-control::placeholder {
            color: #a8a8b3;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            color: #fff;
            font-weight: 600;
            border-radius: 8px;
            padding: 0.75rem;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(127, 0, 255, 0.12);
        }
        .btn-primary:hover {
            background-color: #9933ff;
            color: #fff;
            transform: translateY(-2px);
        }
        .alert {
            background: rgba(255, 140, 0, 0.08);
            border: 1px solid #ff8c00;
            color: #ff8c00;
            border-radius: 8px;
        }
        a {
            color: var(--primary-color);
        }
        a:hover {
            color: #ff8c00;
        }
        .input-group-text {
            background: rgba(30, 30, 47, 0.8);
            border: 1px solid rgba(127, 0, 255, 0.2);
            color: #a8a8b3;
            border-radius: 8px 0 0 8px;
        }
        @media (max-width: 576px) {
            .form-container {
                width: 90vw !important;
                min-width: 0;
                max-width: 95vw;
                padding: 2rem 0.5rem 2rem 0.5rem;
                margin: 0 auto;
                box-sizing: border-box;
                font-size: 1.15rem;
            }
            .hero-section {
                padding: 2rem 0 0.5rem;
                text-align: center;
            }
            h1, .hero-section h1 {
                font-size: 2rem;
            }
            .form-label {
                font-size: 1.1rem;
            }
            .form-control, .input-group-text {
                font-size: 1.15rem;
                padding: 1.1rem 1rem;
                height: 3.2rem;
            }
            .btn-primary {
                font-size: 1.2rem;
                padding: 1rem;
                height: 3.2rem;
            }
            .alert {
                font-size: 1.1rem;
            }
            .mb-3, .mb-4 {
                margin-bottom: 1.5rem !important;
            }
        }
    </style>
</head>

<body class="text-light">

<!-- Hero Section: Login page header -->
<section class="hero-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center">
                <h1 class="mb-2"><i class="bi bi-person-circle me-2"></i>Login</h1>
                <p class="lead">Access your GameTracker account to manage your games and profile.</p>
            </div>
        </div>
    </div>
</section>

<!-- Login Form Container -->
<div class="form-container mt-4 mb-5">
    <h2 class="mb-4 text-center d-lg-none">Login to GameTracker</h2>
    
    <!-- Display error/status message if any -->
    <?php if (!empty($message)): ?>
        <div class="alert text-center mb-3"><?= $message ?></div>
    <?php endif; ?>
    
    <!-- Login Form -->
    <form method="post">
        <!-- Username Input Field -->
        <div class="mb-3">
            <label class="form-label">Username</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" name="username" class="form-control" required autofocus placeholder="Enter your username">
            </div>
        </div>
        
        <!-- Password Input Field -->
        <div class="mb-3">
            <label class="form-label">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" class="form-control" required placeholder="Enter your password">
            </div>
        </div>
        
        <!-- Submit Button -->
        <button class="btn btn-primary w-100 mt-2"><i class="bi bi-box-arrow-in-right me-2"></i>Login</button>
    </form>
    
    <!-- Registration Link -->
    <p class="text-center mt-3 small">
        Don't have an account? <a href="register.php" class="text-decoration-underline">Register</a>
    </p>
</div>

<!-- Bootstrap JavaScript -->
</body>
</html>

<?php $conn->close(); ?>
