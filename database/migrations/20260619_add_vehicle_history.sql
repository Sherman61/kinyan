ALTER TABLE car_listings
  ADD COLUMN vehicle_history ENUM('Used','New') NOT NULL DEFAULT 'Used' AFTER clean_title,
  ADD INDEX idx_vehicle_history (vehicle_history);
