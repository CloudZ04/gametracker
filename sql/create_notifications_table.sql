-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    from_user_id INT NOT NULL,
    type ENUM('follow', 'friend_request', 'friend_accepted', 'friend_declined') NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_from_user_id (from_user_id),
    INDEX idx_type (type),
    INDEX idx_is_read (is_read)
);

-- Add friend_request status to user_relationships table if it doesn't exist
-- (This would need to be done manually if the ENUM doesn't include 'friend_request')
-- ALTER TABLE user_relationships MODIFY COLUMN status ENUM('following', 'friends', 'friend_request') NOT NULL; 