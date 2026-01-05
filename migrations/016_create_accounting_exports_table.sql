-- Migration 016: Créer la table accounting_exports pour enregistrer les exports comptables
CREATE TABLE IF NOT EXISTS accounting_exports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    year INTEGER NOT NULL,
    month INTEGER NOT NULL,
    exported_at TEXT NOT NULL,
    exported_by TEXT NULL,
    row_count INTEGER NULL
);

-- Créer un index sur (year, month) pour accélérer les recherches par mois
CREATE INDEX IF NOT EXISTS idx_accounting_exports_year_month ON accounting_exports (year, month);

-- Créer un index sur exported_at pour récupérer le dernier export rapidement
CREATE INDEX IF NOT EXISTS idx_accounting_exports_exported_at ON accounting_exports (exported_at DESC);
