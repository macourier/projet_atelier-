-- Migration 004: Stocker les infos vélo directement sur tickets et backfill depuis velos
PRAGMA foreign_keys = ON;

-- Ajout des colonnes sur tickets
ALTER TABLE tickets ADD COLUMN bike_brand TEXT;
ALTER TABLE tickets ADD COLUMN bike_model TEXT;
ALTER TABLE tickets ADD COLUMN bike_serial TEXT;
ALTER TABLE tickets ADD COLUMN bike_notes TEXT;

-- Backfill depuis la table velos existante (si velo_id est présent)
UPDATE tickets
SET
  bike_brand = (SELECT v.brand  FROM velos v WHERE v.id = tickets.velo_id LIMIT 1),
  bike_model = (SELECT v.model  FROM velos v WHERE v.id = tickets.velo_id LIMIT 1),
  bike_serial = (SELECT v.serial FROM velos v WHERE v.id = tickets.velo_id LIMIT 1),
  bike_notes = (SELECT v.notes  FROM velos v WHERE v.id = tickets.velo_id LIMIT 1)
WHERE velo_id IS NOT NULL;
