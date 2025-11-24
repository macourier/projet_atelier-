# Base de données et Migrations

## Rôle

Schéma SQLite avec migrations versionnées pour évolution contrôlée.

## Configuration

- **Fichier DB** : `data/app.db` (ignoré par git)
- **Driver** : SQLite par défaut, MySQL/PostgreSQL supportés
- **Configuration** : `.env` → `DB_DRIVER`, `DB_PATH`
- **Script migration** : `bin/migrate.php` ou `composer migrate`

## Tables principales

### users
Utilisateurs authentifiés
- id, email (UNIQUE), password_hash, roles, created_at

### clients
Clients de l'atelier
- id, name, address, email, phone, **note** (ajouté migration 013), created_at, updated_at

### tickets
Interventions/réparations
- id, client_id (FK), status (open/invoiced), **bike_brand, bike_model, bike_serial, bike_notes** (migration 004)
- **received_at** (migration 006-008) : date réception vélo
- total_ht, total_tva, total_ttc, created_at, updated_at
- ⚠️ Plus de `velo_id` FK : infos vélo directement sur ticket

### ticket_prestations
Lignes prestations main d'œuvre
- id, ticket_id (FK), prestation_id (FK nullable), label, quantite, prix_ht_snapshot, tva_snapshot, is_custom

### ticket_consommables
Lignes pièces/consommables
- id, ticket_id (FK), consommable_id (FK nullable), label, quantite, prix_ht_snapshot, tva_snapshot, is_custom

### prestations_catalogue
Catalogue services
- id (TEXT PRIMARY KEY depuis migration 002), **category_id** (FK categories, migration 012)
- categorie (TEXT legacy), libelle, prix_main_oeuvre_ht
- **piece_libelle, piece_prix_ht** (migration 009) : pièce associée optionnelle
- tva_pct, duree_min, **deleted_at** (soft delete)

### categories
Catégories de prestations (migration 012)
- id, name, created_at

### devis
Devis générés
- id, client_id (FK), numero (UNIQUE), montant_ht, montant_tva, montant_ttc, status, pdf_path, created_at, updated_at

### factures
Factures émises
- id, client_id (FK), **ticket_id** (FK migration 003), numero (UNIQUE)
- montant_ht, montant_tva, montant_ttc, status (unpaid/paid), pdf_path, created_at, updated_at

### reglements
Paiements factures
- id, facture_id (FK), amount, method (CB/ESPECE), paid_at

### sequences
Numérotation documents
- name (PK), prefix, last_number

### journal_emails
Journal envois emails
- id, to_address, subject, body, status (pending/sent/error), error_message, sent_at, created_at

## Historique migrations

**001_init.sql** : Schéma initial
- Création toutes tables de base
- Index performance (client_id sur tickets, velos, devis, factures)

**002_prestations_catalogue.sql** : Refonte catalogue
- Changement id INTEGER → TEXT
- Ajout champs categorie, tva_pct, duree_min

**003_add_ticket_id_to_factures.sql** : Lien facture→ticket
- Ajout colonne `ticket_id` sur factures

**004_tickets_bike_fields.sql** : Infos vélo sur ticket
- Ajout bike_brand, bike_model, bike_serial, bike_notes sur tickets
- Migration données depuis table velos (si existante)

**005-008** : Tentatives ajout `received_at`
- Évolution progressive pour gérer contraintes SQLite
- Finalement : colonne `received_at` sans valeur par défaut

**009_add_piece_fields_prestations.sql** : Pièces dans catalogue
- Ajout piece_libelle, piece_prix_ht sur prestations_catalogue

**010_category_colors.sql** : Couleurs catégories (abandonné)

**011_drop_category_colors.sql** : Suppression couleurs

**012_create_categories.sql** : Table categories
- Création table `categories`
- Ajout `category_id` sur prestations_catalogue
- Migration données : catégories depuis `categorie` (TEXT)

**013_client_note.sql** : Notes client
- Ajout colonne `note` sur clients (sans transaction pour compat)

**014_client_note_no_tx.sql** : Correction migration 013
- Re-création sans BEGIN TRANSACTION

## Schema evolution sensible

### Vélos : entité → champs ticket

**Avant** : Table `velos` séparée avec FK `velo_id` sur tickets
**Après** : Champs bike_* directement sur ticket (bike_brand, bike_model, bike_serial, bike_notes)

**Raison** : Simplification modèle, un ticket = une intervention sur UN vélo spécifique

### Catalogue : ID numérique → ID texte

**Avant** : `id INTEGER AUTOINCREMENT`
**Après** : `id TEXT PRIMARY KEY` (format PREST_XXXXXX)

**Raison** : IDs stables, lisibles, pas de conflit lors imports/exports

### Pièces : table séparée → champs prestations

**Avant** : Table `consommables_catalogue` + `ticket_consommables` référençant catalogue
**Après** : Champs `piece_libelle` et `piece_prix_ht` directement sur prestation

**Raison** : Une prestation = main d'œuvre + pièce optionnelle (modèle simplifié)

## Soft delete

Prestations catalogue : `deleted_at IS NULL` = active
- Permet undo rapide
- Conserve historique références tickets

## Contraintes et index

- Foreign keys activées : `PRAGMA foreign_keys = ON`
- ON DELETE CASCADE : clients → tickets, factures
- ON DELETE SET NULL : prestations/consommables catalogue
- Index sur client_id pour jointures rapides

## Scripts utilitaires

**bin/migrate.php** : Application migrations
- Lit `migrations/*.sql` dans l'ordre
- Exécute si pas déjà appliqué (basé sur nom fichier)

**bin/create_admin.php** : Création utilisateur admin
- Saisie interactive email/password
- Hash password avec `password_hash()`

**bin/db_inspect.php** : Inspection schéma
- Affiche tables, colonnes, types

**bin/patch_*.php** : Scripts ponctuels de migration données
- Ajout champs manquants
- Backfill valeurs par défaut
