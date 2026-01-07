-- Supprimer la colonne estimated_minutes de la table planning_items (PostgreSQL version)
-- Le planning ne doit plus gérer de temps/durée
-- PostgreSQL supporte ALTER TABLE DROP COLUMN directement

BEGIN;

-- Vérifier si la colonne existe avant de la supprimer
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name = 'planning_items' 
        AND column_name = 'estimated_minutes'
    ) THEN
        ALTER TABLE planning_items DROP COLUMN estimated_minutes;
    END IF;
END $$;

COMMIT;