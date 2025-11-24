# Module Gestion Tickets

## Rôle

Gestion des tickets d'intervention/réparation vélo avec prestations associées, génération devis et facturation.

## Fichiers concernés

- `src/Controller/TicketController.php`
- `templates/tickets/edit.twig`
- `templates/tickets/queue.twig`

## Logique métier

### Fonctions principales

**edit($id)** : Formulaire d'édition ticket
- Affiche ticket avec client, prestations, consommables
- Catalogue groupé par catégories pour saisie tactile
- Calcul totaux HT/TTC en temps réel
- Affiche documents récents client (devis/factures)
- Support pré-sélection client via `?client_id=...`

**update($id)** : Sauvegarde ticket
- Create si id=0, Update sinon
- Enregistre infos vélo (brand, model, serial, notes) sur ticket
- Utilise `TicketService` pour fusionner/agréger prestations postées
- Recalcule totaux après sauvegarde
- Support actions spéciales : `_action=devis` ou `_action=devis_pdf`

**queue()** : File d'attente tickets ouverts
- Liste tickets status='open' triés du plus ancien au plus récent
- Utilise colonne `received_at` si disponible (avec fallback sur `created_at`)
- Affiche client, marque/modèle vélo, dates

**devisPreview($id)** : Aperçu HTML du devis
- Intègre les lignes POST "pending" avant génération
- Recalcule totaux
- Rendu template `pdf/devis.twig` en HTML

**devisPdf($id)** : Génération PDF devis
- Utilise `PdfService` pour rendu PDF
- Retourne PDF inline (Content-Disposition: inline)
- Numéro temporaire : DEV-{id}

**facturerConfirm($id)** : Transformation ticket → facture
- Génère numéro de facture via `NumberingService`
- Crée enregistrement `factures` avec totaux
- Enregistre règlement si paiement fourni (CB/ESPÈCE)
- Génère PDF facture via `PdfService` et sauvegarde dans `public/pdfs/factures/`
- Envoie email client avec lien PDF si email présent
- Marque ticket status='invoiced'

## Dépendances internes

- `TicketService` : fusion prestations, calcul totaux
- `PdfService` : génération PDF devis/factures
- `MailerService` : envoi email facture
- `NumberingService` : numérotation factures

## Dépendances externes

- Tables : `tickets`, `ticket_prestations`, `ticket_consommables`, `clients`, `factures`, `reglements`
- Catalogue : `prestations_catalogue`

## Points importants

- Les infos vélo sont stockées directement sur le ticket (bike_brand, bike_model, bike_serial, bike_notes)
- Plus de table `velos` séparée (évolution architecture)
- Les prestations sont "snapshottées" : prix et TVA figés au moment ajout ticket
- Possibilité lignes custom (is_custom=1)
- Fusion intelligente des lignes : si même label+prix+tva → incrémente quantité
- Colonne `received_at` permet tri chronologique réel (vs `created_at`)
- Pas de TVA appliquée : TVA snapshot conservé mais TOTAL = HT

## Routes

```
GET  /tickets/queue                     → queue()
GET  /tickets/{id}/edit                 → edit()
POST /tickets/{id}/edit                 → update()
GET  /tickets/{id}/devis/preview        → devisPreview()
GET  /tickets/{id}/devis/pdf            → devisPdf()
POST /tickets/{id}/facturer/confirm     → facturerConfirm()
```

## Workflow facturation

1. Ticket créé avec prestations
2. Aperçu devis : `/tickets/{id}/devis/preview`
3. Validation → `/tickets/{id}/facturer/confirm` (POST)
4. Création facture + PDF + email
5. Ticket marqué 'invoiced'
6. Redirection vers client avec confirmation
