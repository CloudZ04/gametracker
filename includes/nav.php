<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/1hnd/gametracker/');
}

// Get user info from session
$username = $_SESSION['username'] ?? 'Guest';
$initials = strtoupper(preg_replace('/[^A-Z]/i', '', $username[0] . ($username[1] ?? '')));
$profile_image = $_SESSION['profile_image'] ?? '';

// Get unread notification count and fresh profile image when logged in (so nav always shows current image)
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/db.php';
        $unreadQuery = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? AND is_read = FALSE
        ");
        $unreadQuery->bind_param("i", $_SESSION['user_id']);
        $unreadQuery->execute();
        $unread_count = $unreadQuery->get_result()->fetch_assoc()['count'];

        // Load current profile image from DB so nav shows it even after upload (session may be stale)
        $imgStmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
        $imgStmt->bind_param("i", $_SESSION['user_id']);
        $imgStmt->execute();
        $imgResult = $imgStmt->get_result();
        if ($imgResult && ($imgRow = $imgResult->fetch_assoc()) && isset($imgRow['profile_image']) && $imgRow['profile_image'] !== '' && $imgRow['profile_image'] !== null) {
            $profile_image = $imgRow['profile_image'];
        }
    } catch (Exception $e) {
        // Silently fail if database connection fails
        $unread_count = 0;
    }
}
// Build profile image URL: profile images are under auth/uploads/profiles/ (update-profile runs from auth/)
$profile_image_url = '';
if (!empty($profile_image)) {
    $filename = (strpos($profile_image, '/') !== false) ? basename($profile_image) : $profile_image;
    $profile_image_url = BASE_URL . 'auth/uploads/profiles/' . $filename;
}
?>

<style>
@import url("https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700&display=swap");

body,
h1, h2, h3, h4, h5, h6,
p, a, span, li, label,
button, input, select, textarea,
.btn, .dropdown-item, .dropdown-header,
.form-control, .form-select {
    font-family: 'Rubik', sans-serif !important;
}

/* Enhanced Professional Navigation Styles */
.custom-navbar {
    background: linear-gradient(135deg, #15151e 0%, #1a1a26 100%) !important;
    border-bottom: 1px solid rgba(178, 0, 255, 0.2);
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    padding: 0.75rem 0;
    transition: all 0.3s ease;
    z-index: 1040;
}

.custom-navbar::before {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, 
        transparent,
        rgba(178, 0, 255, 0.3) 20%,
        rgba(178, 0, 255, 0.6) 50%,
        rgba(178, 0, 255, 0.3) 80%,
        transparent
    );
}

/* Brand Styling */
.navbar-brand {
    font-family: 'Rubik', sans-serif;
    font-weight: 600;
    font-size: 1.4rem;
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
    text-decoration: none !important;
}

.navbar-brand:hover {
    transform: scale(1.02);
}

.logo-img {
    width: 50px;
    height: 50px;
    margin-right: 0.75rem;
    transition: all 0.3s ease;
}



.game-text {
    color: #b200ff !important;
    font-weight: 700;
}

.navbar-brand span:not(.game-text) {
    color: #ffffff !important;
    font-weight: 400;
}

/* Navigation Links */
.navbar-nav {
    gap: 0.5rem;
}

.nav-link {
    font-family: 'Rubik', sans-serif;
    font-size: 1rem;
    font-weight: 400;
    letter-spacing: 0.03em;
    color: #e0e0e0 !important;
    padding: 0.6rem 1rem !important;
    border-radius: 8px;
    position: relative;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    display: flex;
    align-items: center;
}

.nav-link:hover {
    color: #ffffff !important;
    background-color: rgba(178, 0, 255, 0.1);
    transform: translateY(-1px);
}

.nav-link.active {
    color: #b200ff !important;
    background-color: rgba(178, 0, 255, 0.15);
    font-weight: 600;
}

.nav-link.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 60%;
    background: linear-gradient(180deg, #b200ff, #8b00cc);
    border-radius: 0 2px 2px 0;
}

