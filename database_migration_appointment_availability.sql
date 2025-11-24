-- Create appointment availability table
CREATE TABLE IF NOT EXISTS appointment_availability (
  availability_id INT AUTO_INCREMENT PRIMARY KEY,
  available_date DATE NOT NULL,
  morning_slots INT DEFAULT 25,
  afternoon_slots INT DEFAULT 25,
  morning_available TINYINT(1) DEFAULT 1,
  afternoon_available TINYINT(1) DEFAULT 1,
  is_available TINYINT(1) DEFAULT 1,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  UNIQUE KEY unique_date (available_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add processed_by to appointments table
ALTER TABLE appointments ADD COLUMN processed_by INT NULL DEFAULT NULL AFTER official_id;
ALTER TABLE appointments ADD FOREIGN KEY (processed_by) REFERENCES users(user_id) ON DELETE SET NULL;

-- Add time_slot to appointments (morning/afternoon)
ALTER TABLE appointments ADD COLUMN time_slot ENUM('morning','afternoon') NULL DEFAULT NULL AFTER preferred_end;

