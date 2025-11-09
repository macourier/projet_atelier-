-- Add piece fields to prestations_catalogue (non-destructive)
ALTER TABLE prestations_catalogue ADD COLUMN piece_libelle TEXT DEFAULT 'Pièce';
ALTER TABLE prestations_catalogue ADD COLUMN piece_prix_ht REAL DEFAULT 0;

-- Backfill existing rows
UPDATE prestations_catalogue
SET piece_libelle = COALESCE(piece_libelle, 'Pièce'),
    piece_prix_ht = COALESCE(piece_prix_ht, 0);
