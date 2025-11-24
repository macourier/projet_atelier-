# Module Administration

## Rôle

Gestion du catalogue de prestations et des catégories, avec import/export Excel.

## Fichiers concernés

- `src/Controller/AdminPrestationsController.php`
- `templates/admin/dashboard.twig`
- `templates/admin/prestations.twig`

## Logique métier

### AdminPrestationsController

**index()** : Liste et gestion du catalogue
- Détection automatique colonnes `piece_libelle` et `piece_prix_ht`
- Auto-réparation : ajoute colonnes manquantes si besoin (ALTER TABLE)
- Affichage prestations groupées par catégories (accordéon)
- Liste catégories pour suppression
- Debug: affiche colonnes détectées et chemins DB

**create()** : Création prestation
- Résolution catégorie : accepte `category_id` ou `categorie` (nom)
- Génère ID aléatoire si non fourni (format: PREST_XXXXXX)
- Champs requis : catégorie, libellé, prix main d'œuvre
- Champs optionnels : pièce (libellé + prix), TVA, durée

**update($id)** : Modification prestation
- Update inline (AJAX, retour 204 No Content)
- Résolution automatique category_id depuis nom catégorie
- Support modification tous champs sauf ID

**delete($id)** : Suppression prestation (soft delete)
- Marque `deleted_at = datetime('now')`
- Stocke ID en session pour undo temporaire (5s)

**undo()** : Annulation suppression
- Restaure dernière prestation supprimée si <5s
- Remet `deleted_at = NULL`

**export()** : Export Excel (XLSX)
- Utilise PhpSpreadsheet
- Colonnes : id, categorie, libelle, prix_main_oeuvre_ht, tva_pct, duree_min
- Exclut prestations supprimées

**createCategory()** : Création catégorie
- INSERT OR IGNORE (évite doublons)
- Redirection avec confirmation

**deleteCategory($id)** : Suppression catégorie
- Transaction : supprime catégorie + toutes prestations associées
- Recherche par `category_id` ET par nom (compat anciennes données)

## Fonctions utilitaires privées

**resolveCategoryId($name)** : Résolution ID catégorie
- Cherche catégorie existante par nom
- Crée nouvelle catégorie si inexistante
- Retourne ID de la catégorie

**generateRandomPrestId()** : Génération ID unique
- Format : `PREST_` + 6 caractères hexadécimaux
- Vérifie unicité en DB (max 10 tentatives)

## Dépendances

- Table `prestations_catalogue` (id TEXT PRIMARY KEY, categorie, category_id, libelle, prix_main_oeuvre_ht, piece_libelle, piece_prix_ht, tva_pct, duree_min, deleted_at)
- Table `categories` (id INTEGER, name TEXT)
- PhpSpreadsheet pour export Excel

## Points importants

### Auto-réparation schéma

Le controller détecte via `PRAGMA table_info` si les colonnes `piece_*` existent.
Si absentes, tente un `ALTER TABLE ADD COLUMN` automatique.

### Pièces (consommables)

Chaque prestation peut avoir une pièce associée :
- `piece_libelle` : nom de la pièce (défaut: "Pièce")
- `piece_prix_ht` : prix HT de la pièce

### Soft delete

Les prestations ne sont jamais vraiment supprimées, juste marquées `deleted_at`.
Permet un undo rapide et conserve historique.

### Catégories

- Table séparée `categories` depuis migration 012
- Champ legacy `categorie` (TEXT) conservé pour compatibilité
- Système hybride : `category_id` (FK) + `categorie` (nom)

## Routes

```
GET  /admin                           → dashboard
GET  /admin/prestations               → index()
POST /admin/prestations               → create()
POST /admin/prestations/undo          → undo()
POST /admin/prestations/{id}          → update()
POST /admin/prestations/{id}/delete   → delete()
GET  /admin/prestations/export.xlsx   → export()
POST /admin/categories                → createCategory()
POST /admin/categories/{id}/delete    → deleteCategory()
```

## Gestion session

Variables session utilisées :
- `last_deleted_prest_id` : ID dernière prestation supprimée
- `last_deleted_time` : Timestamp suppression (pour limite 5s undo)
