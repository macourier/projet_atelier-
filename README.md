# PROJET_ATELIER — Facturation Vélo

But : petite application PHP (Slim 4) pour la gestion de tickets, devis et factures pour un atelier vélo.
Ce dépôt contient une structure minimale prêt à être initialisée en développement (SQLite) et extensible.

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

Installation (développement)
1. Installer les dépendances Composer
   composer install

2. Copier le fichier d'exemple d'environnement et l'éditer si nécessaire
   cp .env.example .env

3. Créer le dossier data s'il n'existe pas
   mkdir -p data

4. Initialiser la base SQLite (exécuter depuis la racine du projet)
   sqlite3 data/app.db < migrations/001_init.sql

   Remarque : le fichier data/app.db est listé dans .gitignore (ne pas le committer).

5. (Optionnel) Créer un utilisateur admin
   - Pour démarrer rapidement, insérez un utilisateur manuellement dans la table `users` :
     INSERT INTO users (email, password_hash) VALUES ('admin@example.com', '<hash>');
   - Vous pouvez générer un hash PHP : <?php echo password_hash('admin', PASSWORD_DEFAULT);

Démarrage serveur de développement (local, ne pas utiliser en production)
- Avec PHP intégré :
  php -S localhost:8080 -t public

- Script fourni :
  ./scripts/start-dev.sh

Notes techniques rapides
- bootstrap.php charge .env, démarre la session, crée PDO (SQLite par défaut) et instancie Twig.
- Routes basiques dans `src/routes.php`.
- Contrôleurs sous `src/Controller/` (AuthController, ClientController, VeloController, TicketController, DevisController, FactureController).
- Services :
  - PdfService (Dompdf) — `src/Service/PdfService.php`
  - MailerService (Symfony Mailer / journalisation) — `src/Service/MailerService.php`
  - NumberingService (séquences persistées en DB) — `src/Service/NumberingService.php`
- Templates Twig : `templates/` (layout, auth, dashboard, clients, velos, tickets, devis, factures, pdf/*)
- Migrations SQL : `migrations/001_init.sql`

CI (GitHub Actions)
- Un workflow de base est fourni pour PHP 8.2 ; il installe les dépendances et exécute un lint basique.

Fichiers importants
- public/index.php — front controller
- src/bootstrap.php — initialisation, container simple
- src/routes.php — routes
- migrations/001_init.sql — schéma SQLite
- templates/ — vues Twig
- data/ — base SQLite (ne pas committer)

Contribuer / remarques
- Le code est volontairement minimal et conçu pour être complété : validations, ACL, tests, et robustesse restent à implémenter.
- Si tu veux que j'ajoute un utilisateur admin par défaut via la migration, dis-le et je l'ajouterai.
