-- 002_prestations_catalogue.sql
-- Remplace le catalogue des prestations par un schéma adapté au module tactile.
-- ATTENTION (dev) : on remplace l'ancienne table si elle existait (perte de données éventuelles).
PRAGMA foreign_keys = OFF;
BEGIN TRANSACTION;

DROP TABLE IF EXISTS prestations_catalogue;

CREATE TABLE IF NOT EXISTS prestations_catalogue (
    id TEXT PRIMARY KEY,
    categorie TEXT NOT NULL,
    libelle TEXT NOT NULL,
    prix_main_oeuvre_ht REAL NOT NULL,
    tva_pct REAL DEFAULT 20.0,
    duree_min INTEGER DEFAULT 15,
    deleted_at TEXT DEFAULT NULL
);

INSERT INTO prestations_catalogue (id, categorie, libelle, prix_main_oeuvre_ht) VALUES
-- TRANSMISSION
('PREST_T001','Transmission','Réglage d''un dérailleur AV ou AR',13),
('PREST_T002','Transmission','Remplacement d''un dérailleur + réglage',25),
('PREST_T003','Transmission','Remplacement d''une chaîne',16),
('PREST_T004','Transmission','Remplacement chaîne + cassette/roue libre + réglage',26),
('PREST_T005','Transmission','Changement câble et gaine (passage externe) + réglage',16),
('PREST_T006','Transmission','Contrôle et serrage du boîtier de pédalier',18),
('PREST_T007','Transmission','Nettoyage de la transmission (dégraissant bio)',30),
('PREST_T008','Transmission','Forfait réglage des dérailleurs AV et AR',23),
('PREST_T009','Transmission','Forfait changement câbles et gaines (passage interne)',24),
('PREST_T010','Transmission','Forfait changement câbles et gaines (passage externe)',28),
('PREST_T011','Transmission','Forfait changement câbles et gaines (passage interne, complet)',42),
('PREST_T012','Transmission','Remplacement d''un sélecteur de vitesse + réglage',26),
('PREST_T013','Transmission','Changement du jeu de galets de dérailleur',20),
('PREST_T014','Transmission','Entretien ou changement du boîtier de pédalier',36),
('PREST_T015','Transmission','Remplacement ou dégauchissage de la patte de dérailleur + réglage',25),
('PREST_T016','Transmission','Remplacement d''une cassette ou roue libre',16),
('PREST_T017','Transmission','Changement de pédales',10),
('PREST_T018','Transmission','Changement d''un pédalier ou plateau + réglage',35),
('PREST_T019','Transmission','Pack transmission (remplacement + réglages)',65),

-- DIRECTION
('PREST_D001','Direction','Contrôle et réglage de la direction',15),
('PREST_D002','Direction','Entretien complet du jeu de direction',30),
('PREST_D003','Direction','Changement de cintre (VTT, VTC, urbain)',26),
('PREST_D004','Direction','Changement de potence et/ou entretoise',22),
('PREST_D005','Direction','Changement du jeu de direction complet',35),
('PREST_D006','Direction','Installation d''une paire de poignées',8),

-- ROUES
('PREST_R001','Roues','Remplacement chambre à air ou pneu',12),
('PREST_R002','Roues','Dévoilage d''une roue classique',16),
('PREST_R003','Roues','Réglage de jeu d''un moyeu',18),
('PREST_R004','Roues','Gonflage des pneumatiques',0),
('PREST_R005','Roues','Forfait préventif roue tubeless',15),
('PREST_R006','Roues','Remplacement CAA ou pneu roue arrière VAE/Nexus/FAT',22),
('PREST_R007','Roues','Dévoilage d''une roue spécifique',28),
('PREST_R008','Roues','Remplacement de rayons + reprise du voile',26),
('PREST_R009','Roues','Entretien complet d''un moyeu de roue',35),
('PREST_R010','Roues','Remplacement de la roue avant',20),
('PREST_R011','Roues','Remplacement de la roue arrière',25),
('PREST_R012','Roues','Remplacement d''une roue VAE ou Nexus',40),
('PREST_R013','Roues','Forfait remplacement CAA ou pneus AV + AR',22),
('PREST_R014','Roues','Remplacement express pneu et/ou chambre à air',15);

COMMIT;
PRAGMA foreign_keys = ON;
