-- Migration 013: add optional note field on clients (SQLite)
PRAGMA foreign_keys = OFF;
BEGIN TRANSACTION;

ALTER TABLE clients ADD COLUMN note TEXT;

COMMIT;
PRAGMA foreign_keys = ON;
