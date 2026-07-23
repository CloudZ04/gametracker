<?php
$servername = getenv('DB_HOST') ?: 'localhost';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS');
if ($password === false) {
    $password = '';
}
$dbname = getenv('DB_NAME') ?: 'gametracker';
$port = (int)(getenv('DB_PORT') ?: 3306);

$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Auto-login from remember_me cookie if no active session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $tokenHash = hash('sha256', $_COOKIE['remember_me']);
    $stmt = $conn->prepare(
        "SELECT u.id, u.username, u.name, u.role, u.profile_image
         FROM remember_tokens rt
         JOIN users u ON rt.user_id = u.id
         WHERE rt.token = ? AND rt.expires_at > NOW()"
    );
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $_SESSION['user_id']      = $row['id'];
        $_SESSION['username']     = $row['username'];
        $_SESSION['name']         = $row['name'] ?? $row['username'];
        $_SESSION['role']         = $row['role'];
        $_SESSION['profile_image'] = $row['profile_image'] ?? '';
    } else {
        // Token invalid or expired — clear the cookie
        setcookie('remember_me', '', time() - 3600, '/');
    }
    $stmt->close();
}
?>
