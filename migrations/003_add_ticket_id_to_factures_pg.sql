-- Add ticket linkage to factures for per-ticket PDF link visibility (PostgreSQL version)
-- Note: This migration may fail if column already exists, which is fine (idempotent)

DO $$
BEGIN
    -- Check if column already exists
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name = 'factures' 
        AND column_name = 'ticket_id'
    ) THEN
        ALTER TABLE factures ADD COLUMN ticket_id INTEGER;
    END IF;
END $$;

-- Create index if it doesn't exist
CREATE INDEX IF NOT EXISTS idx_factures_ticket ON factures (ticket_id);