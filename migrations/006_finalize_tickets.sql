BEGIN TRANSACTION;

-- Finalize tickets migration: safely drop old tickets table and rename tickets_new -> tickets
-- Use PRAGMA foreign_keys=OFF to avoid FK constraint errors during the swap, then re-enable.

PRAGMA foreign_keys = OFF;

DROP TABLE IF EXISTS tickets;

-- If tickets_new exists (created by previous migration), rename it to tickets
ALTER TABLE IF EXISTS tickets_new RENAME TO tickets;

-- Recreate index if needed
CREATE INDEX IF NOT EXISTS idx_tickets_client ON tickets (client_id);

PRAGMA foreign_keys = ON;

COMMIT;
