# Guide de déploiement rapide sur Render avec PostgreSQL

Ce guide vous permet de déployer votre application sur Render avec une base de données PostgreSQL persistante en moins de 10 minutes.

## Prérequis

- Un compte Render (gratuit)
- Un dépôt GitHub avec votre code
- Le commit `5e7a0fa` ou supérieur (support PostgreSQL)

---

## Étape 1 : Créer la base de données PostgreSQL (2 minutes)

1. Connectez-vous à [dashboard.render.com](https://dashboard.render.com)
2. Cliquez sur **New+** → **PostgreSQL**
3. Configurez :
   - **Name**: `projet-atelier-db`
   - **Database**: `projet_atelier`
   - **Region**: `Frankfurt` (ou la plus proche de vos utilisateurs)
4. Cliquez sur **Create Database**
5. Attendez que la base soit prête (vert)

---

## Étape 2 : Obtenir les credentials PostgreSQL (1 minute)

1. Cliquez sur votre base de données créée
2. Allez dans **Connections** → **External Database**
3. Copiez les informations suivantes :
   - **Host**: `dpg-xxxxx.oregon-postgres.render.com` (exemple)
   - **Port**: `5432`
   - **User**: `projet_atelier_user`
   - **Password**: cliquez sur "Show" pour le voir
   - **Database**: `projet_atelier`

⚠️ **Gardez ces informations sécurisées !**

---

## Étape 3 : Connecter votre dépôt GitHub (2 minutes)

1. Cliquez sur **New+** → **Web Service**
2. Si ce n'est pas fait, connectez votre compte GitHub
3. Sélectionnez votre dépôt `projet_atelier-`
4. Configurez :
   - **Name**: `projet-atelier-web`
   - **Region**: Même région que votre base PostgreSQL
   - **Branch**: `main`
   - **Runtime**: **Docker** (important !)
   - **Root Directory**: Laissez vide (ou `./`)

---

## Étape 4 : Configurer les variables d'environnement (3 minutes)

Dans la section **Environment**, ajoutez ces variables :

### Variables PostgreSQL (crucial)

```
DB_DRIVER=pgsql
DB_HOST=dpg-xxxxx.oregon-postgres.render.com
DB_PORT=5432
DB_NAME=projet_atelier
DB_USER=projet_atelier_user
DB_PASSWORD=votre_password_ic
```

⚠️ **Remplacez les valeurs par celles copiées à l'étape 2**

### Variables application

```
APP_ENV=production
APP_DEBUG=false
SECRET_KEY=generer_une_cle_secrete_aleatoire
ADMIN_EMAIL=votre@email.com
ADMIN_PASSWORD=votre_mot_de_passe_admin
```

### Variables entreprise (optionnel)

```
COMPANY_NAME=Atelier Vélo
COMPANY_ADDRESS=10 avenue Willy Brandt, 59000 Lille
COMPANY_EMAIL=contact@atelier-velo.com
COMPANY_PHONE=03 20 78 80 63
TVA_DEFAULT=20
NUMSEQ_FACTURE_PREFIX=2025-
```

### Variables mailer (optionnel)

```
MAILER_DSN=smtp://user:pass@smtp.example.com:587
```

---

## Étape 5 : Déployer l'application (1 minute)

1. Cliquez sur **Create Web Service**
2. Attendez que le déploiement se termine (2-3 minutes)
3. Cliquez sur **Logs** pour voir la progression

Vous devriez voir ces messages dans les logs :

```
[info] Using PostgreSQL database
📦 PostgreSQL Migration Script
============================
Host: dpg-xxxxx.oregon-postgres.render.com:5432
Database: projet_atelier
✅ Connected to PostgreSQL database

Found 10 PostgreSQL migration(s):
   ⬆️  001_init_pg.sql ✅
   ⬆️  002_prestations_catalogue_pg.sql ✅
   ...
   ⬆️  020_add_ready_status_to_tickets_pg.sql ✅

============================
✅ Successfully applied 10 migration(s)
============================
```

---

## Étape 6 : Accéder à votre application

Une fois le déploiement terminé (statut "Live") :

1. Cliquez sur l'URL affichée (ex: `https://projet-atelier-web.onrender.com`)
2. Connectez-vous avec :
   - **Email**: celui défini dans `ADMIN_EMAIL`
   - **Mot de passe**: celui défini dans `ADMIN_PASSWORD`

---

## Vérifier le déploiement

Depuis le **Render Shell** de votre service :

```bash
# Vérifier la connexion PostgreSQL
php bin/check_db.php

# Devrait afficher :
# ✅ Connected to PostgreSQL database
# Tables: [list of tables]
```

---

## Dépannage

### Le déploiement échoue avec "PDOException: could not find driver"

**Cause**: Le Dockerfile n'installe pas le driver PostgreSQL

**Solution**: Assurez-vous que vous avez le commit `5e7a0fa` ou supérieur. Le Dockerfile doit contenir :

```dockerfile
RUN apt-get update && apt-get install -y libzip-dev zip libpng-dev libsqlite3-dev libpq-dev \
    && docker-php-ext-install pdo pdo_sqlite pdo_pgsql zip gd
```

### Les migrations ne s'exécutent pas

**Cause**: L'entrypoint ne détecte pas PostgreSQL

**Solution**: Vérifiez que `DB_DRIVER=pgsql` est correctement défini dans les variables d'environnement. Le script d'entrypoint affichera `[info] Using PostgreSQL database` dans les logs.

### Erreur "connection refused" à PostgreSQL

**Cause**: Le hostname ou le port est incorrect

**Solution**: Vérifiez les credentials PostgreSQL dans la section Connections de votre base de données Render. Le hostname doit ressembler à `dpg-xxxxx.oregon-postgres.render.com`.

### La base de données se vide après redéploiement

**Solution**: Avec PostgreSQL, cela ne devrait PAS arriver. PostgreSQL est persistant par défaut. Si ça arrive, vérifiez que vous utilisez bien PostgreSQL et non SQLite.

---

## Prochaines étapes

Une fois votre application déployée :

1. **Configurez votre entreprise** : Allez dans Administration → Paramètres entreprise
2. **Importez votre catalogue** : Administration → Prestations → Ajouter
3. **Testez le workflow** : Créez un client, un ticket, un devis, une facture
4. **Configurez le mailer** (optionnel) : Pour envoyer les devis par email

---

## Mise à jour de l'application

Pour déployer une nouvelle version :

1. Pushez vos modifications sur GitHub
2. Render détectera automatiquement le nouveau commit
3. Il redéploiera automatiquement
4. PostgreSQL n'est pas affecté, les données sont conservées

---

## Coûts

- **Web Service**: Gratuit (750 heures/mois)
- **PostgreSQL**: Gratuit (90 jours d'essai, puis ~7$/mois)
- **Stockage**: Inclus dans PostgreSQL gratuit

---

## Support

Pour toute question sur le déploiement :

- Consultez [RENDER_SETUP.md](RENDER_SETUP.md) pour plus de détails
- Consultez [POSTGRESQL_MIGRATION.md](POSTGRESQL_MIGRATION.md) pour la documentation PostgreSQL
- Consultez les logs Render dans le dashboard