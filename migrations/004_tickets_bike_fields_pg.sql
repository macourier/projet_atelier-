-- Migration 004: Stocker les infos vélo directement sur tickets et backfill depuis velos (PostgreSQL version)

-- Ajout des colonnes sur tickets avec vérification d'existence
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name = 'tickets' 
        AND column_name = 'bike_brand'
    ) THEN
        ALTER TABLE tickets ADD COLUMN bike_brand TEXT;
    END IF;
    
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name = 'tickets' 
        AND column_name = 'bike_model'
    ) THEN
        ALTER TABLE tickets ADD COLUMN bike_model TEXT;
    END IF;
    
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name = 'tickets' 
        AND column_name = 'bike_serial'
    ) THEN
        ALTER TABLE tickets ADD COLUMN bike_serial TEXT;
    END IF;
    
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name = 'tickets' 
        AND column_name = 'bike_notes'
    ) THEN
        ALTER TABLE tickets ADD COLUMN bike_notes TEXT;
    END IF;
END $$;

-- Backfill depuis la table velos existante (si velo_id est présent)
UPDATE tickets
SET
  bike_brand = v.brand,
  bike_model = v.model,
  bike_serial = v.serial,
  bike_notes = v.notes
FROM velos v
WHERE v.id = tickets.velo_id AND tickets.velo_id IS NOT NULL;