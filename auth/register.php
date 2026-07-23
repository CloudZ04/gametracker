<?php
// Include database connection and start session
require_once '../includes/db.php';
session_start();

// Initialize message variable for user feedback
$message = '';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and trim input data
    $username = $conn->real_escape_string(trim($_POST['username']));
    $email = $conn->real_escape_string(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password']);
    $password2 = trim($_POST['password2'] ?? '');

    // Validate all required fields are filled
    if (!empty($username) && !empty($email) && !empty($password) && !empty($password2)) {
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
        }
        // Validate username length
        elseif (strlen($username) < 4) {
            $message = "Username must be at least 4 characters.";
        }
        // Validate password length
        elseif (strlen($password) < 6) {
            $message = "Password must be at least 6 characters.";
        }
        // Verify passwords match
        elseif ($password !== $password2) {
            $message = "Passwords do not match.";
        } else {
            // Check for existing username or email
            $check = $conn->query("SELECT * FROM users WHERE username = '$username' OR email = '$email'");
            if ($check->num_rows > 0) {
                $message = "Username or email already exists!";
            } else {
                // Hash password for secure storage
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user with prepared statement
                $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $email, $hashed_password);
                $stmt->execute();
                
                // Set success message with login link
                $message = "Registration successful! You can now <a href='login.php' class='text-decoration-underline text-light'>login</a>.";
                $stmt->close();
            }
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
    <title>Register - GameTracker</title>
    
    <!-- External CSS dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/styles.css">
    
    <!-- Custom styles for registration page -->
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
        /* Mobile responsiveness */
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
            /* Additional mobile-specific styles */
            .hero-section {
                padding: 2rem 0 0.5rem;
                text-align: center;
            }
            /* Adjust font sizes and spacing for mobile */
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
        }
        
    </style>
</head>

<body class="text-light">
    <!-- Hero section with registration title -->
    <section class="hero-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h1 class="mb-2"><i class="bi bi-person-plus me-2"></i>Register</h1>
                    <p class="lead">Create your GameTracker account to start managing your games and profile.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Registration form container -->
    <div class="form-container mt-4 mb-5">
        <h2 class="mb-4 text-center d-lg-none">Create an Account</h2>
        <?php if (!empty($message)): ?>
            <div class="alert text-center mb-3"><?= $message ?></div>
        <?php endif; ?>
        
        <!-- Registration form -->
        <form method="post">
            <!-- Username field -->
            <div class="mb-3">
                <label class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" class="form-control" required autofocus placeholder="Choose a username">
                </div>
            </div>
            
            <!-- Email field -->
            <div class="mb-3">
                <label class="form-label">Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control" required placeholder="Enter your email">
                </div>
            </div>
            
            <!-- Password fields -->
            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control" required placeholder="Create a password">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Re-enter Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" name="password2" class="form-control" required placeholder="Repeat your password">
                </div>
            </div>
            
            <!-- Submit button -->
            <button class="btn btn-primary w-100 mt-2"><i class="bi bi-person-plus me-2"></i>Register</button>
        </form>
        
        <!-- Login link -->
        <p class="text-center mt-3 small">
            Already have an account? <a href="login.php" class="text-decoration-underline">Login</a>
        </p>
    </div>

    <!-- Bootstrap JavaScript -->
</body>
</html>

<?php $conn->close(); ?>
