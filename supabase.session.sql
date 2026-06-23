SELECT
  ticket_id,
  price,
  pg_typeof(price) AS db_type,
  length(price) AS char_length,
  price ~ '^[0-9]+$' AS is_clean_digits,
  status
FROM "Tickets"
WHERE status != 'cancelled'
ORDER BY ticket_id;