-- Supprimer la colonne estimated_minutes de la table planning_items
-- Le planning ne doit plus gérer de temps/durée

-- SQLite ne supporte pas ALTER TABLE DROP COLUMN directement
-- On doit recréer la table sans la colonne

-- 1. Sauvegarder les données existantes
CREATE TABLE IF NOT EXISTS planning_items_backup AS SELECT id, title, scheduled_date, status, created_at, updated_at FROM planning_items;

-- 2. Supprimer l'ancienne table
DROP TABLE planning_items;

-- 3. Recréer la table sans estimated_minutes
CREATE TABLE IF NOT EXISTS planning_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    scheduled_date DATE NOT NULL,
    status TEXT DEFAULT 'planned',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 4. Restaurer les données
INSERT INTO planning_items (id, title, scheduled_date, status, created_at, updated_at)
SELECT id, title, scheduled_date, status, created_at, updated_at FROM planning_items_backup;

-- 5. Supprimer la table de sauvegarde
DROP TABLE planning_items_backup;

-- Recréer les index
CREATE INDEX IF NOT EXISTS idx_planning_date ON planning_items(scheduled_date);
CREATE INDEX IF NOT EXISTS idx_planning_status ON planning_items(status);
