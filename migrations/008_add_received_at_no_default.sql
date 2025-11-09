-- Add a nullable received_at column (no non-constant default) then backfill from created_at
ALTER TABLE tickets ADD COLUMN received_at DATETIME;

-- Backfill: use created_at where present, otherwise current timestamp
UPDATE tickets SET received_at = COALESCE(created_at, datetime('now')) WHERE received_at IS NULL;
