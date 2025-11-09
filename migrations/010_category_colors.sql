-- Migration: couleurs de catégories pour le catalogue
-- Table de mapping catégorie -> couleurs
CREATE TABLE IF NOT EXISTS prestation_groups (
  categorie TEXT PRIMARY KEY,
  color_bg  TEXT NULL,
  color_text TEXT NULL
);

-- Backfill initial: toutes les catégories existantes
INSERT OR IGNORE INTO prestation_groups (categorie)
SELECT DISTINCT categorie FROM prestations_catalogue
WHERE categorie IS NOT NULL AND categorie != '';

-- Index facultatif pour recherches futures (unicité logique côté app)
CREATE INDEX IF NOT EXISTS idx_prestation_groups_color_bg ON prestation_groups (color_bg);
