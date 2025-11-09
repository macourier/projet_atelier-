BEGIN TRANSACTION;

-- 1) Create new tickets table without 'description' and with 'received_at'
CREATE TABLE IF NOT EXISTS tickets_new (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  client_id INTEGER NOT NULL,
  velo_id INTEGER,
  status TEXT DEFAULT 'open',
  total_ht NUMERIC DEFAULT 0,
  total_tva NUMERIC DEFAULT 0,
  total_ttc NUMERIC DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME,
  received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  FOREIGN KEY (velo_id) REFERENCES velos(id) ON DELETE SET NULL
);

-- 2) Copy existing data: set received_at to previous created_at (best-effort)
INSERT INTO tickets_new (id, client_id, velo_id, status, total_ht, total_tva, total_ttc, created_at, updated_at, received_at)
SELECT id, client_id, velo_id, status, total_ht, total_tva, total_ttc, created_at, updated_at, COALESCE(created_at, CURRENT_TIMESTAMP) FROM tickets;

-- 3) Drop old table and rename new
DROP TABLE tickets;
ALTER TABLE tickets_new RENAME TO tickets;

-- 4) Re-create indexes (if needed)
CREATE INDEX IF NOT EXISTS idx_tickets_client ON tickets (client_id);

COMMIT;
