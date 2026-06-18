UPDATE car_listings
SET
  lease_months_left = CASE
    WHEN lease_takeover = 1 AND lease_end_date IS NOT NULL AND lease_end_date > CURDATE()
      THEN TIMESTAMPDIFF(MONTH, CURDATE(), lease_end_date) + IF(DAY(lease_end_date) > DAY(CURDATE()), 1, 0)
    ELSE lease_months_left
  END,
  price = CASE
    WHEN lease_takeover = 1 THEN COALESCE(lease_down_payment, 0)
    ELSE price
  END;
