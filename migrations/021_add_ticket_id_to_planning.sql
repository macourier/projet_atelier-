-- Ajout du champ ticket_id et recovery_date à planning_items
-- Permet de lier les items de planning aux tickets et de gérer la date de récupération

-- Ajout des colonnes une par une (SQLite ne supporte pas ADD COLUMN multiple)
ALTER TABLE planning_items ADD COLUMN ticket_id INTEGER REFERENCES tickets(id);
ALTER TABLE planning_items ADD COLUMN recovery_date DATE;
ALTER TABLE planning_items ADD COLUMN notes TEXT;

-- Index pour optimiser les requêtes par ticket
CREATE INDEX IF NOT EXISTS idx_planning_ticket ON planning_items(ticket_id);
CREATE INDEX IF NOT EXISTS idx_planning_recovery ON planning_items(recovery_date);