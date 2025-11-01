# PROJET_ATELIER — Facturation Vélo

But : petite application PHP (Slim 4) pour la gestion de tickets, devis et factures pour un atelier vélo.
Ce dépôt contient une structure minimale prête à être initialisée en développement (SQLite) et extensible.

Dépendances principales
- PHP 8.2+
- slim/slim (Slim 4)
- slim/psr7
- twig/twig
- vlucas/phpdotenv
- ramsey/uuid
- dompdf/dompdf
- symfony/mailer & symfony/http-client
- phpoffice/phpspreadsheet

Démarrage rapide (sans Wamp)
Suivre ces 4 commandes dans l'ordre (dans VS Code / terminal à la racine du projet) :

1) Installer les dépendances
   composer install

2) Créer le fichier .env (si absent)
   cp .env.example .env
   # (Windows PowerShell)
   # Copy-Item .env.example .env

3) Appliquer la migration SQLite (crée data/app.db)
   composer migrate
   # (équivalent) php bin/migrate.php

4) Démarrer le serveur PHP intégré
   composer start
   # Ouvrir http://localhost:8080

Activer les extensions PHP (Windows)
Si vous installez et testez localement sous Windows, suivez ces étapes pour localiser et activer les extensions requises (gd, mbstring, zip).

1) Identifier le php.ini utilisé par PHP CLI
- Dans un terminal, exécutez :
  php --ini
- Repérez la ligne "Loaded Configuration File". Exemple (par défaut présumé pour ce guide) :
  C:\FildocDev\php\php.ini
  (Si ton environnement affiche un autre chemin, utilise ce chemin au lieu de l'exemple ci‑dessus.)

2) Modifier php.ini (procédure)
- Ouvre le fichier indiqué par "Loaded Configuration File" avec un éditeur.
- Vérifie / définit la variable extension_dir si nécessaire (ajoute/modifie la ligne) :
```
extension_dir="C:\\FildocDev\\php\\ext"
```
- Active (décommente) les extensions requises : recherchez les lignes et supprimez le point‑virgule (`;`) si présent, ou ajoutez ces lignes si absentes :
```
extension=gd
extension=mbstring
extension=zip
```
- Assure‑toi qu'il n'existe pas de doublons commentés/non commentés conflictuels (par exemple deux lignes "extension=gd", dont une commentée) : nettoie les doublons pour qu'une seule ligne active reste.

3) Vérifications
- Ferme et rouvre le terminal (ou redémarre la session) pour que le CLI prenne en compte les modifications.
- Vérifie que les extensions sont chargées :
```
php -m | findstr /I "gd mbstring zip"
```
- Pour une vérification simple depuis le projet, exécute :
```
php check_php_extensions.php
```
(le script `check_php_extensions.php` affiche l'état des extensions gd, mbstring et zip)

Remarque sur PhpSpreadsheet et Dompdf
- PhpSpreadsheet utilise `ext-gd` pour certaines opérations (ex. génération d'images) ; `mbstring` et `zip` sont recommandées.
- Dompdf peut requérir certaines extensions (notamment `gd`) pour le rendu d'images et de polices.

Important
- Ne modifie pas d'autres extensions que celles mentionnées sans savoir exactement pourquoi.
- Si `php --ini` ne retourne aucun "Loaded Configuration File", ne crée pas un php.ini vide automatiquement — vérifie l'installation de PHP et la présence d'un php.ini fourni par la distribution PHP que tu utilises.

</content>

Note importante : le script `scripts/start-dev.sh` prépare l'environnement (crée data/, crée .env si absent, installe les dépendances si nécessaire et applique la migration) mais ne démarre pas le serveur automatiquement — exécutez `composer start` pour lancer le serveur.

Scripts Composer fournis
- composer start       -> php -S localhost:8080 -t public
- composer migrate     -> php bin/migrate.php
- composer check       -> composer validate --no-check-publish
- post-install-cmd / post-update-cmd : rappels si la base SQLite n'existe pas

Scripts utilitaires
- bin/migrate.php : script PHP exécutable qui :
  - lit `.env` (ou `.env.example` si absent),
  - crée `data/` si nécessaire,
  - ouvre/initialise `data/app.db`,
  - exécute `migrations/001_init.sql`,
  - affiche un résumé (tables créées).

- scripts/start-dev.sh : script d'initialisation qui :
  - crée `data/` si manquant,
  - copie `.env.example` -> `.env` si nécessaire,
  - exécute `composer install` si `vendor/` absent,
  - exécute `composer migrate` (ou `php bin/migrate.php`),
  - n'appelle pas `php -S` automatiquement.

Vérifications faites dans bootstrap.php
- Chargement du fichier `.env` via vlucas/phpdotenv (ou fallback vers `.env.example`).
- Création automatique du dossier `data/` si nécessaire.
- Utilisation de PDO SQLite avec la valeur de `DB_PATH` (par défaut `./data/app.db`).
- PDO configuré avec `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`.

Dépannage rapide
- PHP non trouvé : installez PHP 8.2+ et assurez-vous que `php` est dans le PATH.
- Extension PDO_SQLITE manquante : installez/activez l'extension pdo_sqlite (nécessaire pour SQLite).
- Extensions manquantes sous Windows : voir la section « Extensions PHP requises (Windows) » ci‑dessus.
- Composer introuvable : installez Composer ou utilisez l'installateur de Composer.
- Si `composer migrate` échoue : vérifier les permissions du dossier `data/` et le contenu de `migrations/001_init.sql`.

Sécurité / développement
- Le fichier `data/app.db` est ignoré par `.gitignore` (ne pas le committer).
- `.env` est listé dans `.gitignore` — ne pas committer vos secrets.
- Le projet est livré minimal ; validations, contrôle d'accès, et tests doivent être renforcés avant mise en production.

Fichiers clés
- public/index.php — front controller
- src/bootstrap.php — initialisation, container minimal
- src/routes.php — routes
- migrations/001_init.sql — schéma SQLite
- bin/migrate.php — script d'application des migrations
- scripts/start-dev.sh — préparation de l'environnement
- composer.json — scripts utiles (start, migrate, check, post-install-cmd)

Que faire ensuite
- Si tu veux, je peux :
  - Insérer un utilisateur admin par défaut dans la migration (avec mot de passe hashé).
  - Committer et pousser ces changements si tu veux que je le fasse localement (je peux te fournir les commandes).
  - Afficher ici le diff complet des fichiers modifiés/ajoutés (je peux insérer le patch dans la discussion).

Rappel : je n'ai *pas* démarré de serveur ni exécuté de commandes qui modifient ta machine en dehors de la création de fichiers dans le dépôt. Exécute les commandes ci‑dessus dans ton terminal pour initialiser ton environnement local.
