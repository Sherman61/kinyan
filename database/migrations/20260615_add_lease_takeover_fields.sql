ALTER TABLE car_listings
  ADD COLUMN lease_takeover TINYINT(1) NOT NULL DEFAULT 0 AFTER clean_title,
  ADD COLUMN lease_months_left SMALLINT UNSIGNED NULL AFTER lease_takeover,
  ADD COLUMN lease_monthly_payment DECIMAL(10,2) NULL AFTER lease_months_left,
  ADD COLUMN lease_down_payment DECIMAL(10,2) NULL AFTER lease_monthly_payment,
  ADD COLUMN lease_mileage_allowance INT UNSIGNED NULL AFTER lease_down_payment,
  ADD COLUMN lease_miles_used INT UNSIGNED NULL AFTER lease_mileage_allowance,
  ADD COLUMN lease_transfer_fee DECIMAL(10,2) NULL AFTER lease_miles_used,
  ADD COLUMN lease_company VARCHAR(120) NULL AFTER lease_transfer_fee,
  ADD COLUMN lease_end_date DATE NULL AFTER lease_company;
