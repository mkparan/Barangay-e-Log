-- Database Migration: Add Walk-in Columns to Appointments
-- Author: Antigravity
-- Date: 2025-12-07

USE elog_barangay;

-- 1. Modify citizen_id to be NULLABLE
ALTER TABLE appointments MODIFY citizen_id INT NULL;

-- 2. Add columns for walk-in details
ALTER TABLE appointments ADD COLUMN walkin_name VARCHAR(150) NULL AFTER citizen_id;
ALTER TABLE appointments ADD COLUMN walkin_contact VARCHAR(50) NULL AFTER walkin_name;

-- 3. (Optional) Update existing walk-in appointments to use new columns (Data Migration)
-- Note: This is complex because we need to join with citizens table. 
-- For safety, we will just start using the new columns for NEW appointments.
-- Existing "WALKIN-" citizens will remain until manually cleaned up.