/* Icon styling in navigation */
.nav-link i {
    font-size: 1.1rem;
    margin-right: 0.4rem;
    opacity: 0.8;
    transition: opacity 0.3s ease;
}

.nav-link:hover i,
.nav-link.active i {
    opacity: 1;
}

/* Profile and Notification Enhancements */
.initials-avatar {
    background: linear-gradient(135deg, #b200ff, #8b00cc);
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 2px solid rgba(178, 0, 255, 0.3);
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(178, 0, 255, 0.2);
}

/* Nav profile avatar wrapper – fixed size so image is never clipped */
.nav-profile-avatar {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    overflow: hidden;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(255, 255, 255, 0.1);
}
.nav-profile-avatar img {
    width: 40px;
    height: 40px;
    object-fit: cover;
    display: block;
}
.dropdown-toggle img,
.dropdown-toggle .initials-avatar,
.dropdown-toggle .nav-profile-avatar {
    transition: all 0.3s ease;
}
.dropdown-toggle .nav-profile-avatar {
    border: 2px solid rgba(255, 255, 255, 0.1);
}

.dropdown-toggle:hover img,
.dropdown-toggle:hover .initials-avatar,
.dropdown-toggle:hover .nav-profile-avatar {
    border-color: #b200ff;
    box-shadow: 0 0 15px rgba(178, 0, 255, 0.4);
    transform: scale(1.05);
}

/* Notification Bell */
.nav-link.position-relative {
    padding: 0.6rem !important;
    border-radius: 50%;
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nav-link.position-relative i {
    font-size: 1.2rem;
    margin: 0;
}

.nav-link.position-relative:hover {
    background-color: rgba(178, 0, 255, 0.15);
}

/* Notification Badge */
.badge.bg-danger {
    background: linear-gradient(135deg, #ff4757, #ff3742) !important;
    border: 2px solid #15151e;
    font-size: 0.65rem;
    font-weight: 600;
    min-width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Navbar dropdowns – use class on menu so styles apply even when Popper moves menu (e.g. on home page) */
.navbar-dropdown-menu.dropdown-menu-dark,
.navbar-dropdown-menu.dropdown-menu {
    background: linear-gradient(135deg, #1e1e2f 0%, #252538 100%) !important;
    border: 1px solid rgba(178, 0, 255, 0.3);
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    padding: 0.5rem 0;
    margin-top: 0.5rem !important;
    z-index: 1060 !important;
}

.navbar-dropdown-menu .dropdown-item {
    color: #e0e0e0 !important;
    padding: 0.6rem 1.2rem;
    font-weight: 400;
    transition: all 0.3s ease;
    border-radius: 8px;
    margin: 0rem;
    display: flex;
    align-items: center;
}

.navbar-dropdown-menu .dropdown-item:hover {
    background: linear-gradient(135deg, rgba(178, 0, 255, 0.2), rgba(178, 0, 255, 0.1)) !important;
    color: #ffffff !important;
}

.navbar-dropdown-menu .dropdown-item.text-danger:hover {
    background: linear-gradient(135deg, rgba(255, 71, 87, 0.2), rgba(255, 71, 87, 0.1)) !important;
    color: #ff4757 !important;
}

.navbar-dropdown-menu .dropdown-item i {
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
    opacity: 0.8;
}

.navbar-dropdown-menu .dropdown-item:hover i {
    opacity: 1;
}

.navbar-dropdown-menu .dropdown-item.active {
    color: #b200ff !important;
    background: linear-gradient(135deg, rgba(178, 0, 255, 0.2), rgba(178, 0, 255, 0.1)) !important;
    font-weight: 600;
    position: relative;
}

.navbar-dropdown-menu .dropdown-item.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 60%;
    background: linear-gradient(180deg, #b200ff, #8b00cc);
    border-radius: 0 2px 2px 0;
}

.navbar-dropdown-menu .dropdown-item.active i {
    opacity: 1;
    color: #b200ff;
}

.navbar-dropdown-menu .dropdown-header {
    color: #b200ff !important;
    font-weight: 600;
    font-size: 0.9rem;
    letter-spacing: 0.05em;
    padding: 0.5rem 1.2rem 0.25rem;
}

.navbar-dropdown-menu .dropdown-divider {
    border-color: rgba(178, 0, 255, 0.2);
    margin: 0.5rem 0;
}

/* Mobile Toggler */
.navbar-toggler {
    border: 2px solid rgba(178, 0, 255, 0.3) !important;
    border-radius: 8px;
    padding: 0.4rem 0.6rem;
    transition: all 0.3s ease;
}

.navbar-toggler:hover {
    border-color: #b200ff !important;
    box-shadow: 0 0 10px rgba(178, 0, 255, 0.3);
}

.navbar-toggler-icon {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255, 255, 255, 0.9)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
    width: 20px;
    height: 20px;
}

/* Container adjustments */
.navbar .container {
    padding: 0 1.5rem;
}

/* Responsive adjustments */
@media (max-width: 991.98px) {
    .navbar-collapse {
        background: rgba(21, 21, 30, 0.98);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 12px;
        margin-top: 1rem;
        padding: 1rem;
        border: 1px solid rgba(178, 0, 255, 0.2);
    }
    
    .nav-link {
        margin: 0.2rem 0;
        border-radius: 8px;
    }
    
    .navbar-nav {
        gap: 0.25rem;
    }
}

@media (max-width: 576px) {
    .navbar-brand .game-text,
    .navbar-brand span:not(.game-text) {
        display: none !important;
    }
    
    .logo-img {
        width: 40px;
        height: 40px;
        margin-right: 0;
    }
    
    .navbar .container {
        padding: 0 1rem;
    }
}

/* Smooth scrolling and focus states */
.nav-link:focus,
.dropdown-toggle:focus {
    outline: 2px solid rgba(178, 0, 255, 0.5);
    outline-offset: 2px;
}

/* Loading animation for notifications */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* ── Global toast notifications ───────────────── */
#gt-toast-container {
    position: fixed;
    top: 80px;
    right: 1rem;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-width: 360px;
    width: calc(100% - 2rem);
    pointer-events: none;
}
.gt-toast {
    background: #1e1e2f;
    border-radius: 10px;
    padding: 0.85rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    box-shadow: 0 8px 28px rgba(0,0,0,0.5);
    pointer-events: all;
    animation: gt-toast-in 0.28s cubic-bezier(0.34,1.56,0.64,1);
}
.gt-toast.gt-toast-out {
    animation: gt-toast-out 0.25s ease forwards;
}
.gt-toast-icon { font-size: 1.05rem; flex-shrink: 0; line-height: 1; }
.gt-toast-msg  { color: #e2e2e8; font-size: 0.875rem; line-height: 1.5; flex: 1; }
.gt-toast-close {
    background: none; border: none; color: #555; cursor: pointer;
    padding: 0; font-size: 0.95rem; flex-shrink: 0; line-height: 1;
    transition: color 0.2s;
}
.gt-toast-close:hover { color: #bbb; }
@keyframes gt-toast-in  { from { opacity:0; transform: translateX(30px); } to { opacity:1; transform: translateX(0); } }
@keyframes gt-toast-out { from { opacity:1; transform: translateX(0); }    to { opacity:0; transform: translateX(30px); } }

/* ── Global confirm dialog ─────────────────────── */
#gt-confirm-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(3px);
    z-index: 99998;
    align-items: center;
    justify-content: center;
}
#gt-confirm-overlay.gt-open { display: flex; }
#gt-confirm-box {
    background: #1e1e2f;
    border: 1px solid rgba(178,0,255,0.3);
    border-radius: 14px;
    padding: 1.75rem 1.5rem 1.5rem;
    max-width: 400px;
    width: calc(100% - 2rem);
    box-shadow: 0 16px 48px rgba(0,0,0,0.5);
    animation: gt-confirm-in 0.22s ease;
}
@keyframes gt-confirm-in { from { opacity:0; transform: scale(0.93); } to { opacity:1; transform: scale(1); } }
#gt-confirm-icon { font-size: 2rem; color: #f59e0b; margin-bottom: 0.75rem; }
#gt-confirm-message { color: #e2e2e8; font-size: 0.95rem; line-height: 1.55; margin-bottom: 1.4rem; }
.gt-confirm-actions { display: flex; gap: 0.6rem; justify-content: flex-end; }
#gt-confirm-cancel {
    background: transparent; border: 1px solid rgba(255,255,255,0.15);
    color: #aaa; border-radius: 8px; padding: 0.5rem 1.1rem;
    font-size: 0.875rem; cursor: pointer; transition: all 0.2s;
}
#gt-confirm-cancel:hover { background: rgba(255,255,255,0.08); color: #fff; }
#gt-confirm-ok {
    background: #dc3545; border: none; color: #fff;
    border-radius: 8px; padding: 0.5rem 1.25rem;
    font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
}
#gt-confirm-ok:hover { background: #b02a37; }

.notification-loading {
    animation: pulse 1.5s ease-in-out infinite;
}

/* Improved notification dropdown styling */
#notificationList .dropdown-item {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(178, 0, 255, 0.1);
    margin: 0;
    border-radius: 0;
}

