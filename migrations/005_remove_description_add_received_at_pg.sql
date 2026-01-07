-- Migration 005: Remove description and add received_at to tickets (PostgreSQL version)
BEGIN;

-- 1) Create new tickets table without 'description' and with 'received_at'
-- Including bike fields from migration 004
CREATE TABLE IF NOT EXISTS tickets_new (
  id SERIAL PRIMARY KEY,
  client_id INTEGER NOT NULL,
  velo_id INTEGER,
  bike_brand TEXT,
  bike_model TEXT,
  bike_serial TEXT,
  bike_notes TEXT,
  status TEXT DEFAULT 'open',
  total_ht NUMERIC DEFAULT 0,
  total_tva NUMERIC DEFAULT 0,
  total_ttc NUMERIC DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP,
  received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  FOREIGN KEY (velo_id) REFERENCES velos(id) ON DELETE SET NULL
);

-- 2) Copy existing data: set received_at to previous created_at (best-effort)
INSERT INTO tickets_new (id, client_id, velo_id, bike_brand, bike_model, bike_serial, bike_notes, status, total_ht, total_tva, total_ttc, created_at, updated_at, received_at)
SELECT id, client_id, velo_id, bike_brand, bike_model, bike_serial, bike_notes, status, total_ht, total_tva, total_ttc, created_at, updated_at, COALESCE(created_at, CURRENT_TIMESTAMP) 
FROM tickets;

-- 3) Drop old table and rename new
DROP TABLE tickets;
ALTER TABLE tickets_new RENAME TO tickets;

-- 4) Re-create indexes
CREATE INDEX IF NOT EXISTS idx_tickets_client ON tickets (client_id);

COMMIT;