-- Add ticket linkage to factures for per-ticket PDF link visibility
PRAGMA foreign_keys = OFF;

ALTER TABLE factures ADD COLUMN ticket_id INTEGER;

-- Optional index to speed up lookups by ticket
CREATE INDEX IF NOT EXISTS idx_factures_ticket ON factures (ticket_id);

PRAGMA foreign_keys = ON;
