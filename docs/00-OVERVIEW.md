# Vue d'ensemble du projet - PROJET_ATELIER

## Description générale

Application web PHP de gestion de facturation pour un atelier vélo utilisant Slim 4 comme framework.

**Version PHP**: 8.2+
**Base de données**: SQLite (production possible avec MySQL/PostgreSQL)
**Framework**: Slim 4 (micro-framework PSR-7/PSR-15)
**Templating**: Twig 3
**PDF**: Dompdf 2.0
**Email**: Symfony Mailer 6.0

## Architecture globale

- Architecture MVC simplifiée (Contrôleurs, Services, Templates)
- Container de dépendances minimal (tableau associatif avec lazy loading)
- Routing centralisé dans `src/routes.php`
- Middleware d'authentification session-based
- Migrations SQL versionnées dans `migrations/`

## Modules fonctionnels

1. **Authentification** : Login/Logout simple avec sessions PHP
2. **Gestion Clients** : CRUD clients avec vue 360° (tickets, factures, KPIs)
3. **Gestion Tickets** : Création et suivi des interventions/réparations vélo
4. **Catalogue de prestations** : Services proposés organisés par catégories
5. **Devis** : Génération de devis (avec ou sans persistance en DB)
6. **Factures** : Création automatique depuis tickets, génération PDF, gestion paiements
7. **Recherche globale** : Recherche unifiée clients/tickets/factures
8. **Administration** : Gestion du catalogue (prestations, catégories, export Excel)
9. **File d'attente** : Visualisation chronologique des tickets ouverts

## Points d'entrée

- **Page d'accueil** : `/` → redirige vers `/catalogue`
- **Catalogue/Builder** : `/catalogue` (ex `/devis/new`) - Interface tactile de création
- **Login** : `/login`
- **Admin** : `/admin` et `/admin/prestations`
- **File d'attente** : `/tickets/queue`
- **Clients** : `/clients`
- **Recherche** : `/search`

## Fichiers de configuration

- `.env` : Configuration environnement (DB, mail, entreprise)
- `composer.json` : Dépendances et scripts
- `src/bootstrap.php` : Initialisation app (PDO, Twig, container)
- `src/routes.php` : Définition des routes
- `public/index.php` : Front controller

## Structure des dossiers

```
projet_atelier/
├── bin/              # Scripts CLI (migrations, utilitaires)
├── data/             # Base SQLite (ignoré git)
├── migrations/       # Migrations SQL versionnées
├── public/           # Racine web (index.php, assets, PDFs générés)
├── src/
│   ├── Controller/   # Contrôleurs métier
│   ├── Middleware/   # Middlewares (auth)
│   └── Service/      # Services réutilisables
├── templates/        # Templates Twig
└── vendor/           # Dépendances Composer
```

## Workflow typique

1. Utilisateur se connecte via `/login`
2. Accède au catalogue (`/catalogue`) pour créer un devis/ticket
3. Sélectionne un client existant ou en crée un nouveau
4. Ajoute des prestations depuis le catalogue tactile
5. Génère un devis PDF direct OU crée un ticket persisté
6. Depuis un ticket, peut générer devis puis facturer
7. La facture génère un PDF et peut envoyer un email au client
