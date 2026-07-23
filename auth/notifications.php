<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Include database connection
require_once '../includes/db.php';

// Get user info
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$name = $_SESSION['name'] ?? '';
$email = $_SESSION['email'] ?? '';
$profile_image = $_SESSION['profile_image'] ?? '';
$initials = strtoupper(preg_replace('/[^A-Z]/i', '', $username[0] . ($username[1] ?? '')));

// Get notifications
$notifications = [];
$unread_count = 0;

try {
    // Get all notifications for the user
    $query = $conn->prepare("
        SELECT n.*, u.username, u.name, u.profile_image
        FROM notifications n
        JOIN users u ON n.from_user_id = u.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 100
    ");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
        if (!$row['is_read']) {
            $unread_count++;
        }
    }
    
    // Get unread count
    $unreadQuery = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND is_read = FALSE
    ");
    $unreadQuery->bind_param("i", $user_id);
    $unreadQuery->execute();
    $unread_count = $unreadQuery->get_result()->fetch_assoc()['count'];
    
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - GameTracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@300;400;500;600;700&family=Orbitron:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/styles.css">
    <style>
        .notification-card {
            background-color: #1e1e2f;
            border: 1px solid rgba(127, 0, 255, 0.2);
            border-radius: 10px;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }
        
        .notification-card:hover {
            border-color: rgba(127, 0, 255, 0.4);
            box-shadow: 0 4px 15px rgba(127, 0, 255, 0.1);
        }
        
        .notification-card.unread {
            background-color: rgba(127, 0, 255, 0.05);
            border-left: 4px solid #b200ff;
        }
        
        .notification-card.unread:hover {
            background-color: rgba(127, 0, 255, 0.1);
        }
        
        .notification-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .notification-avatar-initials {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background-color: #b200ff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .notification-time {
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .notification-message {
            color: #e9ecef;
            margin-bottom: 0.5rem;
        }
        
        .notification-username {
            color: #b200ff;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .mark-all-read-btn {
            background-color: #b200ff;
            border-color: #b200ff;
            color: white;
            transition: all 0.3s ease;
        }
        
        .mark-all-read-btn:hover {
            background-color: #8a00cc;
            border-color: #8a00cc;
            color: white;
        }

        .clear-all-btn {
            background: transparent;
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #dc3545;
            transition: all 0.3s ease;
        }
        .clear-all-btn:hover {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
                 .notification-type-badge {
             background-color: rgba(127, 0, 255, 0.2);
             color: #b200ff;
             font-size: 0.75rem;
             padding: 0.25rem 0.5rem;
             border-radius: 12px;
             font-weight: 500;
         }
         
         .notification-avatar,
         .notification-avatar-initials {
             transition: all 0.3s ease;
         }
         
         .notification-avatar:hover,
         .notification-avatar-initials:hover {
             transform: scale(1.1);
             box-shadow: 0 0 10px rgba(127, 0, 255, 0.5);
         }
         
         .notification-username {
             transition: color 0.3s ease;
         }
         
         .notification-username:hover {
             color: #8a00cc !important;
         }
    </style>
</head>
<body class="bg-dark text-light">
    <?php include '../includes/nav.php'; ?>

    <div class="container mt-5 pt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1">
                            <i class="bi bi-bell-fill text-primary me-2"></i>
                            Notifications
                        </h1>
                        <p class="text-muted mb-0">
                            <?= $unread_count ?> unread notification<?= $unread_count != 1 ? 's' : '' ?>
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($unread_count > 0): ?>
                        <button class="btn btn-primary mark-all-read-btn" id="markAllRead">
                            <i class="bi bi-check-all me-1"></i>Mark All Read
                        </button>
                        <?php endif; ?>
                        <?php if (!empty($notifications)): ?>
                        <button class="btn clear-all-btn" id="clearAll">
                            <i class="bi bi-trash me-1"></i>Clear All
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notifications List -->
                <div id="notificationsContainer">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <i class="bi bi-bell-slash"></i>
                            <h4>No notifications yet</h4>
                            <p class="text-muted">When you receive notifications, they'll appear here.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-card p-3 <?= !$notification['is_read'] ? 'unread' : '' ?>" 
                                 data-notification-id="<?= $notification['id'] ?>">
                                <div class="d-flex align-items-start">
                                                                         <div class="flex-shrink-0 me-3">
                                         <a href="view-profile.php?username=<?= urlencode($notification['username']) ?>" class="text-decoration-none">
                                             <?php if (!empty($notification['profile_image'])): ?>
                                                 <img src="../uploads/profiles/<?= htmlspecialchars($notification['profile_image']) ?>" 
                                                      class="notification-avatar" alt="Profile">
                                             <?php else: ?>
                                                 <div class="notification-avatar-initials">
                                                     <?= strtoupper($notification['username'][0]) ?>
                                                 </div>
                                             <?php endif; ?>
                                         </a>
                                     </div>
                                     <div class="flex-grow-1">
                                         <div class="d-flex justify-content-between align-items-start mb-2">
                                             <div>
                                                 <a href="view-profile.php?username=<?= urlencode($notification['username']) ?>" class="text-decoration-none">
                                                     <span class="notification-username"><?= htmlspecialchars($notification['username']) ?></span>
                                                 </a>
                                                <span class="notification-type-badge ms-2">
                                                    <?php
                                                    switch ($notification['type']) {
                                                        case 'follow':
                                                            echo '<i class="bi bi-person-plus me-1"></i>Follow';
                                                            break;
                                                        case 'friend_request':
                                                            echo '<i class="bi bi-person-plus me-1"></i>Friend Request';
                                                            break;
                                                        case 'friend_accepted':
                                                            echo '<i class="bi bi-check-circle me-1"></i>Friend Accepted';
                                                            break;
                                                        case 'friend_declined':
                                                            echo '<i class="bi bi-x-circle me-1"></i>Friend Declined';
                                                            break;
                                                        default:
                                                            echo '<i class="bi bi-bell me-1"></i>Notification';
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                                                                                                                      <small class="notification-time">
                                                                                                   <?php
                                                                                                     // Set timezone to match your local time
                                                   date_default_timezone_set('Europe/London');
                                                   
                                                   $created = strtotime($notification['created_at']);
                                                   $now = time();
                                                   $diff = $now - $created;
                                                   
                                                   if ($diff < 60) {
                                                       echo 'Just now';
                                                   } elseif ($diff < 3600) {
                                                       $minutes = floor($diff / 60);
                                                       echo $minutes . 'm ago';
                                                   } elseif ($diff < 86400) {
                                                       $hours = floor($diff / 3600);
                                                       echo $hours . 'h ago';
                                                   } elseif ($diff < 2592000) { // 30 days
                                                       $days = floor($diff / 86400);
                                                       echo $days . 'd ago';
                                                   } elseif ($diff < 31536000) { // 365 days
                                                       $months = floor($diff / 2592000);
                                                       echo $months . 'mo ago';
                                                   } else {
                                                       $years = floor($diff / 31536000);
                                                       echo $years . 'y ago';
                                                   }
                                                  
                                                  ?>
                                             </small>
                                        </div>
                                        <div class="notification-message">
                                            <?= htmlspecialchars($notification['message']) ?>
                                        </div>
                                        <?php if ($notification['type'] === 'follow' || $notification['type'] === 'friend_request'): ?>
                                        <div class="notification-actions mt-2">
                                            <?php if ($notification['type'] === 'follow'): ?>
                                                <button class="btn btn-primary btn-sm follow-btn" data-user-id="<?= $notification['from_user_id'] ?>" data-notification-id="<?= $notification['id'] ?>">
                                                    <i class="bi bi-person-plus me-1"></i>Follow Back
                                                </button>
                                            <?php elseif ($notification['type'] === 'friend_request'): ?>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button class="btn btn-success accept-friend-btn" data-user-id="<?= $notification['from_user_id'] ?>" data-notification-id="<?= $notification['id'] ?>">
                                                        <i class="bi bi-check me-1"></i>Accept
                                                    </button>
                                                    <button class="btn btn-danger decline-friend-btn" data-user-id="<?= $notification['from_user_id'] ?>" data-notification-id="<?= $notification['id'] ?>">
                                                        <i class="bi bi-x me-1"></i>Decline
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

         <script>
         document.addEventListener('DOMContentLoaded', function() {
            const markAllReadBtn = document.getElementById('markAllRead');
            const notificationCards = document.querySelectorAll('.notification-card');
            
            // Mark individual notification as read
            notificationCards.forEach(card => {
                card.addEventListener('click', function() {
                    const notificationId = this.dataset.notificationId;
                    if (!this.classList.contains('unread')) return;
                    
                    markNotificationAsRead(notificationId, this);
                });
            });
            
            // Clear all notifications
            const clearAllBtn = document.getElementById('clearAll');
            if (clearAllBtn) {
                clearAllBtn.addEventListener('click', function() {
                    showConfirm('Delete all notifications? This cannot be undone.', function() {
                        fetch('../api/clear-notifications.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' }
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                document.getElementById('notificationsContainer').innerHTML = `
                                    <div class="empty-state">
                                        <i class="bi bi-bell-slash"></i>
                                        <h4>No notifications yet</h4>
                                        <p class="text-muted">When you receive notifications, they'll appear here.</p>
                                    </div>`;
                                clearAllBtn.style.display = 'none';
                                if (markAllReadBtn) markAllReadBtn.style.display = 'none';
                                const countEl = document.querySelector('.text-muted.mb-0');
                                if (countEl) countEl.textContent = '0 unread notifications';
                                showToast('All notifications cleared.', 'success');
                            } else {
                                showToast(data.message || 'Failed to clear notifications', 'error');
                            }
                        })
                        .catch(() => showToast('An error occurred', 'error'));
                    });
                });
            }

            // Mark all notifications as read
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', function() {
                    const unreadCards = document.querySelectorAll('.notification-card.unread');
                    const notificationIds = Array.from(unreadCards).map(card => card.dataset.notificationId);
                    
                    if (notificationIds.length > 0) {
                        markAllNotificationsAsRead(notificationIds);
                    }
                });
            }
            
            // Handle follow back button clicks
            document.querySelectorAll('.follow-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const userId = this.dataset.userId;
                    const notificationId = this.dataset.notificationId;
                    followUser(userId, notificationId, this);
                });
            });
            
            // Handle accept friend request button clicks
            document.querySelectorAll('.accept-friend-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const userId = this.dataset.userId;
                    const notificationId = this.dataset.notificationId;
                    respondToFriendRequest(userId, 'accept', notificationId, this);
                });
            });
            
            // Handle decline friend request button clicks
            document.querySelectorAll('.decline-friend-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const userId = this.dataset.userId;
                    const notificationId = this.dataset.notificationId;
                    respondToFriendRequest(userId, 'decline', notificationId, this);
                });
            });
            
            function markNotificationAsRead(notificationId, cardElement) {
                fetch('../api/mark-notification-read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ notification_id: notificationId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cardElement.classList.remove('unread');
                        updateUnreadCount();
                    }
                })
                .catch(error => console.error('Error marking notification as read:', error));
            }
            
            function markAllNotificationsAsRead(notificationIds) {
                // Mark each notification as read
                const promises = notificationIds.map(id => 
                    fetch('../api/mark-notification-read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ notification_id: id })
                    }).then(response => response.json())
                );
                
                Promise.all(promises)
                    .then(() => {
                        // Remove unread class from all cards
                        document.querySelectorAll('.notification-card.unread').forEach(card => {
                            card.classList.remove('unread');
                        });
                        
                        updateUnreadCount();
                        
                        // Hide the mark all read button
                        if (markAllReadBtn) {
                            markAllReadBtn.style.display = 'none';
                        }
                    })
                    .catch(error => console.error('Error marking all notifications as read:', error));
            }
            
            function updateUnreadCount() {
                const unreadCount = document.querySelectorAll('.notification-card.unread').length;
                const countElement = document.querySelector('.text-muted.mb-0');
                if (countElement) {
                    countElement.textContent = `${unreadCount} unread notification${unreadCount != 1 ? 's' : ''}`;
                }
            }
            
            function followUser(userId, notificationId, buttonElement) {
                fetch('../api/toggle-follow.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ user_id: userId })
                })
                .then(response => response.json())
                .then(data => {
                        if (data.success) {
                        const cardElement = buttonElement.closest('.notification-card');
                        buttonElement.innerHTML = '<i class="bi bi-person-check-fill me-1"></i>Following';
                        buttonElement.classList.remove('btn-primary');
                        buttonElement.classList.add('btn-success');
                        buttonElement.disabled = true;
                        if (cardElement) markNotificationAsRead(notificationId, cardElement);
                    } else {
                        showToast(data.message || 'Failed to follow user', 'error');
                    }
                })
                .catch(() => showToast('An error occurred', 'error'));
            }
            
                         function respondToFriendRequest(userId, action, notificationId, buttonElement) {
                 console.log('Sending friend request response:', { userId, action, notificationId });
                 fetch('../api/respond-friend-request.php', {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                     },
                     body: JSON.stringify({ 
                         user_id: userId,
                         action: action
                     })
                 })
                                 .then(response => response.json())
                 .then(data => {
                     console.log('Response from respond-friend-request.php:', data);
                     if (data.success) {
                         // Store the card element before modifying the button
                         const cardElement = buttonElement.closest('.notification-card');
                         const buttonGroup = buttonElement.closest('.btn-group');
                         
                         if (action === 'accept') {
                             // Replace button group with "Accepted" message
                             buttonGroup.innerHTML = '<span class="btn btn-success btn-sm disabled"><i class="bi bi-check-circle me-1"></i>Accepted</span>';
                         } else {
                             // Replace button group with "Declined" message
                             buttonGroup.innerHTML = '<span class="btn btn-secondary btn-sm disabled"><i class="bi bi-x-circle me-1"></i>Declined</span>';
                         }
                         
                         // Mark notification as read
                         if (cardElement) {
                             markNotificationAsRead(notificationId, cardElement);
                         }
                     } else {
                         showToast(data.message || `Failed to ${action} friend request`, 'error');
                     }
                 })
                .catch(() => showToast('An error occurred', 'error'));
            }
        });
    </script>
</body>
</html> 