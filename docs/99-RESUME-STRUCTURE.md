# Résumé et Structure générale du projet

## Vue d'ensemble

**PROJET_ATELIER** est une application web PHP de gestion de facturation pour atelier vélo.

- **Framework** : Slim 4 (micro-framework PSR-7)
- **Base de données** : SQLite (extensible MySQL/PostgreSQL)
- **Templating** : Twig 3
- **PDF** : Dompdf
- **Email** : Symfony Mailer
- **UI** : Alpine.js pour interactivité tactile

## Architecture en 3 couches

```
┌─────────────────────────────────────┐
│  PRESENTATION (Templates Twig)      │
│  - Views HTML                       │
│  - Alpine.js (interactivité)        │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│  BUSINESS (Controllers + Services)  │
│  - Contrôleurs métier               │
│  - Services réutilisables           │
│  - Middleware Auth                  │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│  DATA (SQLite + Migrations)         │
│  - Tables relationnelles            │
│  - Migrations versionnées           │
└─────────────────────────────────────┘
```

## Modules fonctionnels

### 1. Authentification
- Login/Logout basé sessions
- Protection routes via middleware
- Pas de système de rôles avancé (prévu mais non implémenté)

### 2. Gestion Clients
- CRUD complet
- Vue 360° : KPIs, historique tickets/factures
- Champ note pour annotations privées
- Propagation automatique infos vélo vers tickets

### 3. Gestion Tickets
- Création/édition interventions vélo
- Infos vélo stockées directement sur ticket (bike_brand, bike_model, bike_serial, bike_notes)
- Prestations avec snapshot prix/TVA
- File d'attente chronologique (FIFO)
- Transformation ticket → facture

### 4. Catalogue de Prestations
- Interface tactile principale (`/catalogue`)
- Prestations groupées par catégories
- Chaque prestation = main d'œuvre + pièce optionnelle
- Soft delete (restauration possible)
- Export Excel

### 5. Devis
- **Mode direct** : PDF sans persistance DB (estimations rapides)
- **Mode ticket** : Aperçu puis facturation
- Numérotation : DIRECT-timestamp ou DEV-{id}

### 6. Factures
- Génération depuis ticket
- Numérotation séquentielle (NumberingService)
- PDF sauvegardé dans `public/pdfs/factures/`
- Gestion paiements (CB/ESPÈCE)
- Envoi email automatique client

### 7. Administration
- Gestion catalogue prestations
- Gestion catégories
- Auto-réparation schéma DB
- Inline editing (AJAX)
- Undo suppression (5 secondes)

### 8. Recherche
- Recherche globale clients/tickets/factures (SearchController présent mais peu documenté dans les fichiers lus)

## Flux métier principal

```
1. Utilisateur se connecte (/login)
          ↓
2. Accède au catalogue (/catalogue)
          ↓
3. Sélectionne client existant OU crée nouveau client
          ↓
4. Ajoute prestations depuis interface tactile
          ↓
5a. Génère devis PDF direct (estimation rapide)
    → PDF ouvert dans nouvel onglet
    → Aucune sauvegarde DB
          OU
5b. Crée ticket persisté avec prestations
          ↓
6. Depuis ticket : génère aperçu devis
          ↓
7. Valide → transformation en facture
          ↓
8. Facture créée :
   - Numéro séquentiel généré
   - PDF sauvegardé
   - Email envoyé au client
   - Ticket marqué "invoiced"
```

## Points techniques clés

### Snapshot des prix
Les prix et TVA sont "figés" au moment de l'ajout d'une prestation à un ticket.
Permet de conserver l'historique même si le catalogue évolue.

### Fusion intelligente prestations
Lors de l'ajout de prestations, le système agrège les lignes identiques :
- Clé de fusion : `"type|label|prix|tva"`
- Si existe : incrémentation quantité
- Sinon : insertion nouvelle ligne

### Pas de TVA appliquée
Bien que le champ `tva_pct` existe, le calcul final est : **TOTAL = HT**
(TVA conservée pour compatibilité future)

### Évolution architecture DB