#notificationList .dropdown-item:last-child {
    border-bottom: none;
}

#notificationList .dropdown-item:hover {
    background: rgba(178, 0, 255, 0.1) !important;
}
</style>
<script src="https://unpkg.com/@phosphor-icons/web"></script>

<!-- Main navigation bar -->
<nav class="navbar navbar-expand-lg custom-navbar fixed-top">
  <div class="container d-flex align-items-center justify-content-between">
    <!-- Logo and brand name -->
    <a class="navbar-brand" href="<?= BASE_URL ?>">
      <img class="logo-img" src="<?= BASE_URL ?>images/logo.svg" alt="GameTracker Logo">
      <span class="game-text"></span><span></span>
    </a>

    <!-- Mobile menu toggle button -->
    <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Mobile profile dropdown -->
    <?php if (isset($_SESSION['user_id'])): ?>
      <div class="nav-item dropdown ms-2 d-block d-lg-none">
        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="userDropdownMobile" role="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false">
          <?php if (!empty($profile_image_url)): ?>
            <span class="nav-profile-avatar"><img src="<?= $profile_image_url ?>" alt="Profile"></span>
          <?php else: ?>
            <div class="initials-avatar"><?= $initials ?></div>
          <?php endif; ?>
          <span class="d-none d-sm-inline text-nowrap"><?= htmlspecialchars($username) ?></span>
        </a>
        <!-- Mobile dropdown menu items -->
                  <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end navbar-dropdown-menu" aria-labelledby="userDropdownMobile">
            <li><a class="dropdown-item <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>auth/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
            <li><a class="dropdown-item <?= basename($_SERVER['PHP_SELF']) == 'profile-wishlist.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>auth/profile-wishlist.php"><i class="bi bi-heart me-2"></i>Wishlist</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item <?= basename($_SERVER['PHP_SELF']) == 'search-users.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>auth/search-users.php"><i class="bi bi-people me-2"></i>Find Friends</a></li>
            <li><a class="dropdown-item <?= basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>auth/messages.php"><i class="bi bi-chat-dots me-2"></i>Messages</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>auth/settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
      </div>
    <?php endif; ?>

    <!-- Main navigation menu -->
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto align-items-center">
        <!-- Navigation links -->
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>">
            <i class="bi bi-house-door"></i>Home
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'explore.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>explore.php">
            <i class="bi bi-compass"></i>Explore
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'timeline.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>timeline.php">
            <i class="bi bi-clock-history"></i>Timeline
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'faq.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>faq.php">
            <i class="bi bi-question-circle"></i>FAQ
          </a>
        </li>
        


        <!-- Admin link (only visible to admin users) -->
        <?php if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'admin.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>auth/admin.php">
                <i class="bi bi-shield-check"></i>Admin
            </a>
        </li>
        <?php endif; ?>

        <!-- Login/Register links (only visible when not logged in) -->
        <?php if (!isset($_SESSION['user_id'])): ?>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>auth/login.php">
            <i class="bi bi-box-arrow-in-right"></i>Login
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'register.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>auth/register.php">
            <i class="bi bi-person-plus"></i>Register
          </a>
        </li>
        <?php endif; ?>

        <!-- Notification bell (only visible when logged in) -->
        <?php if (isset($_SESSION['user_id'])): ?>
        <li class="nav-item dropdown ms-2 d-none d-lg-block">
          <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false">
            <i class="bi bi-bell"></i>
            <?php if ($unread_count > 0): ?>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                <?= $unread_count > 9 ? '9+' : $unread_count ?>
              </span>
            <?php endif; ?>
          </a>
          <!-- Notification dropdown menu -->
          <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end navbar-dropdown-menu" aria-labelledby="notificationDropdown" style="width: 320px; max-height: 400px; overflow-y: auto;">
            <li><h6 class="dropdown-header"><i class="bi bi-bell me-2"></i>Recent Notifications</h6></li>
            <div id="notificationList">
              <!-- Notifications will be loaded here via JavaScript -->
            </div>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-center" href="<?= BASE_URL ?>auth/notifications.php">
              <i class="bi bi-list-ul me-2"></i>View All Notifications
            </a></li>
          </ul>
        </li>
        <?php endif; ?>

        <!-- Desktop profile dropdown -->
        <?php if (isset($_SESSION['user_id'])): ?>
        <li class="nav-item dropdown ms-2 d-none d-lg-block">
          <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="userDropdownDesktop" role="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false">
            <?php if (!empty($profile_image_url)): ?>
              <span class="nav-profile-avatar"><img src="<?= $profile_image_url ?>" alt="Profile"></span>
            <?php else: ?>
              <div class="initials-avatar"><?= $initials ?></div>
            <?php endif; ?>
            <span class="text-nowrap"><?= htmlspecialchars($username) ?></span>
          </a>
          <!-- Desktop dropdown menu items -->
          <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end navbar-dropdown-menu" aria-labelledby="userDropdownDesktop">
            <li><h6 class="dropdown-header"><?= htmlspecialchars($username) ?></h6></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>auth/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
            <li><a class="dropdown-item <?= basename($_SERVER['PHP_SELF']) == 'profile-wishlist.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>auth/profile-wishlist.php"><i class="bi bi-heart me-2"></i>Wishlist</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item <?= basename($_SERVER['PHP_SELF']) == 'search-users.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>auth/search-users.php"><i class="bi bi-people me-2"></i>Find Friends</a></li>
            <li><a class="dropdown-item <?= basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>auth/messages.php"><i class="bi bi-chat-dots me-2"></i>Messages</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>auth/settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- Spacer to prevent content from hiding behind fixed navbar -->
