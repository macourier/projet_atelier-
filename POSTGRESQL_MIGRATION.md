# Migration vers PostgreSQL

Ce guide explique comment migrer l'application de SQLite vers PostgreSQL.

## Prérequis

- PostgreSQL installé et en cours d'exécution
- PHP 8.2+ avec l'extension `pdo_pgsql`
- Accès administrateur pour créer la base de données

## Installation de l'extension PostgreSQL PHP

### Windows (WAMP)

1. Téléchargez les DLLs PostgreSQL PHP compatibles avec votre version PHP depuis :
   https://windows.php.net/downloads/pecl/releases/pdo_pgsql/

2. Copiez les fichiers dans `C:\wamp64\bin\php\{version}\ext\` :
   - `php_pdo_pgsql.dll`
   - `php_pgsql.dll`

3. Éditez `php.ini` et ajoutez :
   ```ini
   extension=pdo_pgsql
   extension=pgsql
   ```

4. Redémarrez Apache/WAMP

5. Vérifiez l'installation :
   ```bash
   php -m | grep pgsql
   ```

### Linux (Ubuntu/Debian)

```bash
sudo apt-get install php-pgsql php-pdo
sudo systemctl restart apache2  # ou php-fpm
```

### macOS

```bash
brew install php
brew install postgresql
```

## Configuration de la base de données

### 1. Créer la base de données

```bash
# Connectez-vous à PostgreSQL
psql -U postgres

# Dans le prompt psql :
CREATE DATABASE projet_atelier;
CREATE USER atelier_user WITH PASSWORD 'votre_mot_de_passe';
GRANT ALL PRIVILEGES ON DATABASE projet_atelier TO atelier_user;
\q
```

### 2. Configurer le fichier .env

Créez ou modifiez le fichier `.env` à la racine du projet :

```env
DB_DRIVER=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=projet_atelier
DB_USER=atelier_user
DB_PASSWORD=votre_mot_de_passe
```

### 3. Tester la connexion

```bash
php bin/migrate_pg.php
```

Vous devriez voir :
```
📦 PostgreSQL Migration Script
============================
Host: 127.0.0.1:5432
Database: projet_atelier
User: atelier_user

✅ Connected to PostgreSQL database

Found X PostgreSQL migration(s):
...
```

## Exécuter les migrations

### Méthode automatique (au démarrage de l'application)

L'application exécute automatiquement les migrations au démarrage si elle détecte `DB_DRIVER=pgsql`.

### Méthode manuelle

```bash
php bin/migrate_pg.php
```

Ce script :
- Vérifie les migrations déjà appliquées
- Applique uniquement les nouvelles migrations
- Suit l'ordre chronologique des fichiers
- Gère les erreurs avec rollback

## Migrations créées

Les migrations PostgreSQL suivantes sont disponibles :

- `001_init_pg.sql` - Structure initiale de la base de données
- `002_prestations_catalogue_pg.sql` - Catalogue des prestations
- `003_add_ticket_id_to_factures_pg.sql` - Lien ticket-facture
- `004_tickets_bike_fields_pg.sql` - Champs vélo dans les tickets
- `005_remove_description_add_received_at_pg.sql` - Modification de la table tickets
- `016_create_accounting_exports_table_pg.sql` - Table des exports comptables
- `017_create_company_profile_pg.sql` - Profil entreprise
- `018_create_planning_pg.sql` - Module planning
- `019_remove_estimated_minutes_from_planning_pg.sql` - Suppression de colonne
- `020_add_ready_status_to_tickets_pg.sql` - Statut ready

## Différences SQLite vs PostgreSQL

### Types de données

| SQLite | PostgreSQL |
|---------|-----------|
| INTEGER PRIMARY KEY AUTOINCREMENT | SERIAL PRIMARY KEY |
| DATETIME | TIMESTAMP |
| REAL | NUMERIC |
| TEXT DEFAULT NULL | TIMESTAMP DEFAULT NULL |

### Syntaxe spécifique

- PostgreSQL utilise `BEGIN;` au lieu de `BEGIN TRANSACTION;`
- PostgreSQL utilise `ON CONFLICT` au lieu de `INSERT OR IGNORE`
- PostgreSQL supporte `ALTER TABLE DROP COLUMN` directement
- PostgreSQL utilise `information_schema` pour les métadonnées

### Index

Les deux systèmes supportent `CREATE INDEX IF NOT EXISTS`, mais la syntaxe est identique.

## Retour à SQLite

Pour revenir à SQLite, modifiez simplement votre fichier `.env` :

```env
DB_DRIVER=sqlite
DB_PATH=./data/app.db
```

L'application utilisera automatiquement les migrations SQLite.

## Dépannage

### Erreur : "could not find driver"

L'extension PostgreSQL n'est pas installée. Voir la section "Installation de l'extension PostgreSQL PHP".

### Erreur : "FATAL: password authentication failed"

Vérifiez le mot de passe dans `.env` et les utilisateurs PostgreSQL :

```bash
psql -U postgres -c "\du"
```

### Erreur : "FATAL: database "projet_atelier" does not exist"

Créez la base de données :

```bash
createdb -U postgres projet_atelier
```

### Erreur de connexion

Vérifiez que PostgreSQL est en cours d'exécution :

```bash
# Windows
sc query postgresql-x64-14

# Linux
sudo systemctl status postgresql

# macOS
brew services list
```

### Problèmes de permissions

Assurez-vous que l'utilisateur a les droits nécessaires :

```sql
GRANT ALL PRIVILEGES ON DATABASE projet_atelier TO atelier_user;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO atelier_user;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO atelier_user;
```

## Migration des données existantes

Si vous avez des données dans SQLite que vous souhaitez migrer vers PostgreSQL :

### Option 1 : Export/Import pg_dump

```bash
# Depuis SQLite
sqlite3 data/app.db .dump > sqlite_dump.sql

# Convertir en PostgreSQL (manuellement ou avec un outil)
# Il existe des outils comme pgloader

# Importer dans PostgreSQL
psql -U atelier_user -d projet_atelier < pg_dump.sql
```

### Option 2 : Utiliser pgloader (recommandé)

```bash
# Installer pgloader
# Ubuntu/Debian
sudo apt-get install pgloader

# macOS
brew install pgloader

# Windows (via WSL ou Cygwin)

# Exécuter la migration
pgloader sqlite://data/app.db postgresql://atelier_user:password@localhost/projet_atelier
```

### Option 3 : Script PHP personnalisé

Créez un script pour migrer les données table par table en utilisant PDO pour lire SQLite et écrire dans PostgreSQL.

## Sécurité

- Ne committez jamais le fichier `.env` dans le dépôt Git
- Utilisez des mots de passe forts
- Limitez les droits de l'utilisateur de la base de données
- Activez SSL pour les connexions de production

## En production

Pour un environnement de production :

1. Créez un utilisateur dédié avec droits limités
2. Activez la connexion SSL
3. Configurez les sauvegardes automatiques
4. Surveillez les performances avec `pg_stat_statements`
5. Configurez `pg_hba.conf` pour la sécurité

## Ressources

- Documentation PostgreSQL : https://www.postgresql.org/docs/
- PDO PostgreSQL : https://www.php.net/manual/fr/ref.pdo-pgsql.php
- pgloader : https://pgloader.readthedocs.io/