**Vélos** : Table séparée → Champs sur ticket
**Catalogue** : ID numérique → ID texte (PREST_XXXXXX)
**Pièces** : Table séparée → Champs sur prestation catalogue

### Auto-réparation schéma
Le contrôleur Admin détecte colonnes manquantes et applique `ALTER TABLE` automatiquement.

## Structure fichiers

```
projet_atelier/
├── bin/                    # Scripts CLI
│   ├── migrate.php         # Application migrations
│   └── create_admin.php    # Création utilisateur admin
├── data/                   # Base SQLite (gitignore)
├── docs/                   # Documentation projet (ce dossier)
├── migrations/             # Migrations SQL versionnées (001→014)
├── public/                 # Document root web
│   ├── index.php           # Front controller
│   ├── css/                # Styles
│   ├── js/                 # Alpine.js
│   └── pdfs/factures/      # PDFs générés
├── src/
│   ├── bootstrap.php       # Init app (PDO, Twig, container)
│   ├── routes.php          # Définition routes
│   ├── Controller/         # 8 contrôleurs métier
│   ├── Middleware/         # AuthMiddleware
│   └── Service/            # 4 services (PDF, Mailer, Numbering, Ticket)
├── templates/              # Templates Twig
│   ├── layout.twig         # Layout principal
│   ├── admin/              # Interface admin
│   ├── clients/            # CRUD clients
│   ├── tickets/            # Gestion tickets
│   ├── devis/              # Catalogue + devis
│   ├── factures/           # Affichage factures
│   └── pdf/                # Templates PDF
├── .env                    # Configuration (gitignore)
├── .env.example            # Template configuration
└── composer.json           # Dépendances + scripts
```

## Services réutilisables

1. **PdfService** : Génération PDF via Dompdf
2. **MailerService** : Envoi emails + journalisation
3. **NumberingService** : Numérotation séquentielle documents
4. **TicketService** : Gestion prestations tickets + calculs totaux

## Container DI minimal

Container = tableau associatif avec lazy loading :
```php
$container['services']['pdf'] = function() { return new PdfService(...); };
$pdfService = $container['get']('pdf'); // Instanciation à la demande
```

## Sécurité

- Password hash avec `password_hash()` / `password_verify()`
- Auto-escape Twig (protection XSS)
- Foreign keys SQLite activées
- Middleware auth sur routes protégées
- `.env` et `data/` ignorés par git

## Déploiement

**Dev** :
```bash
composer install
cp .env.example .env
composer migrate
php bin/create_admin.php
composer start  # → http://localhost:8080
```

**Prod** :
- Configurer web server (Apache/Nginx) → `public/` en document root
- Configurer MAILER_DSN pour envoi emails réels
- Configurer DB externe si nécessaire (MySQL/PostgreSQL)
- Activer cache Twig
- Désactiver APP_DEBUG

## Extensions requises

- `pdo_sqlite` (ou pdo_mysql/pdo_pgsql)
- `gd` (images PDF)
- `mbstring` (manipulation chaînes)
- `zip` (PhpSpreadsheet export)

## Documentation complète

1. **00-OVERVIEW.md** : Vue d'ensemble projet
2. **01-AUTHENTIFICATION.md** : Système auth + middleware
3. **02-CLIENTS.md** : Gestion clients + vue 360°
4. **03-TICKETS.md** : Tickets + facturation
5. **04-DEVIS-FACTURES.md** : Génération devis/factures
6. **05-ADMINISTRATION.md** : Gestion catalogue
7. **06-SERVICES.md** : Services métier détaillés
8. **07-BASE-DE-DONNEES.md** : Schéma + migrations
9. **08-TEMPLATES-UI.md** : Interface utilisateur
10. **99-RESUME-STRUCTURE.md** : Ce fichier

## Améliorations possibles

- Système de rôles/permissions utilisateurs
- Gestion stock pièces
- Statistiques/tableaux de bord analytiques
- Export comptable
- Multi-utilisateurs simultanés (gestion conflits)
- API REST pour intégrations tierces
- Notifications temps réel (WebSockets)
- Gestion relances factures impayées
- Sauvegarde automatique base de données