<div style="height: 85px;"></div>

<!-- Global toast container -->
<div id="gt-toast-container" aria-live="polite"></div>

<!-- Global confirm dialog -->
<div id="gt-confirm-overlay">
    <div id="gt-confirm-box">
        <div id="gt-confirm-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <p id="gt-confirm-message"></p>
        <div class="gt-confirm-actions">
            <button id="gt-confirm-cancel">Cancel</button>
            <button id="gt-confirm-ok">Confirm</button>
        </div>
    </div>
</div>

<script>
(function() {
    var BASE_URL = <?= json_encode(BASE_URL) ?>;
    document.addEventListener('DOMContentLoaded', function () {
        // Centralized favicon: force all pages to use logo.svg
        (function setGlobalFavicon() {
            var faviconHref = BASE_URL + 'images/logo.svg';
            var head = document.head || document.getElementsByTagName('head')[0];
            if (!head) return;

            // Remove any existing icon declarations to prevent page-level mismatches
            head.querySelectorAll('link[rel="icon"], link[rel="shortcut icon"]').forEach(function(node) {
                node.parentNode.removeChild(node);
            });

            var icon = document.createElement('link');
            icon.setAttribute('rel', 'icon');
            icon.setAttribute('type', 'image/svg+xml');
            icon.setAttribute('href', faviconHref);
            head.appendChild(icon);
        })();

        var navbarToggler = document.querySelector('.navbar-toggler');
        var navbarCollapse = document.getElementById('navbarNav');
        
        if (navbarCollapse) {
            navbarCollapse.querySelectorAll('.nav-link:not(.dropdown-toggle)').forEach(function(link) {
                link.addEventListener('click', function () {
                    if (navbarCollapse.classList.contains('show')) {
                        new bootstrap.Collapse(navbarCollapse).hide();
                    }
                });
            });
        }
        
        document.addEventListener('click', function (event) {
            if (!navbarCollapse || !navbarToggler) return;
            var isClickInsideNavbar = navbarCollapse.contains(event.target);
            var isClickOnToggler = navbarToggler.contains(event.target);
            if (!isClickInsideNavbar && !isClickOnToggler && navbarCollapse.classList.contains('show')) {
                new bootstrap.Collapse(navbarCollapse).hide();
            }
        });
        
        var notificationDropdown = document.getElementById('notificationDropdown');
        var notificationList = document.getElementById('notificationList');
        
        if (notificationDropdown && notificationList) {
            notificationDropdown.addEventListener('show.bs.dropdown', function() {
                notificationList.innerHTML = '<li><span class="dropdown-item notification-loading" style="color: #ccc;"><i class="bi bi-arrow-clockwise me-2"></i>Loading notifications...</span></li>';
                loadNotifications();
            });
        }
        
        function loadNotifications() {
            fetch(BASE_URL + 'api/get-notifications.php')
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success && data.notifications && data.notifications.length > 0) {
                        var notificationsUrl = BASE_URL + 'auth/notifications.php';
                        var profileImgBase = BASE_URL + 'auth/uploads/profiles/';
                        notificationList.innerHTML = data.notifications.slice(0, 5).map(function(notification) {
                            var img = notification.from_user.profile_image
                                ? '<img src="' + profileImgBase + (notification.from_user.profile_image.indexOf('/') !== -1 ? notification.from_user.profile_image.split('/').pop() : notification.from_user.profile_image) + '" class="rounded-circle" style="width: 36px; height: 36px; object-fit: cover;">'
                                : '<div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-size: 0.8rem; color: white;">' + (notification.from_user.username ? notification.from_user.username.charAt(0).toUpperCase() : '') + '</div>';
                            return '<li><a class="dropdown-item d-flex align-items-start py-3" href="' + notificationsUrl + '"><div class="flex-shrink-0 me-3">' + img + '</div><div class="flex-grow-1"><div class="fw-semibold small mb-1">' + (notification.from_user.username || '') + '</div><div class="small text-light mb-1">' + (notification.message || '') + '</div><div class="small" style="color: #aaa;">' + getTimeAgo(notification.created_at) + '</div></div></a></li>';
                        }).join('');
                    } else {
                        notificationList.innerHTML = '<li><span class="dropdown-item text-center py-4" style="color: #ccc;"><i class="bi bi-bell-slash me-2"></i>No notifications</span></li>';
                    }
                })
                .catch(function(error) {
                    console.error('Error loading notifications:', error);
                    notificationList.innerHTML = '<li><span class="dropdown-item text-danger text-center py-4"><i class="bi bi-exclamation-triangle me-2"></i>Error loading notifications</span></li>';
                });
        }
    
        function getTimeAgo(timestamp) {
            var now = new Date();
            var created = new Date(timestamp);
            var diffMs = now - created;
            var diffMins = Math.floor(diffMs / 60000);
            var diffHours = Math.floor(diffMs / 3600000);
            var diffDays = Math.floor(diffMs / 86400000);
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return diffMins + 'm ago';
            if (diffHours < 24) return diffHours + 'h ago';
            if (diffDays < 7) return diffDays + 'd ago';
            return created.toLocaleDateString();
        }
        
        // ── Global toast & confirm helpers ──────────────
        window.showToast = function(message, type, duration) {
            type     = type     || 'error';
            duration = (duration === undefined) ? 4000 : duration;
            var icons  = { success:'bi-check-circle-fill', error:'bi-exclamation-circle-fill', warning:'bi-exclamation-triangle-fill', info:'bi-info-circle-fill' };
            var colors = { success:'#22c55e', error:'#ef4444', warning:'#f59e0b', info:'#b200ff' };
            var color  = colors[type] || colors.info;
            var icon   = icons[type]  || icons.info;
            var id     = 'gt-toast-' + Date.now();
            var container = document.getElementById('gt-toast-container');
            var el = document.createElement('div');
            el.className = 'gt-toast';
            el.id = id;
            el.style.borderLeft = '3px solid ' + color;
            el.style.border     = '1px solid ' + color + '33';
            el.style.borderLeftColor = color;
            el.innerHTML =
                '<i class="bi ' + icon + ' gt-toast-icon" style="color:' + color + '"></i>' +
                '<span class="gt-toast-msg">' + message + '</span>' +
                '<button class="gt-toast-close" onclick="(function(e){e.classList.add(\'gt-toast-out\');setTimeout(function(){e.remove();},260);})(document.getElementById(\'' + id + '\'))"><i class="bi bi-x-lg"></i></button>';
            container.appendChild(el);
            if (duration > 0) {
                setTimeout(function() {
                    if (document.getElementById(id)) {
                        el.classList.add('gt-toast-out');
                        setTimeout(function() { el.remove(); }, 260);
                    }
                }, duration);
            }
        };

        window.showConfirm = function(message, onConfirm, options) {
            options = options || {};
            var overlay    = document.getElementById('gt-confirm-overlay');
            var msgEl      = document.getElementById('gt-confirm-message');
            var okBtn      = document.getElementById('gt-confirm-ok');
            var cancelBtn  = document.getElementById('gt-confirm-cancel');
            var iconEl     = document.getElementById('gt-confirm-icon').querySelector('i');

            msgEl.textContent   = message;
            okBtn.textContent   = options.confirmText  || 'Confirm';
            cancelBtn.textContent = options.cancelText || 'Cancel';
            okBtn.style.background = options.danger === false ? '#b200ff' : '#dc3545';
            if (iconEl) iconEl.className = options.danger === false ? 'bi bi-info-circle-fill' : 'bi bi-exclamation-triangle-fill';

            var newOk     = okBtn.cloneNode(true);
            var newCancel = cancelBtn.cloneNode(true);
            okBtn.parentNode.replaceChild(newOk, okBtn);
            cancelBtn.parentNode.replaceChild(newCancel, cancelBtn);
            newOk.textContent     = options.confirmText  || 'Confirm';
            newCancel.textContent = options.cancelText   || 'Cancel';
            newOk.style.background = options.danger === false ? '#b200ff' : '#dc3545';

            function close() { overlay.classList.remove('gt-open'); }
            newOk.addEventListener('click', function() { close(); if (onConfirm) onConfirm(); });
            newCancel.addEventListener('click', close);
            overlay.addEventListener('click', function handler(e) {
                if (e.target === overlay) { close(); overlay.removeEventListener('click', handler); }
            });
            overlay.classList.add('gt-open');
        };
        // ── End helpers ──────────────────────────────────

        document.querySelectorAll('a[href^="#"]:not([data-bs-toggle]):not([data-bs-dismiss])').forEach(function(anchor) {
            anchor.addEventListener('click', function (e) {
                var href = this.getAttribute('href');
                if (!href || href === '#') {
                    return;
                }
                e.preventDefault();
                var target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    });
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
