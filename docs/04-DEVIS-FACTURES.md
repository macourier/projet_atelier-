# Module Devis et Factures

## Rôle

Génération de devis (persistés ou directs) et gestion des factures avec paiements.

## Fichiers concernés

- `src/Controller/DevisController.php`
- `src/Controller/FactureController.php`
- `templates/devis/builder.twig`
- `templates/devis/show.twig`
- `templates/factures/show.twig`
- `templates/pdf/devis.twig`
- `templates/pdf/facture.twig`

## Logique métier

### DevisController

**builder()** : Catalogue/Interface de création devis
- Page d'accueil de l'app (ex `/devis/new`, maintenant `/catalogue`)
- Affiche catalogue prestations groupé par catégories
- Interface tactile pour sélection prestations
- Support pré-sélection client via `?client_id=...`
- Deux modes : devis direct (PDF sans DB) ou ticket persisté

**show($id)** : Affichage devis persisté (actuellement peu utilisé)

**directPdf()** : Génération PDF devis sans persistance
- Récupère prestations depuis POST (panier catalogue)
- Support lignes custom prestations/pièces
- Génère PDF éphémère (numéro DIRECT-{timestamp})
- Client et marque vélo optionnels (affichage conditionnel)
- Buffer cleaning avant envoi PDF pour éviter corruption
- Retourne PDF inline (nouvel onglet navigateur)

### FactureController

**show($id)** : Affichage facture
- Récupère facture et client associés
- Template affiche infos facture, PDF path, statut paiement

## Dépendances

- `PdfService` : génération PDF via Dompdf
- `TicketService` : chargement catalogue groupé
- Tables : `devis`, `factures`, `clients`, `reglements`

## Points importants

### Devis direct vs Ticket

- **Devis direct** : Pas de sauvegarde DB, PDF généré à la volée, utile pour estimations rapides
- **Via ticket** : Création ticket → devis preview → facturation → PDF avec numéro officiel

### Structure données PDF

Lignes devis/facture :
```php
[
  'label' => string,
  'quantite' => int,
  'prix_ht_snapshot' => float,
  'tva_snapshot' => float
]
```

### Numérotation

- Devis direct : `DIRECT-{YmdHis}`
- Devis ticket : `DEV-{ticket_id}`
- Factures : via `NumberingService` (ex: `2025-0001`)

### Paiements

- Enregistrés dans table `reglements`
- Méthodes supportées : CB, ESPÈCE
- Mise à jour automatique statut facture (unpaid → paid)
- Affichage info paiement sur PDF facture

## Routes

```
GET  /catalogue                    → builder() [ex /devis/new]
GET  /devis/new                    → builder()
POST /devis/pdf/direct             → directPdf()
GET  /devis/{id}/show              → show()
GET  /factures/{id}/show           → show()
```

## Workflow devis direct

1. Sélection prestations dans catalogue
2. Saisie optionnelle : nom client, téléphone, marque vélo
3. Clic "Générer devis PDF"
4. POST → `/devis/pdf/direct`
5. Affichage PDF dans nouvel onglet

## Workflow facturation complète

Voir doc `03-TICKETS.md` → méthode `facturerConfirm()`
