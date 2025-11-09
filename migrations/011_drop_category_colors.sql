-- Rollback: suppression de la fonctionnalité couleurs catégories
-- Supprimer la table et l'index associés si présents
DROP INDEX IF EXISTS idx_prestation_groups_color_bg;
DROP TABLE IF EXISTS prestation_groups;
