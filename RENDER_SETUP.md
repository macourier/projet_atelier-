# Configuration Render pour la persistance SQLite

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

## Alternative : Exécuter manuellement les migrations

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
