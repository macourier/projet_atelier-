-- PostgreSQL initialization for PROJET_ATELIER – Facturation Vélo
-- This is the PostgreSQL-compatible version of 001_init.sql

-- users
CREATE TABLE IF NOT EXISTS users (
  id SERIAL PRIMARY KEY,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  roles TEXT DEFAULT 'ROLE_USER',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- clients
CREATE TABLE IF NOT EXISTS clients (
  id SERIAL PRIMARY KEY,
  name TEXT NOT NULL,
  address TEXT,
  email TEXT,
  phone TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP
);

-- velos (bikes)
CREATE TABLE IF NOT EXISTS velos (
  id SERIAL PRIMARY KEY,
  client_id INTEGER NOT NULL,
  brand TEXT,
  model TEXT,
  serial TEXT,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- prestations_catalogue
CREATE TABLE IF NOT EXISTS prestations_catalogue (
  id SERIAL PRIMARY KEY,
  code TEXT UNIQUE,
  label TEXT NOT NULL,
  prix_ht NUMERIC NOT NULL DEFAULT 0,
  tva NUMERIC NOT NULL DEFAULT 0
);

-- consommables_catalogue
CREATE TABLE IF NOT EXISTS consommables_catalogue (
  id SERIAL PRIMARY KEY,
  code TEXT UNIQUE,
  label TEXT NOT NULL,
  prix_ht NUMERIC NOT NULL DEFAULT 0,
  tva NUMERIC NOT NULL DEFAULT 0
);

-- tickets
CREATE TABLE IF NOT EXISTS tickets (
  id SERIAL PRIMARY KEY,
  client_id INTEGER NOT NULL,
  velo_id INTEGER,
  description TEXT,
  status TEXT DEFAULT 'open',
  total_ht NUMERIC DEFAULT 0,
  total_tva NUMERIC DEFAULT 0,
  total_ttc NUMERIC DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  FOREIGN KEY (velo_id) REFERENCES velos(id) ON DELETE SET NULL
);

-- ticket_prestations (lines)
CREATE TABLE IF NOT EXISTS ticket_prestations (
  id SERIAL PRIMARY KEY,
  ticket_id INTEGER NOT NULL,
  prestation_id INTEGER, -- reference to catalogue (nullable if custom)
  label TEXT NOT NULL,
  quantite INTEGER NOT NULL DEFAULT 1,
  prix_ht_snapshot NUMERIC NOT NULL DEFAULT 0, -- snapshot price at creation
  tva_snapshot NUMERIC NOT NULL DEFAULT 0,     -- snapshot tva at creation
  is_custom INTEGER NOT NULL DEFAULT 0,        -- 0 = false, 1 = true
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (prestation_id) REFERENCES prestations_catalogue(id) ON DELETE SET NULL
);

-- ticket_consommables (lines)
CREATE TABLE IF NOT EXISTS ticket_consommables (
  id SERIAL PRIMARY KEY,
  ticket_id INTEGER NOT NULL,
  consommable_id INTEGER, -- reference to catalogue (nullable if custom)
  label TEXT NOT NULL,
  quantite INTEGER NOT NULL DEFAULT 1,
  prix_ht_snapshot NUMERIC NOT NULL DEFAULT 0,
  tva_snapshot NUMERIC NOT NULL DEFAULT 0,
  is_custom INTEGER NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (consommable_id) REFERENCES consommables_catalogue(id) ON DELETE SET NULL
);

-- devis
CREATE TABLE IF NOT EXISTS devis (
  id SERIAL PRIMARY KEY,
  client_id INTEGER NOT NULL,
  numero TEXT UNIQUE,
  montant_ht NUMERIC DEFAULT 0,
  montant_tva NUMERIC DEFAULT 0,
  montant_ttc NUMERIC DEFAULT 0,
  status TEXT DEFAULT 'draft',
  pdf_path TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- factures
CREATE TABLE IF NOT EXISTS factures (
  id SERIAL PRIMARY KEY,
  client_id INTEGER NOT NULL,
  numero TEXT UNIQUE,
  montant_ht NUMERIC DEFAULT 0,
  montant_tva NUMERIC DEFAULT 0,
  montant_ttc NUMERIC DEFAULT 0,
  status TEXT DEFAULT 'unpaid',
  pdf_path TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- reglements (payments)
CREATE TABLE IF NOT EXISTS reglements (
  id SERIAL PRIMARY KEY,
  facture_id INTEGER NOT NULL,
  amount NUMERIC NOT NULL,
  method TEXT,
  paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (facture_id) REFERENCES factures(id) ON DELETE CASCADE
);

-- journal_emails
CREATE TABLE IF NOT EXISTS journal_emails (
  id SERIAL PRIMARY KEY,
  to_address TEXT,
  subject TEXT,
  body TEXT,
  status TEXT DEFAULT 'pending',
  error_message TEXT,
  sent_at TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- sequences (numbering)
CREATE TABLE IF NOT EXISTS sequences (
  name TEXT PRIMARY KEY,
  prefix TEXT,
  last_number INTEGER NOT NULL DEFAULT 0
);

-- Optional indexes for performance
CREATE INDEX IF NOT EXISTS idx_tickets_client ON tickets (client_id);
CREATE INDEX IF NOT EXISTS idx_velos_client ON velos (client_id);
CREATE INDEX IF NOT EXISTS idx_devis_client ON devis (client_id);
CREATE INDEX IF NOT EXISTS idx_factures_client ON factures (client_id);