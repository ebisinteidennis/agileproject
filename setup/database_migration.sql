-- Database migration script for file upload functionality
-- Run this if your database doesn't have the file-related columns

-- Check if file columns exist in messages table and add if missing
SELECT COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'messages' 
  AND COLUMN_NAME IN ('file_path', 'file_name', 'file_size', 'file_type');

-- Add file-related columns to messages table if they don't exist
-- (These should already exist based on your provided schema, but including for completeness)

-- ALTER TABLE `messages` 
-- ADD COLUMN `file_path` varchar(500) DEFAULT NULL AFTER `created_at`,
-- ADD COLUMN `file_name` varchar(255) DEFAULT NULL AFTER `file_path`,
-- ADD COLUMN `file_size` varchar(50) DEFAULT NULL AFTER `file_name`,
-- ADD COLUMN `file_type` varchar(100) DEFAULT NULL AFTER `file_size`;

-- Ensure allow_file_upload column exists in subscriptions table
-- (This should already exist based on your schema)

-- ALTER TABLE `subscriptions` 
-- ADD COLUMN `allow_file_upload` tinyint(1) NOT NULL DEFAULT 0 AFTER `visitor_limit`;

-- Update existing subscription plans to enable file upload for Standard and Premium
UPDATE `subscriptions` 
SET `allow_file_upload` = 1 
WHERE `name` IN ('Standard', 'Premium');

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_messages_file_path` ON `messages` (`file_path`);
CREATE INDEX IF NOT EXISTS `idx_messages_visitor_widget` ON `messages` (`visitor_id`, `widget_id`);
CREATE INDEX IF NOT EXISTS `idx_messages_user_created` ON `messages` (`user_id`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_visitors_user_active` ON `visitors` (`user_id`, `last_active`);

-- Create uploads directory structure (to be done manually on server)
-- mkdir -p ../uploads/messages
-- chmod 755 ../uploads
-- chmod 755 ../uploads/messages

-- Verify subscription plans have correct file upload settings
SELECT id, name, allow_file_upload, message_limit, visitor_limit 
FROM subscriptions 
ORDER BY price ASC;