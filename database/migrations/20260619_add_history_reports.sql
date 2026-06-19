ALTER TABLE car_listings
  MODIFY accident_history TEXT NULL,
  ADD COLUMN history_report_file VARCHAR(80) NULL AFTER accident_history,
  ADD COLUMN history_report_name VARCHAR(190) NULL AFTER history_report_file,
  ADD COLUMN history_report_uploaded_at DATETIME NULL AFTER history_report_name;
