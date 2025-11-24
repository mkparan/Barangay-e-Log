-- Add is_verified column to citizens table
ALTER TABLE citizens ADD COLUMN is_verified TINYINT(1) DEFAULT 0 AFTER is_active;

-- Set existing citizens as verified (optional - remove if you want to verify them manually)
-- UPDATE citizens SET is_verified = 1 WHERE is_verified = 0;

