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
$profile_image = $_SESSION['profile_image'] ?? '';
$initials = strtoupper(preg_replace('/[^A-Z]/i', '', $username[0] . ($username[1] ?? '')));

// Get conversations (unique users the current user has messaged or received messages from)
$conversations = [];

try {
    // Get all conversations for the current user
    $query = $conn->prepare("
        SELECT DISTINCT 
            u.id,
            u.username,
            u.name,
            u.profile_image,
            (
                SELECT m.message 
                FROM messages m 
                WHERE (m.sender_id = ? AND m.receiver_id = u.id) 
                   OR (m.sender_id = u.id AND m.receiver_id = ?)
                ORDER BY m.created_at DESC 
                LIMIT 1
            ) as last_message,
            (
                SELECT m.created_at 
                FROM messages m 
                WHERE (m.sender_id = ? AND m.receiver_id = u.id) 
                   OR (m.sender_id = u.id AND m.receiver_id = ?)
                ORDER BY m.created_at DESC 
                LIMIT 1
            ) as last_message_time,
            (
                SELECT COUNT(*) 
                FROM messages m 
                WHERE m.sender_id = u.id 
                  AND m.receiver_id = ? 
                  AND m.read_at IS NULL
            ) as unread_count
        FROM users u
        WHERE u.id IN (
            SELECT DISTINCT 
                CASE 
                    WHEN sender_id = ? THEN receiver_id 
                    ELSE sender_id 
                END
            FROM messages 
            WHERE sender_id = ? OR receiver_id = ?
        )
        ORDER BY last_message_time DESC
    ");
    $query->bind_param("iiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    $query->execute();
    $result = $query->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $conversations[] = $row;
    }
    
} catch (Exception $e) {
    error_log("Error fetching conversations: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - GameTracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@300;400;500;600;700&family=Orbitron:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/styles.css">
         <style>
         /* Full Page Messages App Styles */
         .messages-app-container {
             height: calc(100vh - 80px); /* Account for navbar */
             display: flex;
             flex-direction: column;
             background-color: #0d0d1a;
         }
         
         .messages-header {
             background-color: #15151e;
             border-bottom: 1px solid rgba(127, 0, 255, 0.2);
             padding: 1rem 2rem;
             flex-shrink: 0;
         }
         
         .messages-container {
             background-color: #1e1e2f;
             flex: 1;
             overflow: hidden;
             display: flex;
             min-height: 0;
         }
         
         .conversation-list {
             background-color: #15151e;
             border-right: 1px solid rgba(127, 0, 255, 0.2);
             width: 25%;
             min-width: 220px;
             max-width: 350px;
             height: 100%;
             overflow-y: auto;
             flex-shrink: 0;
         }
        
        .conversation-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(127, 0, 255, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .conversation-item:hover {
            background-color: rgba(127, 0, 255, 0.1);
        }
        
        .conversation-item.active {
            background-color: rgba(127, 0, 255, 0.2);
            border-left: 4px solid #b200ff;
        }
        
        .conversation-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .conversation-avatar-initials {
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
        
        .conversation-info {
            flex: 1;
            min-width: 0;
        }
        
        .conversation-name {
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.25rem;
        }
        
                 .conversation-preview {
             color: #a8a8b3;
             font-size: 0.875rem;
             white-space: nowrap;
             overflow: hidden;
             text-overflow: ellipsis;
             max-width: 150px; /* Limit width to prevent overflow */
         }
        
        .conversation-time {
            color: #6c757d;
            font-size: 0.75rem;
        }
        
        .unread-badge {
            background-color: #b200ff;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
                 .chat-container {
             width: 75%;
             height: 100%;
             display: flex;
             flex-direction: column;
             flex: 1 1 0%;
             min-width: 0;
             min-height: 0;
         }
        
        .chat-header {
            background: #19192a;
            padding: 1.2rem 2rem 1.2rem 1.2rem;
            border-bottom: 1px solid rgba(127, 0, 255, 0.1);
            flex-shrink: 0;
        }
        
        .chat-messages {
            flex: 1 1 0%;
            overflow-y: auto;
            padding: 2rem 2rem 1rem 2rem;
            min-height: 0;
        }
        
        .message {
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-end;
        }
        
        .message.sent {
            justify-content: flex-end;
        }
        
        .message.received {
            justify-content: flex-start;
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 0.75rem 1rem;
            border-radius: 18px;
            word-wrap: break-word;
        }
        
        .message.sent .message-bubble {
            background-color: #b200ff;
            color: white;
            border-bottom-right-radius: 4px;
        }
        
        .message.received .message-bubble {
            background-color: #2a2a3d;
            color: #ffffff;
            border-bottom-left-radius: 4px;
        }
        
        .message-time {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .chat-input {
            background: #19192a;
            padding: 1rem 2rem;
            border-top: 1px solid rgba(127, 0, 255, 0.1);
            flex-shrink: 0;
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
        
        .no-conversation {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #6c757d;
            text-align: center;
        }
    </style>
</head>
<body class="bg-dark text-light">
    <?php include '../includes/nav.php'; ?>

         <!-- Full Page Messages App -->
     <div class="messages-app-container">
         <!-- Compact Header -->
         <div class="messages-header">
             <div class="d-flex align-items-center justify-content-between">
                 <div class="d-flex align-items-center">
                     <i class="bi bi-chat-dots-fill text-primary me-2" style="font-size: 1.5rem;"></i>
                     <span class="text-light fw-bold">Messages</span>
                 </div>
                 <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                     <i class="bi bi-plus me-1"></i>New Message
                 </button>
             </div>
         </div>
 
         <!-- Messages Container -->
         <div class="messages-container">
             <div class="conversation-list">
                 <?php if (empty($conversations)): ?>
                     <div class="empty-state">
                         <i class="bi bi-chat-dots"></i>
                         <h4>No conversations yet</h4>
                         <p class="text-light">Start messaging your friends!</p>
                     </div>
                 <?php else: ?>
                     <?php foreach ($conversations as $conversation): ?>
                         <div class="conversation-item d-flex align-items-center" 
                              data-user-id="<?= $conversation['id'] ?>" 
                              data-username="<?= htmlspecialchars($conversation['username']) ?>">
                             <div class="flex-shrink-0 me-3">
                                 <?php if (!empty($conversation['profile_image'])): ?>
                                     <img src="../uploads/profiles/<?= htmlspecialchars($conversation['profile_image']) ?>" 
                                          class="conversation-avatar" alt="Profile">
                                 <?php else: ?>
                                     <div class="conversation-avatar-initials">
                                         <?= strtoupper($conversation['username'][0]) ?>
                                     </div>
                                 <?php endif; ?>
                             </div>
                             <div class="conversation-info">
                                 <div class="d-flex justify-content-between align-items-start">
                                     <div class="flex-grow-1 me-2" style="min-width: 0;">
                                         <div class="conversation-name">
                                             <?= htmlspecialchars($conversation['name'] ?: $conversation['username']) ?>
                                         </div>
                                         <div class="conversation-preview">
                                             <?= htmlspecialchars($conversation['last_message'] ?? 'No messages yet') ?>
                                         </div>
                                     </div>
                                     <div class="d-flex flex-column align-items-end flex-shrink-0">
                                         <?php if ($conversation['unread_count'] > 0): ?>
                                             <div class="unread-badge mb-1">
                                                 <?= $conversation['unread_count'] ?>
                                             </div>
                                         <?php endif; ?>
                                         <div class="conversation-time">
                                             <?php
                                             if ($conversation['last_message_time']) {
                                                 $time = strtotime($conversation['last_message_time']);
                                                 $now = time();
                                                 $diff = $now - $time;
                                                 
                                                 if ($diff < 60) {
                                                     echo 'Just now';
                                                 } elseif ($diff < 3600) {
                                                     echo floor($diff / 60) . 'm ago';
                                                 } elseif ($diff < 86400) {
                                                     echo floor($diff / 3600) . 'h ago';
                                                 } else {
                                                     echo date('M j', $time);
                                                 }
                                             } else {
                                                 echo '';
                                             }
                                             ?>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                         </div>
                     <?php endforeach; ?>
                 <?php endif; ?>
             </div>
             <div class="chat-container">
                 <div id="chatHeader" class="chat-header" style="display: none;">
                     <div class="d-flex align-items-center">
                         <div class="flex-shrink-0 me-3">
                             <div id="chatAvatar" class="conversation-avatar-initials"></div>
                         </div>
                         <div class="flex-grow-1">
                             <h5 class="mb-0" id="chatUsername"></h5>
                         </div>
                     </div>
                 </div>
 
                 <div id="chatMessages" class="chat-messages">
                     <div class="no-conversation">
                         <div>
                             <i class="bi bi-chat-dots" style="font-size: 3rem; opacity: 0.5;"></i>
                             <h4 class="mt-3">Select a conversation</h4>
                             <p class="text-light">Choose a conversation from the list to start messaging</p>
                         </div>
                     </div>
                 </div>
 
                 <div id="chatInput" class="chat-input" style="display: none;">
                     <form id="messageForm" class="d-flex">
                         <input type="text" class="form-control me-2" id="messageInput" 
                                placeholder="Type a message..." autocomplete="off">
                         <button type="submit" class="btn btn-primary">
                             <i class="bi bi-send"></i>
                         </button>
                     </form>
                 </div>
             </div>
         </div>
     </div>

         <script>
         let currentConversation = null;
         let messagePollingInterval = null;
        
        // Handle conversation selection
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.addEventListener('click', function() {
                const userId = this.dataset.userId;
                const username = this.dataset.username;
                
                // Update active state
                document.querySelectorAll('.conversation-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                
                // Show chat interface
                document.getElementById('chatHeader').style.display = 'block';
                document.getElementById('chatInput').style.display = 'block';
                document.getElementById('chatUsername').textContent = username;
                document.getElementById('chatAvatar').textContent = username.charAt(0).toUpperCase();
                
                                 // Load messages
                 loadMessages(userId);
                 currentConversation = userId;
                 
                 // Start polling for new messages
                 startMessagePolling(userId);
                 
                 // Mark as read
                 markConversationAsRead(userId);
            });
        });
        
        // Handle message form submission
        document.getElementById('messageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if (message && currentConversation) {
                sendMessage(currentConversation, message);
                messageInput.value = '';
            }
        });
        
        function loadMessages(userId) {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
            
            fetch(`../api/get-messages.php?user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayMessages(data.messages);
                    } else {
                        chatMessages.innerHTML = '<div class="text-center py-4 text-danger">Error loading messages</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    chatMessages.innerHTML = '<div class="text-center py-4 text-danger">Error loading messages</div>';
                });
        }
        
        function displayMessages(messages) {
            const chatMessages = document.getElementById('chatMessages');
            
            if (messages.length === 0) {
                chatMessages.innerHTML = '<div class="text-center py-4 text-white">No messages yet. Start the conversation!</div>';
                return;
            }
            
            chatMessages.innerHTML = messages.map(message => `
                <div class="message ${message.sender_id == <?= $user_id ?> ? 'sent' : 'received'}">
                    <div class="message-bubble">
                        <div>${escapeHtml(message.message)}</div>
                        <div class="message-time">
                            ${formatMessageTime(message.created_at)}
                        </div>
                    </div>
                </div>
            `).join('');
            
            // Scroll to bottom
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
                 function sendMessage(receiverId, message) {
             console.log('Sending message to:', receiverId, message);
             // Add the message to the chat immediately for instant feedback
             const chatMessages = document.getElementById('chatMessages');
             const currentTime = new Date();
             const messageHtml = `
                 <div class="message sent">
                     <div class="message-bubble">
                         <div>${escapeHtml(message)}</div>
                         <div class="message-time">Just now</div>
                     </div>
                 </div>
             `;
             
             // Add the message to the chat
             chatMessages.insertAdjacentHTML('beforeend', messageHtml);
             
             // Scroll to bottom to show the new message
             chatMessages.scrollTop = chatMessages.scrollHeight;
             
             // Update the conversation preview in the list
             updateConversationPreview(receiverId, message);
             
             // Send the message to the server
             fetch('../api/send-message.php', {
                 method: 'POST',
                 headers: {
                     'Content-Type': 'application/json',
                 },
                 body: JSON.stringify({
                     receiver_id: receiverId,
                     message: message
                 })
             })
             .then(response => response.json())
             .then(data => {
                 console.log('Send message response:', data);
                 if (!data.success) {
                     // If sending failed, remove the message from the chat
                     const lastMessage = chatMessages.querySelector('.message.sent:last-child');
                     if (lastMessage) {
                         lastMessage.remove();
                     }
                     showToast('Failed to send message: ' + data.message, 'error');
                 }
             })
             .catch(error => {
                 console.error('Error:', error);
                 const lastMessage = chatMessages.querySelector('.message.sent:last-child');
                 if (lastMessage) lastMessage.remove();
                 showToast('Failed to send message', 'error');
             });
         }
        
        function markConversationAsRead(userId) {
            fetch('../api/mark-messages-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    sender_id: userId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update unread badge
                    const conversationItem = document.querySelector(`[data-user-id="${userId}"]`);
                    const unreadBadge = conversationItem.querySelector('.unread-badge');
                    if (unreadBadge) {
                        unreadBadge.remove();
                    }
                }
            })
            .catch(error => {
                console.error('Error marking as read:', error);
            });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
                 function updateConversationPreview(userId, message) {
             console.log('Updating conversation preview for:', userId, message);
             // Find the conversation item in the list
             const conversationItem = document.querySelector(`.conversation-list [data-user-id="${userId}"]`);
             console.log('Found conversation item:', conversationItem);
             if (conversationItem) {
                 // Update the preview message
                 const previewElement = conversationItem.querySelector('.conversation-preview');
                 if (previewElement) {
                     previewElement.textContent = message;
                     console.log('Updated preview message');
                 }
                 
                 // Update the time to "Just now"
                 const timeElement = conversationItem.querySelector('.conversation-time');
                 if (timeElement) {
                     timeElement.textContent = 'Just now';
                     console.log('Updated time to Just now');
                 }
                 
                 // Move this conversation to the top of the list
                 const conversationList = document.querySelector('.conversation-list');
                 conversationList.insertBefore(conversationItem, conversationList.firstChild);
                 console.log('Moved conversation to top');
             } else {
                 console.log('No conversation item found for user:', userId);
             }
         }
         
         function formatMessageTime(timestamp) {
             const date = new Date(timestamp);
             const now = new Date();
             const diffMs = now - date;
             const diffMins = Math.floor(diffMs / 60000);
             const diffHours = Math.floor(diffMs / 3600000);
             const diffDays = Math.floor(diffMs / 86400000);
             
             if (diffMins < 1) return 'Just now';
             if (diffMins < 60) return `${diffMins}m ago`;
             if (diffHours < 24) return `${diffHours}h ago`;
             if (diffDays < 7) return `${diffDays}d ago`;
             return date.toLocaleDateString();
         }
         
         // Start polling for new messages
         function startMessagePolling(userId) {
             // Clear any existing polling
             if (messagePollingInterval) {
                 clearInterval(messagePollingInterval);
             }
             
             // Start polling every 1 second for faster updates
             messagePollingInterval = setInterval(() => {
                 checkForNewMessages(userId);
             }, 1000);
         }
         
         // Stop polling for messages
         function stopMessagePolling() {
             if (messagePollingInterval) {
                 clearInterval(messagePollingInterval);
                 messagePollingInterval = null;
             }
         }
         
         // Check for new messages
         function checkForNewMessages(userId) {
             if (!currentConversation || currentConversation != userId) {
                 return; // Only check if we're still in the same conversation
             }
             
             fetch(`../api/get-messages.php?user_id=${userId}`)
                 .then(response => response.json())
                 .then(data => {
                     if (data.success) {
                         // Get current message count
                         const chatMessages = document.getElementById('chatMessages');
                         const currentMessages = chatMessages.querySelectorAll('.message');
                         
                         // If we have new messages, update the display
                         if (data.messages.length > currentMessages.length) {
                             console.log('New messages detected, updating chat...');
                             displayMessages(data.messages);
                             
                             // Mark as read
                             markConversationAsRead(userId);
                             
                             // Update conversation preview with latest message
                             if (data.messages.length > 0) {
                                 const latestMessage = data.messages[data.messages.length - 1];
                                 if (latestMessage.sender_id != <?= $user_id ?>) {
                                     updateConversationPreview(userId, latestMessage.message);
                                 }
                             }
                         }
                     }
                 })
                 .catch(error => {
                     console.error('Error checking for new messages:', error);
                 });
         }
    </script>

    <!-- New Message Modal -->
    <div class="modal fade" id="newMessageModal" tabindex="-1" aria-labelledby="newMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="newMessageModalLabel">Start New Conversation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="friendSearch" class="form-label">Search Friends</label>
                        <input type="text" class="form-control" id="friendSearch" placeholder="Type to search friends...">
                    </div>
                    <div id="friendsList" class="list-group">
                        <!-- Friends will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Load friends for new message modal
        document.getElementById('newMessageModal').addEventListener('show.bs.modal', function() {
            console.log('Modal opening, loading friends...');
            loadFriendsForNewMessage();
        });

        // Handle friend search
        document.getElementById('friendSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const friendItems = document.querySelectorAll('#friendsList .friend-item');
            
            friendItems.forEach(item => {
                const friendName = item.querySelector('.friend-name').textContent.toLowerCase();
                const friendUsername = item.querySelector('.friend-username').textContent.toLowerCase();
                
                if (friendName.includes(searchTerm) || friendUsername.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        function loadFriendsForNewMessage() {
            console.log('Loading friends for new message...');
            const friendsList = document.getElementById('friendsList');
            friendsList.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
            
            fetch('../api/get-friends.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayFriendsForNewMessage(data.friends);
                    } else {
                        friendsList.innerHTML = '<div class="text-center py-4 text-danger">Error loading friends</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    friendsList.innerHTML = '<div class="text-center py-4 text-danger">Error loading friends</div>';
                });
        }

        function displayFriendsForNewMessage(friends) {
            console.log('Displaying friends:', friends);
            const friendsList = document.getElementById('friendsList');
            
            if (friends.length === 0) {
                friendsList.innerHTML = `
                    <div class="text-center py-4">
                        <i class="bi bi-people text-white" style="font-size: 2rem;"></i>
                        <h6 class="mt-2">No friends yet</h6>
                        <p class="text-white">Add some friends to start messaging them!</p>
                        <a href="../search-users.php" class="btn btn-primary btn-sm">Find Friends</a>
                    </div>
                `;
                return;
            }
            
            friendsList.innerHTML = friends.map(friend => `
                <div class="friend-item list-group-item list-group-item-action bg-dark border-secondary" 
                     data-user-id="${friend.id}" 
                     data-username="${friend.username}"
                     style="cursor: pointer;">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            ${friend.profile_image 
                                ? `<img src="../uploads/profiles/${friend.profile_image}" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">`
                                : `<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: bold;">
                                    ${friend.username.charAt(0).toUpperCase()}
                                   </div>`
                            }
                        </div>
                        <div class="flex-grow-1">
                            <div class="friend-name text-white fw-bold">${friend.name || friend.username}</div>
                            <div class="friend-username text-white">@${friend.username}</div>
                        </div>
                        <div class="flex-shrink-0">
                            <i class="bi bi-chevron-right text-white"></i>
                        </div>
                    </div>
                </div>
            `).join('');
            
                         // Add click handlers for friend selection
             document.querySelectorAll('.friend-item').forEach(item => {
                 item.addEventListener('click', function() {
                     console.log('Friend item clicked:', this.dataset);
                     const userId = this.dataset.userId;
                     const username = this.dataset.username;
                     
                     // Start conversation with this friend first
                     startConversation(userId, username);
                     
                     // Close modal after a short delay to ensure the conversation is set up
                     setTimeout(() => {
                         const modal = bootstrap.Modal.getInstance(document.getElementById('newMessageModal'));
                         if (modal) {
                             modal.hide();
                         }
                         // Remove focus from modal to prevent accessibility issues
                         document.activeElement?.blur();
                     }, 100);
                 });
             });
        }

                 function startConversation(userId, username) {
             console.log('Starting conversation with:', userId, username);
             // Check if conversation already exists in the conversation list
             const existingConversation = document.querySelector('.conversation-list [data-user-id="' + userId + '"]');
             
             if (existingConversation) {
                 console.log('Existing conversation found, clicking on it');
                 // If conversation exists, just click on it
                 existingConversation.click();
             } else {
                                  console.log('Creating new conversation item');
                 // Create a new conversation item
                 const conversationList = document.querySelector('.conversation-list');
                 console.log('Conversation list found:', conversationList);
                 const newConversationItem = document.createElement('div');
                                 newConversationItem.className = 'conversation-item d-flex align-items-center';
                 newConversationItem.setAttribute('data-user-id', userId);
                 newConversationItem.setAttribute('data-username', username);
                 console.log('New conversation item created:', newConversationItem);
                
                                 newConversationItem.innerHTML = `
                     <div class="flex-shrink-0 me-3">
                         <div class="conversation-avatar-initials">
                             ${username.charAt(0).toUpperCase()}
                         </div>
                     </div>
                     <div class="conversation-info">
                         <div class="d-flex justify-content-between align-items-start">
                             <div class="flex-grow-1 me-2" style="min-width: 0;">
                                 <div class="conversation-name">${username}</div>
                                 <div class="conversation-preview">No messages yet</div>
                             </div>
                             <div class="d-flex flex-column align-items-end flex-shrink-0">
                                 <div class="conversation-time"></div>
                             </div>
                         </div>
                     </div>
                 `;
                
                                 // Add click handler to new conversation
                 newConversationItem.addEventListener('click', function() {
                     console.log('New conversation item clicked');
                     const userId = this.dataset.userId;
                     const username = this.dataset.username;
                     
                     // Update active state
                     document.querySelectorAll('.conversation-item').forEach(i => i.classList.remove('active'));
                     this.classList.add('active');
                     
                     // Show chat interface
                     document.getElementById('chatHeader').style.display = 'block';
                     document.getElementById('chatInput').style.display = 'block';
                     document.getElementById('chatUsername').textContent = username;
                     document.getElementById('chatAvatar').textContent = username.charAt(0).toUpperCase();
                     
                     // Load messages (will be empty for new conversation)
                     loadMessages(userId);
                     currentConversation = userId;
                     
                     // Start polling for new messages
                     startMessagePolling(userId);
                     
                     // Mark as read
                     markConversationAsRead(userId);
                 });
                
                                 // Add to the top of the conversation list
                 const emptyState = conversationList.querySelector('.empty-state');
                 if (emptyState) {
                     emptyState.remove();
                 }
                 
                 conversationList.insertBefore(newConversationItem, conversationList.firstChild);
                 console.log('New conversation item added to list');
                 console.log('Conversation list now contains:', conversationList.children.length, 'items');
                 
                 // Click on the new conversation
                 console.log('Clicking on new conversation item');
                 newConversationItem.click();
            }
        }
    </script>
</body>
</html> 