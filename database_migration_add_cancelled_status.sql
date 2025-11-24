-- Add 'cancelled' status to appointments table
ALTER TABLE appointments MODIFY COLUMN status ENUM('pending','approved','rescheduled','declined','completed','cancelled') DEFAULT 'pending';

