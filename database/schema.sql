CREATE DATABASE IF NOT EXISTS kinyan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kinyan;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  trust_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS car_listings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  make VARCHAR(80) NOT NULL,
  model VARCHAR(80) NOT NULL,
  trim VARCHAR(80) NULL,
  year SMALLINT UNSIGNED NOT NULL,
  mileage INT UNSIGNED NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  vin VARCHAR(32) NULL,
  exterior_color VARCHAR(60) NULL,
  interior_color VARCHAR(60) NULL,
  body_type VARCHAR(40) NOT NULL,
  transmission VARCHAR(40) NOT NULL,
  drivetrain VARCHAR(60) NULL,
  fuel_type VARCHAR(40) NOT NULL,
  engine VARCHAR(80) NULL,
  condition_status VARCHAR(40) NOT NULL,
  accident_history VARCHAR(120) NULL,
  clean_title TINYINT(1) NOT NULL DEFAULT 0,
  lease_takeover TINYINT(1) NOT NULL DEFAULT 0,
  lease_months_left SMALLINT UNSIGNED NULL,
  lease_monthly_payment DECIMAL(10,2) NULL,
  lease_down_payment DECIMAL(10,2) NULL,
  lease_mileage_allowance INT UNSIGNED NULL,
  lease_miles_used INT UNSIGNED NULL,
  lease_transfer_fee DECIMAL(10,2) NULL,
  lease_company VARCHAR(120) NULL,
  lease_end_date DATE NULL,
  description TEXT NOT NULL,
  city VARCHAR(100) NOT NULL,
  state CHAR(2) NOT NULL,
  zip VARCHAR(12) NULL,
  seller_name VARCHAR(120) NOT NULL,
  seller_phone VARCHAR(40) NOT NULL,
  seller_email VARCHAR(190) NULL,
  preferred_contact_method VARCHAR(20) NOT NULL DEFAULT 'Any',
  status ENUM('pending','active','rejected','inactive','sold','expired') NOT NULL DEFAULT 'pending',
  featured TINYINT(1) NOT NULL DEFAULT 0,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status_created (status, created_at),
  INDEX idx_make_model (make, model),
  INDEX idx_price (price),
  INDEX idx_year (year),
  CONSTRAINT fk_cars_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS car_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  car_listing_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  image_title VARCHAR(160) NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_car_sort (car_listing_id, sort_order),
  CONSTRAINT fk_images_car FOREIGN KEY (car_listing_id) REFERENCES car_listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wanted_posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  preferred_make VARCHAR(80) NULL,
  preferred_model VARCHAR(80) NULL,
  min_year SMALLINT UNSIGNED NULL,
  max_year SMALLINT UNSIGNED NULL,
  max_mileage INT UNSIGNED NULL,
  min_budget DECIMAL(10,2) NULL,
  max_budget DECIMAL(10,2) NULL,
  preferred_body_type VARCHAR(40) NULL,
  preferred_transmission VARCHAR(40) NULL,
  preferred_fuel_type VARCHAR(40) NULL,
  must_have_clean_title TINYINT(1) NOT NULL DEFAULT 0,
  location VARCHAR(140) NOT NULL,
  travel_distance INT UNSIGNED NOT NULL DEFAULT 25,
  buyer_name VARCHAR(120) NOT NULL,
  buyer_phone VARCHAR(40) NOT NULL,
  buyer_email VARCHAR(190) NULL,
  preferred_contact_method VARCHAR(20) NOT NULL DEFAULT 'Any',
  description TEXT NOT NULL,
  status ENUM('pending','active','rejected','inactive','sold','expired') NOT NULL DEFAULT 'pending',
  views INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_wanted_status_created (status, created_at),
  INDEX idx_wanted_make_model (preferred_make, preferred_model),
  CONSTRAINT fk_wanted_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  target_type ENUM('car','wanted') NOT NULL,
  target_id INT UNSIGNED NOT NULL,
  reason VARCHAR(120) NOT NULL,
  details TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reports_target (target_type, target_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vehicle_makes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contact_clicks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  target_type ENUM('car','wanted') NOT NULL,
  target_id INT UNSIGNED NOT NULL,
  method VARCHAR(20) NOT NULL,
  ip_address VARCHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_contact_target (target_type, target_id)
) ENGINE=InnoDB;

INSERT INTO site_settings (setting_key, setting_value) VALUES
('auto_approve_listings', '0'),
('site_name', 'Kinyan'),
('support_email', 'support@kinyan.live')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT IGNORE INTO vehicle_makes (name) VALUES
('Toyota'),('Honda'),('Ford'),('Chevrolet'),('Nissan'),('Hyundai'),('Kia'),('BMW'),('Mercedes-Benz'),('Lexus'),('Subaru'),('Tesla'),('Jeep'),('Mazda'),('Volkswagen');

-- Create a default admin by running this after replacing the hash with the output of:
-- php -r "echo password_hash('change-this-password', PASSWORD_DEFAULT), PHP_EOL;"
-- INSERT INTO users (name, email, phone, password_hash, role) VALUES ('Kinyan Admin', 'admin@kinyan.live', '', 'PASTE_HASH_HERE', 'admin');
