-- Migration 013: add optional note field on clients (SQLite)
ALTER TABLE clients ADD COLUMN note TEXT;
