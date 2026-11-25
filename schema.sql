-- Fresh-install schema. Existing tables are migrated automatically via config.php / install.php.
CREATE DATABASE IF NOT EXISTS hulambee CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hulambee;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  full_name VARCHAR(100) NOT NULL DEFAULT '',
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL DEFAULT '',
  role ENUM('user','member','admin') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS equipment (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  summary VARCHAR(255) NOT NULL DEFAULT '',
  category VARCHAR(50) DEFAULT 'General',
  item_condition VARCHAR(50) NOT NULL DEFAULT 'Good',
  weight_limit VARCHAR(50) NOT NULL DEFAULT 'Not applicable',
  pickup_location VARCHAR(150) NOT NULL DEFAULT '',
  usage_notes TEXT NULL,
  status ENUM('available','borrowed','maintenance') NOT NULL DEFAULT 'available',
  image VARCHAR(255) NOT NULL DEFAULT 'assets/img/default.svg',
  quantity_total INT UNSIGNED NOT NULL DEFAULT 1,
  quantity_available INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  equipment_id INT NOT NULL,
  item_id INT NULL,
  requester_name VARCHAR(100) NULL,
  phone VARCHAR(30) NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  needed_by DATE NOT NULL,
  fulfillment ENUM('pickup','delivery') NOT NULL DEFAULT 'pickup',
  notes TEXT NULL,
  status ENUM('pending','approved','borrowed','declined','rejected','returned') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  borrowed_at DATETIME NULL,
  returned_at DATETIME NULL,
  CONSTRAINT fk_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_requests_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_meta (
  meta_key VARCHAR(64) PRIMARY KEY,
  meta_value VARCHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW items AS
SELECT id, name, description, summary, category, item_condition, weight_limit,
       pickup_location, usage_notes, status, image, quantity_total, quantity_available, created_at
FROM equipment;
