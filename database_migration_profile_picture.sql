-- Add profile_picture column to citizens table
-- Run this SQL to add the profile picture feature

ALTER TABLE citizens 
ADD COLUMN profile_picture VARCHAR(255) NULL DEFAULT NULL 
AFTER gov_affiliations;

-- This column will store the file path to the uploaded profile picture
-- Example: 'uploads/profiles/citizen_123_profile.jpg'

