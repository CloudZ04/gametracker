<?php
require_once '../includes/db.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Debug: Log notification values
error_log("User notification values:");
error_log("notify_friend_requests: " . ($user['notify_friend_requests'] ?? 'NULL'));
error_log("notify_new_followers: " . ($user['notify_new_followers'] ?? 'NULL'));
error_log("notify_achievements: " . ($user['notify_achievements'] ?? 'NULL'));
error_log("notify_followed_games: " . ($user['notify_followed_games'] ?? 'NULL'));
error_log("notify_game_achievements: " . ($user['notify_game_achievements'] ?? 'NULL'));
error_log("notify_reviews: " . ($user['notify_reviews'] ?? 'NULL'));
error_log("notify_activity: " . ($user['notify_activity'] ?? 'NULL'));

$page_title = "Account Settings";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - GameTracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css" />
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/fill/style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../includes/styles.css">
    <style>
        :root {
            --primary-color: #b200ff;
            --card-bg: #1e1e2f;
            --text-color: #ffffff;
            --border-color: rgba(127, 0, 255, 0.3);
        }

        body {
            background-color: #15151e;
            color: var(--text-color);
        }

        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--text-color);
        }

        .card-header {
            background-color: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
        }

        .list-group-item {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--text-color);
        }

        .list-group-item:hover {
            background-color: rgba(127, 0, 255, 0.1);
            color: var(--primary-color);
        }

        .list-group-item.active {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .list-group-item.active:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: var(--text-color);
        }

        .form-control {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--text-color);
        }

        .form-control:focus {
            background-color: var(--card-bg);
            border-color: var(--primary-color);
            color: var(--text-color);
            box-shadow: 0 0 0 0.2rem rgba(178, 0, 255, 0.25);
        }

        .form-control:disabled {
            background-color: rgba(127, 0, 255, 0.1);
            color: rgba(255, 255, 255, 0.6);
        }

        .form-label {
            color: var(--text-color);
        }

        .text-muted {
            color: rgba(255, 255, 255, 0.6) !important;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: #8a00cc;
            border-color: #8a00cc;
        }

        .settings-nav .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
        }

        .tab-content {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
        }

        .tab-pane {
            padding: 1.5rem;
        }

        /* Custom checkbox and radio button styles */
        .form-check {
            padding: 0;
            margin-bottom: 0.75rem;
        }

        .form-check-input {
            display: none;
        }

        .form-check-label {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: rgba(30, 30, 47, 0.5);
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
            color: var(--text-color);
        }

        /* Radio button specific styles */
        input[type="radio"] + .form-check-label {
            justify-content: space-between;
        }

        input[type="radio"] + .form-check-label::before {
            display: none;
        }

        input[type="radio"] + .form-check-label i {
            margin-right: 0.75rem;
            color: var(--primary-color);
        }

        /* Checkbox specific styles */
        input[type="checkbox"] + .form-check-label {
            justify-content: flex-start;
        }

        input[type="checkbox"] + .form-check-label i {
            margin-right: 0.75rem;
            color: var(--primary-color);
            opacity: 0.8;
        }

        input[type="checkbox"]:checked + .form-check-label i {
            opacity: 1;
        }

        .form-check-label::before {
            content: '';
            width: 24px;
            height: 24px;
            margin-right: 1rem;
            background: transparent;
            border: 2px solid rgba(127, 0, 255, 0.3);
            border-radius: 4px;
            transition: all 0.2s ease;
            order: -1;
        }

        .form-check-input:checked + .form-check-label {
            background: rgba(127, 0, 255, 0.15);
        }

        .form-check-input:checked + .form-check-label::before {
            background: var(--primary-color) url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4 8.5l2.5 2.5l5.5 -5.5'/%3e%3c/svg%3e") center/16px no-repeat;
            border-color: var(--primary-color);
        }

        .form-check-label .description {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* Ensure checkbox state is properly synced */
        .form-check-input:checked + .form-check-label {
            background: rgba(127, 0, 255, 0.15);
        }

        .form-check-input:not(:checked) + .form-check-label {
            background: rgba(30, 30, 47, 0.5);
        }
    </style>
</head>
<body>
    <?php require_once '../includes/nav.php'; ?>

    <div class="container py-5">
        <!-- Toast Container -->
        <div class="toast-container position-fixed" style="top: 80px; right: 1rem; z-index: 1000;"></div>

        <div class="row">
            <!-- Settings Navigation -->
            <div class="col-md-3">
                <div class="settings-nav card">
                    <div class="list-group list-group-flush">
                        <a href="#profile" class="list-group-item list-group-item-action active" data-bs-toggle="list">
                            <i class="bi bi-person-circle me-2"></i>Profile
                        </a>
                        <a href="#personalisation" class="list-group-item list-group-item-action" data-bs-toggle="list">
                            <i class="bi bi-palette me-2"></i>Personalisation
                        </a>
                        <a href="#privacy" class="list-group-item list-group-item-action" data-bs-toggle="list">
                            <i class="bi bi-shield-lock me-2"></i>Privacy
                        </a>
                        <a href="#account" class="list-group-item list-group-item-action" data-bs-toggle="list">
                            <i class="bi bi-gear me-2"></i>Account
                        </a>
                        <a href="#notifications" class="list-group-item list-group-item-action" data-bs-toggle="list">
                            <i class="bi bi-bell me-2"></i>Notifications
                        </a>
                        <a href="#data" class="list-group-item list-group-item-action" data-bs-toggle="list">
                            <i class="bi bi-database me-2"></i>Data & Export
                        </a>
                    </div>
                </div>
            </div>

            <!-- Settings Content -->
            <div class="col-md-9">
                <div class="tab-content">
                    <!-- Profile Settings -->
                    <div class="tab-pane fade show active" id="profile">
                        <h5 class="mb-4">Profile Settings</h5>
                        <form id="profileForm" action="update-profile.php" method="POST" enctype="multipart/form-data">
                            <div class="mb-4 text-center">
                                <?php if (!empty($user['profile_image'])): ?>
                                    <img src="<?= strpos($user['profile_image'], 'uploads/profiles/') === 0 ? $user['profile_image'] : 'uploads/profiles/' . $user['profile_image'] ?>" 
                                         alt="Profile Image" 
                                         class="rounded-circle shadow mb-3" 
                                         style="width: 100px; height: 100px; object-fit: cover; border: 3px solid rgba(127, 0, 255, 0.3);">
                                <?php else: ?>
                                    <div class="profile-avatar-initials rounded-circle shadow mb-3 mx-auto" 
                                         style="width: 100px; height: 100px; aspect-ratio: 1/1; border-radius: 50%; background: var(--card-bg); border: 3px solid rgba(127, 0, 255, 0.3); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: var(--primary-color);">
                                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label for="profile_image" class="form-label d-block">Profile Picture</label>
                                    <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*" style="max-width: 300px; margin: 0 auto;">
                                    <small class="text-muted d-block mt-2">Recommended size: 300x300 pixels</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="name" class="form-label">Display Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>">
                            </div>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                <small class="text-muted">Usernames cannot be changed</small>
                            </div>
                            <div class="mb-3">
                                <label for="about" class="form-label">About Me</label>
                                <textarea class="form-control" id="about" name="about" rows="3"><?= htmlspecialchars(str_replace(['\r\n', '\r', '\n'], ["\n", "\n", "\n"], $user['about'])) ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>

                    <!-- Personalisation Settings -->
                    <div class="tab-pane fade" id="personalisation">
                        <h5 class="mb-4">Personalisation Settings</h5>
                        <form id="personalisationForm" action="update-personalisation.php" method="POST">
                            <div class="mb-4">
                                <label class="form-label">Theme</label>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="theme" id="default" value="default" <?= $user['theme'] === 'default' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="default">
                                        <span><i class="ph ph-game-controller"></i>Default</span>
                                        <span class="description">Default theme (dark)</span>
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="theme" id="dark" value="dark" <?= $user['theme'] === 'dark' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="dark">
                                        <span><i class="fa-solid fa-radiation"></i></i>Fallout</span>
                                        <span class="description">Fallout inspired theme from the PIP-Pad</span>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="theme" id="jedi" value="jedi" <?= $user['theme'] === 'jedi' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="jedi">
                                        <span><i class="fa-solid fa-jedi"></i></i>Jedi</span>
                                        <span class="description">Ready to become the chosen one?</span>
                                    </label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Theme Settings</button>
                        </form>
                    </div>


                    <!-- Privacy Settings -->
                    <div class="tab-pane fade" id="privacy">
                        <h5 class="mb-4">Privacy Settings</h5>
                        <form id="privacyForm" method="POST">
                            <div class="mb-4">
                                <label class="form-label">Profile Visibility</label>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="profile_visibility" id="public" value="public" <?= $user['profile_visibility'] === 'public' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="public">
                                        <span><i class="bi bi-globe"></i>Public</span>
                                        <span class="description">Anyone can view your profile</span>
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="profile_visibility" id="friends" value="friends" <?= $user['profile_visibility'] === 'friends' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="friends">
                                        <span><i class="bi bi-people"></i>Friends Only</span>
                                        <span class="description">Only friends can view your profile</span>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="profile_visibility" id="private" value="private" <?= $user['profile_visibility'] === 'private' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="private">
                                        <span><i class="bi bi-lock"></i>Private</span>
                                        <span class="description">Only you can view your profile</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Content Visibility -->
                            <div class="mb-4">
                                <label class="form-label">Content Visibility</label>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="show_collections" id="show_collections" <?= $user['show_collections'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="show_collections">
                                        <i class="bi bi-collection"></i>Show my game collections
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="show_activity" id="show_activity" <?= $user['show_activity'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="show_activity">
                                        <i class="bi bi-activity"></i>Show my activity
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="show_reviews" id="show_reviews" <?= $user['show_reviews'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="show_reviews">
                                        <i class="bi bi-star"></i>Show my reviews
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="show_achievements" id="show_achievements" <?= $user['show_achievements'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="show_achievements">
                                        <i class="bi bi-trophy"></i>Show my achievements
                                    </label>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary" id="savePrivacyBtn">Save Privacy Settings</button>
                        </form>
                    </div>

                    <!-- Account Settings -->
                    <div class="tab-pane fade" id="account">
                        <h5 class="mb-4">Account Settings</h5>
                        <div class="alert alert-info mb-4" style="background: rgba(127, 0, 255, 0.1); border: 1px solid rgba(127, 0, 255, 0.2); color: var(--text-color);">
                            <i class="bi bi-info-circle me-2"></i>
                            Your username (<strong><?= htmlspecialchars($user['username']) ?></strong>) cannot be changed. If you have a special circumstance, please <a href="../support.php" style="color: var(--primary-color); text-decoration: none; font-weight: bold;">contact support</a>.
                        </div>

                        <form id="passwordForm" action="update-password.php" method="POST" class="mb-4">
                            <h6>Change Password</h6>
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </form>

                        <div class="border-top pt-4 mt-4">
                            <h6 class="mb-1">Persistent Login</h6>
                            <p class="text-muted mb-3">If you ticked "Keep me logged in" when signing in, you have an active remember token. Disabling this will sign you out of any device using that token on next page load.</p>
                            <button type="button" class="btn btn-outline-warning" id="disablePersistentLoginBtn">
                                <i class="bi bi-shield-x me-2"></i>Disable Persistent Login
                            </button>
                        </div>

                        <div class="border-top pt-4 mt-4">
                            <h6 class="text-danger">Danger Zone</h6>
                            <p class="text-muted">Once you delete your account, there is no going back. Please be certain.</p>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                Delete Account
                            </button>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div class="tab-pane fade" id="notifications">
                        <h5 class="mb-4">Notification Preferences</h5>
                        <p class="text-muted mb-4">Choose what notifications you want to see on GameTracker.</p>
                        <form id="notificationsForm" action="update-notifications.php" method="POST">
                            <div class="mb-4">
                                <h6 class="mb-3">Activity Notifications</h6>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="notify_friend_requests" id="notify_friend_requests" <?= $user['notify_friend_requests'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="notify_friend_requests">
                                        <i class="bi bi-person-plus"></i>Friend requests
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="notify_new_followers" id="notify_new_followers" <?= $user['notify_new_followers'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="notify_new_followers">
                                        <i class="bi bi-person-heart"></i>New followers
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="notify_achievements" id="notify_achievements" <?= $user['notify_achievements'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="notify_achievements">
                                        <i class="bi bi-trophy"></i>Achievement unlocks
                                    </label>
                                </div>
                            </div>
                            <div class="mb-4">
                                <h6 class="mb-3">Game Updates</h6>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="notify_followed_games" id="notify_followed_games" <?= $user['notify_followed_games'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="notify_followed_games">
                                        <i class="bi bi-heart"></i>Updates from followed games
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="notify_game_achievements" id="notify_game_achievements" <?= $user['notify_game_achievements'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="notify_game_achievements">
                                        <i class="bi bi-trophy"></i>Achievement updates for followed games
                                    </label>
                                </div>
                            </div>
                            <div class="mb-4">
                                <h6 class="mb-3">Social</h6>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="notify_reviews" id="notify_reviews" <?= $user['notify_reviews'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="notify_reviews">
                                        <i class="bi bi-star"></i>Reviews from friends
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="notify_activity" id="notify_activity" <?= $user['notify_activity'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="notify_activity">
                                        <i class="bi bi-activity"></i>Friend activity
                                    </label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Notification Settings</button>
                        </form>
                    </div>

                    <!-- Data & Export -->
                    <div class="tab-pane fade" id="data">
                        <h5 class="mb-4">Data & Export</h5>
                        <p>Download a copy of your data including your profile information, game collections, reviews, and achievements.</p>
                        <form action="export-data.php" method="POST">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-download me-2"></i>Export My Data
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete your account? This action cannot be undone.</p>
                    <form id="deleteAccountForm" action="delete-account.php" method="POST">
                        <div class="mb-3">
                            <label for="delete_confirm" class="form-label">Type "DELETE" to confirm</label>
                            <input type="text" class="form-control" id="delete_confirm" name="delete_confirm" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="deleteAccountForm" class="btn btn-danger">Delete Account</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('disablePersistentLoginBtn')?.addEventListener('click', function() {
        fetch('../api/disable-persistent-login.php', { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast('Persistent login disabled. You will need to log in again next visit.', 'success');
                } else {
                    showToast('Failed to disable persistent login.', 'error');
                }
            })
            .catch(() => showToast('Something went wrong.', 'error'));
    });

    console.log('Settings page JavaScript loaded');
    // Debug: Log notification values from PHP
    console.log('Notification values from database:');
    console.log('notify_friend_requests:', <?= json_encode($user['notify_friend_requests'] ?? null) ?>);
    console.log('notify_new_followers:', <?= json_encode($user['notify_new_followers'] ?? null) ?>);
    console.log('notify_achievements:', <?= json_encode($user['notify_achievements'] ?? null) ?>);
    console.log('notify_followed_games:', <?= json_encode($user['notify_followed_games'] ?? null) ?>);
    console.log('notify_game_achievements:', <?= json_encode($user['notify_game_achievements'] ?? null) ?>);
    console.log('notify_reviews:', <?= json_encode($user['notify_reviews'] ?? null) ?>);
    console.log('notify_activity:', <?= json_encode($user['notify_activity'] ?? null) ?>);
    
    // Debug: Log the actual checkbox checked states
    console.log('Checkbox checked states:');
    console.log('notify_friend_requests checked:', <?= $user['notify_friend_requests'] ? 'true' : 'false' ?>);
    console.log('notify_new_followers checked:', <?= $user['notify_new_followers'] ? 'true' : 'false' ?>);
    console.log('notify_achievements checked:', <?= $user['notify_achievements'] ? 'true' : 'false' ?>);
    console.log('notify_followed_games checked:', <?= $user['notify_followed_games'] ? 'true' : 'false' ?>);
    console.log('notify_game_achievements checked:', <?= $user['notify_game_achievements'] ? 'true' : 'false' ?>);
    console.log('notify_reviews checked:', <?= $user['notify_reviews'] ? 'true' : 'false' ?>);
    console.log('notify_activity checked:', <?= $user['notify_activity'] ? 'true' : 'false' ?>);

    // Set checkbox states based on database values
    document.addEventListener('DOMContentLoaded', function() {
        // Set notification checkboxes
        const notifyFriendRequests = document.getElementById('notify_friend_requests');
        const notifyNewFollowers = document.getElementById('notify_new_followers');
        const notifyAchievements = document.getElementById('notify_achievements');
        const notifyFollowedGames = document.getElementById('notify_followed_games');
        const notifyGameAchievements = document.getElementById('notify_game_achievements');
        const notifyReviews = document.getElementById('notify_reviews');
        const notifyActivity = document.getElementById('notify_activity');
        
        if (notifyFriendRequests) notifyFriendRequests.checked = <?= $user['notify_friend_requests'] ? 'true' : 'false' ?>;
        if (notifyNewFollowers) notifyNewFollowers.checked = <?= $user['notify_new_followers'] ? 'true' : 'false' ?>;
        if (notifyAchievements) notifyAchievements.checked = <?= $user['notify_achievements'] ? 'true' : 'false' ?>;
        if (notifyFollowedGames) notifyFollowedGames.checked = <?= $user['notify_followed_games'] ? 'true' : 'false' ?>;
        if (notifyGameAchievements) notifyGameAchievements.checked = <?= $user['notify_game_achievements'] ? 'true' : 'false' ?>;
        if (notifyReviews) notifyReviews.checked = <?= $user['notify_reviews'] ? 'true' : 'false' ?>;
        if (notifyActivity) notifyActivity.checked = <?= $user['notify_activity'] ? 'true' : 'false' ?>;
        
        console.log('Checkboxes set after DOM load:');
        console.log('notify_friend_requests checked:', notifyFriendRequests?.checked);
        console.log('notify_new_followers checked:', notifyNewFollowers?.checked);
        console.log('notify_achievements checked:', notifyAchievements?.checked);
        console.log('notify_followed_games checked:', notifyFollowedGames?.checked);
        console.log('notify_game_achievements checked:', notifyGameAchievements?.checked);
        console.log('notify_reviews checked:', notifyReviews?.checked);
        console.log('notify_activity checked:', notifyActivity?.checked);
    });

    function showToast(message, type = 'success') {
        const toastContainer = document.querySelector('.toast-container');
        const toastElement = document.createElement('div');
        toastElement.className = `toast align-items-center text-white border-0`;
        toastElement.setAttribute('role', 'alert');
        toastElement.setAttribute('aria-live', 'assertive');
        toastElement.setAttribute('aria-atomic', 'true');
        toastElement.style.backgroundColor = type === 'success' ? '#198754' : '#dc3545';
        toastElement.innerHTML = `
            <div class="d-flex justify-content-between align-items-center w-100">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        toastContainer.appendChild(toastElement);
        const toast = new bootstrap.Toast(toastElement, {
            autohide: true,
            delay: 5000
        });
        toast.show();
        setTimeout(() => toastElement.remove(), 5000);
    }

    document.addEventListener('DOMContentLoaded', function() {
        console.log('Second DOMContentLoaded event fired');
        // Profile Image Preview
        const profileImageInput = document.getElementById('profile_image');
        if (profileImageInput) {
            profileImageInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const previewContainer = document.querySelector('.text-center');
                        let previewImg = previewContainer.querySelector('img');
                        const initialsDiv = previewContainer.querySelector('.profile-avatar-initials');
                        
                        if (previewImg) {
                            previewImg.src = e.target.result;
                        } else if (initialsDiv) {
                            initialsDiv.insertAdjacentHTML('beforebegin', `
                                <img src="${e.target.result}" 
                                     alt="Profile Preview" 
                                     class="rounded-circle shadow mb-3" 
                                     style="width: 100px; height: 100px; object-fit: cover; border: 3px solid rgba(127, 0, 255, 0.3);">
                            `);
                            initialsDiv.style.display = 'none';
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Profile Form Submission
        const profileForm = document.getElementById('profileForm');
        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('update-profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Profile settings saved successfully!');
                        // Update profile image if changed
                        if (data.profile_image) {
                            let imgPath = data.profile_image;
                            if (imgPath && !imgPath.startsWith('/')) {
                                imgPath = '/' + imgPath;
                            }
                            const previewImg = document.querySelector('.text-center img');
                            if (previewImg) {
                                previewImg.src = imgPath + '?t=' + new Date().getTime();
                            }
                        }

                        // Update other fields if needed
                        if (data.name) {
                            document.getElementById('name').value = data.name;
                        }
                        if (data.about) {
                            document.getElementById('about').value = data.about;
                        }
                    }
                });
            });
        }

        // Form validation for password change
        const passwordForm = document.getElementById('passwordForm');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    showToast('New password and confirmation password do not match.', 'error');
                }
            });
        }

        // Delete account confirmation
        const deleteAccountForm = document.getElementById('deleteAccountForm');
        if (deleteAccountForm) {
            deleteAccountForm.addEventListener('submit', function(e) {
                const confirmText = document.getElementById('delete_confirm').value;
                if (confirmText !== 'DELETE') {
                    e.preventDefault();
                    showToast('Please type "DELETE" to confirm account deletion.', 'warning');
                }
            });
        }

        // Password Form Submission
        const passwordFormSubmit = document.getElementById('passwordForm');
        if (passwordFormSubmit) {
            passwordFormSubmit.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('update-password.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Password changed successfully!');
                        this.reset();
                    } else {
                        showToast(data.message || 'Failed to update password', 'danger');
                    }
                });
            });
        }



        // Privacy Form Submission
        const savePrivacyBtn = document.getElementById('savePrivacyBtn');
        console.log('Save privacy button found:', savePrivacyBtn);
        if (savePrivacyBtn) {
            savePrivacyBtn.addEventListener('click', function(e) {
                console.log('Save privacy button clicked');
                e.preventDefault();
                const privacyForm = document.getElementById('privacyForm');
                const formData = new FormData(privacyForm);
                
                // Get checkbox elements and ensure they exist
                const showCollections = document.getElementById('show_collections');
                const showActivity = document.getElementById('show_activity');
                const showReviews = document.getElementById('show_reviews');
                const showAchievements = document.getElementById('show_achievements');
                
                // Check if elements exist before accessing their properties
                if (showCollections && showActivity && showReviews && showAchievements) {
                    formData.set('show_collections', showCollections.checked ? '1' : '0');
                    formData.set('show_activity', showActivity.checked ? '1' : '0');
                    formData.set('show_reviews', showReviews.checked ? '1' : '0');
                    formData.set('show_achievements', showAchievements.checked ? '1' : '0');
                } else {
                    console.error('One or more checkbox elements not found');
                    showToast('Error: Form elements not found', 'danger');
                    return;
                }

                fetch('update-privacy.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Privacy settings saved successfully!');
                    } else {
                        showToast(data.message || 'Failed to update privacy settings', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An error occurred while saving privacy settings', 'danger');
                });
            });
        }

        // Personalisation Form Submission
        const personalisationForm = document.getElementById('personalisationForm');
        if (personalisationForm) {
            personalisationForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                // Debug: Log what's being sent
                console.log('Personalisation form data:');
                for (let [key, value] of formData.entries()) {
                    console.log(key + ': ' + value);
                }
                
                fetch('update-personalisation.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Server response:', data);
                    if (data.success) {
                        showToast('Theme updated successfully!');
                        // Optionally reload the page to apply the theme
                        // window.location.reload();
                    } else {
                        showToast(data.message || 'Failed to update theme', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An error occurred while saving theme settings', 'danger');
                });
            });
        }

        // Notifications Form Submission
        const notificationsForm = document.getElementById('notificationsForm');
        if (notificationsForm) {
            notificationsForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Ensure checkboxes are set correctly before reading them
                // setNotificationCheckboxes(); // This line is removed
                
                const formData = new FormData(this);
                
                // Add checkbox values explicitly (they won't be included if unchecked)
                const notifyFriendRequests = document.getElementById('notify_friend_requests');
                const notifyNewFollowers = document.getElementById('notify_new_followers');
                const notifyAchievements = document.getElementById('notify_achievements');
                const notifyFollowedGames = document.getElementById('notify_followed_games');
                const notifyGameAchievements = document.getElementById('notify_game_achievements');
                const notifyReviews = document.getElementById('notify_reviews');
                const notifyActivity = document.getElementById('notify_activity');
                
                formData.set('notify_friend_requests', notifyFriendRequests.checked ? '1' : '0');
                formData.set('notify_new_followers', notifyNewFollowers.checked ? '1' : '0');
                formData.set('notify_achievements', notifyAchievements.checked ? '1' : '0');
                formData.set('notify_followed_games', notifyFollowedGames.checked ? '1' : '0');
                formData.set('notify_game_achievements', notifyGameAchievements.checked ? '1' : '0');
                formData.set('notify_reviews', notifyReviews.checked ? '1' : '0');
                formData.set('notify_activity', notifyActivity.checked ? '1' : '0');

                // Debug: Log what's being sent
                console.log('Notifications form data:');
                console.log('notify_friend_requests checked:', notifyFriendRequests.checked);
                console.log('notify_new_followers checked:', notifyNewFollowers.checked);
                console.log('notify_achievements checked:', notifyAchievements.checked);
                console.log('notify_followed_games checked:', notifyFollowedGames.checked);
                console.log('notify_game_achievements checked:', notifyGameAchievements.checked);
                console.log('notify_reviews checked:', notifyReviews.checked);
                console.log('notify_activity checked:', notifyActivity.checked);
                
                for (let [key, value] of formData.entries()) {
                    console.log(key + ': ' + value);
                }
                
                fetch('update-notifications.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Server response:', data);
                    if (data.success) {
                        showToast('Notification preferences saved successfully!');
                    } else {
                        showToast(data.message || 'Failed to update notification preferences', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An error occurred while saving notification preferences', 'danger');
                });
            });
        }
    });
    </script>

    <?php require_once '../includes/footer.php'; ?>
</body>
</html> 