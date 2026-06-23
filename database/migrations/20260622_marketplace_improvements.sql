CREATE TABLE IF NOT EXISTS saved_listings (
  user_id INT UNSIGNED NOT NULL,
  car_listing_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, car_listing_id),
  INDEX idx_saved_listing (car_listing_id),
  CONSTRAINT fk_saved_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_saved_car FOREIGN KEY (car_listing_id) REFERENCES car_listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS account_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  purpose ENUM('password_reset','email_verify') NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_account_tokens_user_purpose (user_id, purpose, expires_at),
  INDEX idx_account_tokens_expires (expires_at),
  CONSTRAINT fk_account_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'reports' AND column_name = 'status') = 0, "ALTER TABLE reports ADD COLUMN status ENUM('open','investigating','resolved','dismissed') NOT NULL DEFAULT 'open' AFTER details", 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'reports' AND column_name = 'admin_notes') = 0, 'ALTER TABLE reports ADD COLUMN admin_notes TEXT NULL AFTER status', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'reports' AND column_name = 'resolved_by') = 0, 'ALTER TABLE reports ADD COLUMN resolved_by INT UNSIGNED NULL AFTER admin_notes', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'reports' AND column_name = 'resolved_at') = 0, 'ALTER TABLE reports ADD COLUMN resolved_at DATETIME NULL AFTER resolved_by', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'reports' AND index_name = 'idx_reports_status_created') = 0, 'CREATE INDEX idx_reports_status_created ON reports (status, created_at)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'car_listings' AND index_name = 'idx_status_price') = 0, 'CREATE INDEX idx_status_price ON car_listings (status, price)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'car_listings' AND index_name = 'idx_status_year_mileage') = 0, 'CREATE INDEX idx_status_year_mileage ON car_listings (status, year, mileage)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'car_listings' AND index_name = 'idx_status_lease_payment') = 0, 'CREATE INDEX idx_status_lease_payment ON car_listings (status, lease_takeover, lease_monthly_payment)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'car_listings' AND index_name = 'idx_status_location') = 0, 'CREATE INDEX idx_status_location ON car_listings (status, state, city)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO site_settings (setting_key, setting_value) VALUES ('listing_expiration_days', '45') ON DUPLICATE KEY UPDATE setting_value = setting_value;
