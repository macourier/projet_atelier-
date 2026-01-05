# Correction des migrations pour Render

## Problème identifié

L'erreur `no such table: tickets` sur Render était causée par la migration `006_finalize_tickets.sql` qui :
1. Utilisait `ALTER TABLE IF EXISTS` - une syntaxe PostgreSQL non supportée par SQLite
2. Supprimait la table `tickets` mais échouait à la recréer
3. Laissait la base de données sans table `tickets`

## Corrections apportées

### 1. Suppression de migrations obsolètes/défectueuses

Les fichiers suivants ont été supprimés :
- `migrations/006_finalize_tickets.sql` - Défectueux (syntaxe ALTER TABLE IF EXISTS invalide)
- `migrations/007_add_received_at_simple.sql` - Obsolète (colonne déjà ajoutée par 005)
- `migrations/008_add_received_at_no_default.sql` - Obsolète (colonne déjà ajoutée par 005)

### 2. Correction de la migration 013

Fichier : `migrations/013_client_note.sql`
- Suppression des transactions inutiles qui causaient l'erreur : "cannot start a transaction within a transaction"
- Simplification en une instruction ALTER TABLE directe

### 3. Amélioration de la migration 015

Fichier : `migrations/015_add_notes_to_tickets.sql`
- Ajout de commentaires explicatifs sur le comportement idempotent
- La colonne est ajoutée si elle n'existe pas, sinon la requête échouera silencieusement

### 4. Amélioration du script de migration

Fichier : `bin/migrate.php`
- Ajout de gardes d'idempotence pour la migration 013 (clients.note)
- Ajout de gardes d'idempotence pour la migration 015 (tickets.notes)
- Vérification de l'existence de la table `tickets` avant d'essayer d'ajouter des colonnes

## Résultat des tests locaux

```
[info] Tables in database:
  - accounting_exports
  - categories
  - clients
  - company_profile
  - consommables_catalogue
  - devis
  - factures
  - journal_emails
  - planning_items
  - prestations_catalogue
  - reglements
  - sequences
  - sqlite_sequence
  - ticket_consommables
  - ticket_prestations
  - tickets      ✓ TABLE PRÉSENTE
  - users
  - velos

=== Tickets Table ===
  Total tickets: 355      ✓ DONNÉES PRÉSENTES
```

## Instructions pour le déploiement sur Render

### Étape 1 : Commiter les modifications

```bash
git add .
git commit -m "Fix: Corriger les migrations SQLite pour Render"
git push
```

### Étape 2 : Déployer sur Render

1. Allez sur votre dashboard Render
2. Sélectionnez votre service web
3. Cliquez sur "Manual Deploy" → "Deploy latest commit"
4. Attendez que le déploiement se termine

### Étape 3 : Vérifier le déploiement

Les logs de déploiement devraient montrer :
```
[ok] Applied migration: 001_init.sql
[ok] Applied migration: 002_prestations_catalogue.sql
[ok] Applied migration: 003_add_ticket_id_to_factures.sql
[ok] Applied migration: 004_tickets_bike_fields.sql
[ok] Applied migration: 005_remove_description_add_received_at.sql
[ok] Applied migration: 009_add_piece_fields_prestations.sql
...
[ok] Applied migration: 020_add_ready_status_to_tickets.sql
[info] Tables in database:
  - tickets    ✓
```

### Étape 4 : Tester l'application

1. Accédez à votre application Render
2. Cliquez sur "File d'attente"
3. La page devrait s'afficher correctement sans erreur `no such table: tickets`

## Recommandation pour la persistance des données

Bien que les corrections résolvent le problème immédiat, il est recommandé d'ajouter un **volume persistant** sur Render pour éviter que la base de données ne soit perdue lors des redéploiements futurs :

1. Allez dans **Settings** → **Advanced**
2. Cliquez sur **Add Disk**
3. Configurez :
   - **Name** : `data`
   - **Mount path** : `/var/www/html/data`
   - **Size** : 1 GB
4. Sauvegardez et redéployez

## Note importante

Avec les corrections actuelles, les migrations s'exécuteront correctement même si la base de données est réinitialisée. Cependant, **toutes les données seront perdues** si la base de données SQLite n'est pas sur un volume persistant.
