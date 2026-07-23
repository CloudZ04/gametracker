-- Add theme column to users table
-- Run this SQL script to add theme support

ALTER TABLE users ADD COLUMN theme VARCHAR(50) DEFAULT 'default' AFTER about;

-- Update existing users to have the default theme
UPDATE users SET theme = 'default' WHERE theme IS NULL; 