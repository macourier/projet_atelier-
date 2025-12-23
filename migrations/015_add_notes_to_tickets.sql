-- Migration 015: Ajouter colonne notes à la table tickets
ALTER TABLE tickets ADD COLUMN notes TEXT DEFAULT NULL;
