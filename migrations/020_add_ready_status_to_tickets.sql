-- Ajout du statut "ready" pour les tickets terminés mais non facturés
-- La colonne status existe déjà, cette migration documente simplement l'utilisation du statut "ready"

-- Aucune modification de schéma nécessaire car la colonne status est de type TEXT
-- Les valeurs possibles sont maintenant : 'open', 'ready', 'invoiced', 'closed'
