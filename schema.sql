-- schema.sql for bh_system
CREATE DATABASE IF NOT EXISTS bh_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bh_system;

-- users table
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('owner','student') NOT NULL DEFAULT 'student',
  contact VARCHAR(128) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- posts table
CREATE TABLE IF NOT EXISTS posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status VARCHAR(32) NOT NULL DEFAULT 'inactive',
  payment_methods VARCHAR(255) DEFAULT NULL,
  location VARCHAR(255) DEFAULT NULL,
  amenities JSON DEFAULT NULL,                 -- use TEXT if your MySQL doesn't support JSON
  latitude DECIMAL(10,7) DEFAULT NULL,
  longitude DECIMAL(10,7) DEFAULT NULL,
  room_count INT DEFAULT 1,
  room_type VARCHAR(64) DEFAULT NULL,
  contact VARCHAR(128) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- post_images table
CREATE TABLE IF NOT EXISTS post_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- messages table
CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  student_id INT NOT NULL,
  owner_id INT NOT NULL,
  content TEXT,
  owner_reply TEXT DEFAULT NULL,
  student_read TINYINT(1) NOT NULL DEFAULT 0,
  owner_read TINYINT(1) NOT NULL DEFAULT 0,
  is_resolved TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id)   REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (owner_id)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- booking table
CREATE TABLE IF NOT EXISTS booking (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  student_id INT NOT NULL,
  owner_id INT NOT NULL,
  occupants INT DEFAULT 1,
  total_price DECIMAL(10,2) DEFAULT 0.00,
  guest_name VARCHAR(255) NOT NULL,
  guest_email VARCHAR(255) NOT NULL,
  guest_contact VARCHAR(128) NOT NULL,
  note TEXT DEFAULT NULL,
  payment_method VARCHAR(64) DEFAULT 'cash',
  payment_status VARCHAR(64) DEFAULT 'unpaid',
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id)   REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (owner_id)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- booking_payments table
CREATE TABLE IF NOT EXISTS booking_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  provider VARCHAR(64),
  amount DECIMAL(10,2) NOT NULL,
  currency VARCHAR(8) DEFAULT 'PHP',
  transaction_id VARCHAR(255),
  status VARCHAR(64) DEFAULT 'completed',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES booking(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- If your MySQL version does not support JSON, change 'amenities JSON' to 'amenities TEXT' and store JSON as string.
