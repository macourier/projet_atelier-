# Configuration Render pour le déploiement

## Problème actuel

L'erreur `no such table: tickets` indique que la base de données SQLite n'existe pas sur l'environnement de production Render. Cela se produit car :

1. Le système de fichiers de Render n'est pas persistant par défaut entre les déploiements
2. La base de données SQLite (située dans `/var/www/html/data`) est perdue à chaque redéploiement
3. Le script d'entrypoint exécute bien les migrations, mais si le conteneur est redémarré, la base de données peut être perdue

## Solution : Configurer un volume persistant sur Render

### Étape 1 : Ajouter un volume persistant

1. Connectez-vous à votre dashboard Render
2. Sélectionnez votre service web
3. Cliquez sur "Deploy" ou "Settings"
4. Cherchez la section "Disk" ou "Persistent Disk"
5. Ajoutez un nouveau volume persistant :
   - **Nom** : `data`
   - **Mount path** : `/var/www/html/data`
   - **Taille recommandée** : 1 GB (suffisant pour SQLite avec fichiers PDF)

### Étape 2 : Forcer un redéploiement

Une fois le volume ajouté :

1. Cliquez sur "Manual Deploy" ou "Deploy latest commit"
2. Attendez que le déploiement se termine
3. Le script d'entrypoint exécutera automatiquement les migrations et créera la base de données dans le volume persistant

### Étape 3 : Vérifier la base de données

Utilisez le script de diagnostic via Render Shell :

```bash
# Ouvrez un shell sur votre service Render
php bin/check_db.php
```

Ce script affichera :
- Le chemin de la base de données
- Si le fichier existe
- La taille du fichier
- Les tables présentes
- Le nombre de tickets

## Configuration PostgreSQL (Recommandé pour la production)

Pour une base de données plus robuste et persistante, utilisez PostgreSQL sur Render.

### Étape 1 : Créer une base de données PostgreSQL

1. Dans votre dashboard Render, cliquez sur "New+" → "PostgreSQL"
2. Configurez votre base de données :
   - **Name** : projet-atelier-db
   - **Database** : projet_atelier
   - **User/Password** : seront générés automatiquement
   - **Region** : même région que votre service web
3. Cliquez sur "Create Database"

### Étape 2 : Connecter votre service à PostgreSQL

1. Accédez à votre service web
2. Allez dans "Settings" → "Environment"
3. Ajoutez les variables d'environnement suivantes :

```
DB_DRIVER=pgsql
DB_HOST=votre-db-host.internal
DB_PORT=5432
DB_NAME=projet_atelier
DB_USER=votre-db-user
DB_PASSWORD=votre-db-password
```

⚠️ **Important** : Ne copiez pas l'intégralité de `DATABASE_URL`, séparez les composants comme ci-dessus.

### Étape 3 : Variables d'environnement supplémentaires

Ajoutez également ces variables :

```
APP_ENV=production
APP_DEBUG=false
ADMIN_EMAIL=votre@email.com
ADMIN_PASSWORD=votre_mot_de_passe
```

### Étape 4 : Redéployer

1. Cliquez sur "Manual Deploy" → "Deploy latest commit"
2. Le script d'entrypoint détectera automatiquement PostgreSQL et exécutera `migrate_pg.php`
3. Les migrations seront appliquées sur votre base PostgreSQL

### Vérifier la connexion PostgreSQL

Via le Render Shell de votre service web :

```bash
# Vérifier les variables d'environnement
env | grep DB_

# Tester la connexion PostgreSQL
php bin/check_db.php
```

### Avantages de PostgreSQL sur Render

- ✅ **Persistance automatique** : Pas besoin de volume persistant
- ✅ **Sauvegardes automatiques** : Render backup votre base quotidiennement
- ✅ **Haute disponibilité** : Réplication automatique
- ✅ **Scalabilité** : Peut gérer plus de connexions que SQLite
- ✅ **Pas de perte de données** : Même si le conteneur redémarre

### Alternative : Exécuter manuellement les migrations

Si vous avez un accès SSH à votre service Render :

```bash
# Exécuter toutes les migrations
php bin/migrate.php

# Vérifier l'état de la base de données
php bin/check_db.php
```

## Variables d'environnement requises

Assurez-vous que ces variables sont configurées dans Render :

```
DB_PATH=./data/app.db
ADMIN_EMAIL=votre@email.com
ADMIN_PASSWORD=votre_mot_de_passe
```

## Dépannage

### La base de données est toujours vide après redéploiement

- Vérifiez que le volume persistant est bien monté sur `/var/www/html/data`
- Vérifiez les permissions : le conteneur doit avoir accès en écriture au volume
- Consultez les logs Render pour voir si les migrations ont été exécutées

### Erreur de permission sur le fichier de base de données

Le Dockerfile définit déjà les permissions avec `chmod -R 775`, mais vous devrez peut-être ajuster :

```bash
# Dans le Render Shell
chmod -R 777 /var/www/html/data
```

### Voir les logs de migration

Dans le dashboard Render, consultez les logs du déploiement. Vous devriez voir des messages comme :

```
[info] Using SQLite DB at: /var/www/html/data/app.db
[ok] Applied migration: 001_init.sql
[ok] Applied migration: 002_prestations_catalogue.sql
...
[summary] Database file created and initialized at: /var/www/html/data/app.db
```

## Migration vers PostgreSQL (optionnel)

Si vous souhaitez une base de données plus robuste pour la production, Render offre PostgreSQL gratuit :

1. Créez une base de données PostgreSQL sur Render
2. Ajoutez les variables d'environnement `DATABASE_URL`
3. Mettez à jour votre code pour utiliser PostgreSQL au lieu de SQLite
4. Exécutez les migrations sur PostgreSQL

Cela nécessitera des modifications du code (connexion PDO, types de données, etc.).
