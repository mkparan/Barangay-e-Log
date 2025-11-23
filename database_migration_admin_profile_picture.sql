-- Add profile_picture column to users table
-- Run this SQL to add the profile picture feature for admins/officials

ALTER TABLE users 
ADD COLUMN profile_picture VARCHAR(255) NULL DEFAULT NULL 
AFTER email;

-- This column will store the file path to the uploaded profile picture
-- Example: 'uploads/profiles/admin_123_profile.jpg'

