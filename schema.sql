-- schema.sql
CREATE DATABASE IF NOT EXISTS elog_barangay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE elog_barangay;

-- users: officials and admin accounts
CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  role ENUM('admin','official','secretary','sk_official','captain') NOT NULL DEFAULT 'official',
  contact_number VARCHAR(30),
  email VARCHAR(120),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- citizens: CIN-based records
CREATE TABLE IF NOT EXISTS citizens (
  citizen_id INT AUTO_INCREMENT PRIMARY KEY,
  cin VARCHAR(30) UNIQUE NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(100),
  last_name VARCHAR(100) NOT NULL,
  birth_date DATE,
  gender VARCHAR(20),
  contact_number VARCHAR(30),
  email VARCHAR(120),
  address TEXT,
  gov_affiliations TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- announcements
CREATE TABLE IF NOT EXISTS announcements (
  announcement_id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  posted_by INT,
  is_published TINYINT(1) DEFAULT 1,
  publish_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expire_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (posted_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- duty roster
CREATE TABLE IF NOT EXISTS duty_roster (
  roster_id INT AUTO_INCREMENT PRIMARY KEY,
  official_id INT NOT NULL,
  duty_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  remarks VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (official_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- presence (check-in/out) - corrected to allow NULL timestamps
CREATE TABLE IF NOT EXISTS presence (
  presence_id INT AUTO_INCREMENT PRIMARY KEY,
  official_id INT NOT NULL,
  check_in TIMESTAMP NULL DEFAULT NULL,
  check_out TIMESTAMP NULL DEFAULT NULL,
  status ENUM('present','absent') DEFAULT 'present',
  notes VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (official_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- appointments
CREATE TABLE IF NOT EXISTS appointments (
  appointment_id INT AUTO_INCREMENT PRIMARY KEY,
  citizen_id INT NOT NULL,
  official_id INT DEFAULT NULL,
  service_type VARCHAR(150) NOT NULL,
  details TEXT,
  preferred_date DATE,
  preferred_start TIME,
  preferred_end TIME,
  status ENUM('pending','approved','rescheduled','declined','completed') DEFAULT 'pending',
  queue_number INT DEFAULT NULL,
  admin_notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (citizen_id) REFERENCES citizens(citizen_id) ON DELETE CASCADE,
  FOREIGN KEY (official_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- transactions (logbook)
CREATE TABLE IF NOT EXISTS transactions (
  transaction_id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT,
  citizen_id INT NOT NULL,
  processed_by INT,
  transaction_type VARCHAR(150),
  status ENUM('in_progress','completed','cancelled') DEFAULT 'in_progress',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL,
  FOREIGN KEY (citizen_id) REFERENCES citizens(citizen_id) ON DELETE CASCADE,
  FOREIGN KEY (processed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- documents
CREATE TABLE IF NOT EXISTS documents (
  document_id INT AUTO_INCREMENT PRIMARY KEY,
  transaction_id INT,
  citizen_id INT,
  document_type VARCHAR(150),
  file_path VARCHAR(255),
  signature_image_path VARCHAR(255),
  issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_revoked TINYINT(1) DEFAULT 0,
  FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE CASCADE,
  FOREIGN KEY (citizen_id) REFERENCES citizens(citizen_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- audit logs
CREATE TABLE IF NOT EXISTS audit_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_identifier VARCHAR(150),
  user_id INT NULL,
  action VARCHAR(255),
  entity VARCHAR(100),
  entity_id INT,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
