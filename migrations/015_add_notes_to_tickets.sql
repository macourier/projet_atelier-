-- Migration 015: Ajouter colonne notes à la table tickets
-- Vérifier si la table existe et si la colonne n'existe pas déjà
-- SQLite n'a pas de ALTER TABLE IF EXISTS pour les colonnes, donc on utilise une approche basée sur les erreurs
-- La colonne sera ajoutée si elle n'existe pas, sinon la requête échouera silencieusement (géré par bin/migrate.php)
ALTER TABLE tickets ADD COLUMN notes TEXT DEFAULT NULL;
