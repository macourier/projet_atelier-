-- Table planning_items pour le module Planning V1
-- Stocke les interventions planifiées avec date et durée estimée

CREATE TABLE IF NOT EXISTS planning_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    scheduled_date DATE NOT NULL,
    estimated_minutes INTEGER NOT NULL DEFAULT 0,
    status TEXT DEFAULT 'planned', -- planned/done/cancelled
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Index pour optimiser les requêtes de planning
CREATE INDEX IF NOT EXISTS idx_planning_date ON planning_items(scheduled_date);
CREATE INDEX IF NOT EXISTS idx_planning_status ON planning_items(status);
