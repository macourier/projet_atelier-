# Services métier

## Rôle

Services réutilisables encapsulant la logique métier complexe.

## PdfService

**Fichier** : `src/Service/PdfService.php`

**Rôle** : Génération de documents PDF via Dompdf

**Méthodes** :
- `renderPdf($template, $context, $options)` : Rend un template Twig en PDF (binaire)
- `savePdf($template, $context, $path, $options)` : Génère et sauvegarde PDF sur disque

**Configuration Dompdf** :
- `isRemoteEnabled: true` : Autorise chargement ressources distantes (images, CSS)
- `defaultFont: 'DejaVu Sans'` : Police par défaut
- `isHtml5ParserEnabled: true` : Parser HTML5

**Options supportées** :
- `paper` : Format papier (a4, letter, etc.)
- `orientation` : portrait ou landscape

**Utilisation** :
```php
$pdfService = $container['get']('pdf');
$pdf = $pdfService->renderPdf('pdf/facture.twig', ['facture' => $data]);
$pdfService->savePdf('pdf/devis.twig', $context, '/path/to/file.pdf');
```

---

## TicketService

**Fichier** : `src/Service/TicketService.php`

**Rôle** : Gestion prestations tickets et calculs

**Méthodes principales** :

### loadCatalogueGrouped()
- Charge prestations catalogue groupées par catégories
- Gestion fallback si colonnes `piece_*` absentes
- Fusion avec table `categories` pour inclure catégories vides
- Retourne : `['Nom Categorie' => [prestations...], ...]`

### computeTotals($ticketId)
- Calcule total_ht depuis `ticket_prestations` et `ticket_consommables`
- Pas de TVA : total_ttc = total_ht
- Persiste totaux sur ticket (UPDATE)
- Retourne : `['ht' => float, 'tva' => float, 'ttc' => float]`

### replacePrestationsFromPost($ticketId, $post)
- **Logique complexe de fusion/agrégation des prestations**
- Agrège lignes identiques (même label + prix + tva) → incrémente quantité
- Gère prestations main d'œuvre ET pièces associées
- Support lignes custom (vente vélo, prestations libres)
- Utilise clé de fusion : `"type|label|prix|tva"`
- UPDATE lignes existantes ou INSERT nouvelles lignes

**Flux replacePrestationsFromPost** :
1. Parse POST arrays : `prest_id[]`, `qty[]`, `price_override[]`, `piece_qty[]`, `piece_price_override[]`
2. Charge infos depuis catalogue
3. Construit agrégats pending (MO + Pièces)
4. Charge lignes existantes en DB
5. Pour chaque clé : UPDATE si existe, INSERT sinon
6. Traite lignes custom (bike, prestation, pièce)

**Points sensibles** :
- Fusion par clé composite évite doublons
- Snapshot prix/TVA au moment insertion
- Support bases non migrées (fallback colonnes manquantes)

---

## MailerService

**Fichier** : `src/Service/MailerService.php`

**Rôle** : Envoi emails via Symfony Mailer avec fallback journalisation

**Configuration** : DSN Symfony Mailer (MAILER_DSN dans .env)

**Méthodes** :

### send($to, $subject, $body, $from)
- Envoi email HTML
- Si mailer configuré : envoi réel via transport
- Sinon : journalisation en table `journal_emails` (status='pending')
- Retourne : true si envoyé/journalisé, false si erreur

### logEmail() (privée)
- Enregistre email dans `journal_emails`
- Fallback fichier `data/emails.log` si pas de DB
- Statuts : 'sent', 'error', 'pending'

**Points importants** :
- Mode dev : emails journalisés sans envoi réel
- Mode prod : emails envoyés + journalisés pour traçabilité
- Pas de blocage application si envoi échoue (try/catch)

---

## NumberingService

**Fichier** : `src/Service/NumberingService.php`

**Rôle** : Génération numéros de séquence (factures, devis, etc.)

**Méthodes** :

### next($name, $pad)
- Génère prochain numéro pour séquence `$name`
- Utilise table `sequences` : (name, prefix, last_number)
- Transaction pour éviter doublons concurrents
- Format : `{prefix}{last_number}` paddé
- Crée séquence si inexistante (commence à 1)
- Fallback timestamp si DB indisponible

### current($name)
- Lit valeur actuelle séquence sans incrémenter
- Retourne : int|null

**Utilisation** :
```php
$numService = new NumberingService($pdo, '2025-');
$numero = $numService->next('facture', 4); // → "2025-0001"
```

**Configuration** :
- Prefix via constructeur ou environnement (NUMSEQ_FACTURE_PREFIX)
- Pad length : nombre de zéros (4 → 0001)

---

## Dépendances entre services

```
TicketController
  ├─> TicketService (prestations, totaux)
  ├─> PdfService (génération PDF)
  ├─> MailerService (envoi facture)
  └─> NumberingService (numéro facture)

DevisController
  ├─> TicketService (catalogue)
  └─> PdfService (PDF)

AdminPrestationsController
  └─> (aucun service, accès direct PDO)
```

## Lazy loading services

Services instanciés à la demande via container :
```php
$container['services']['pdf'] = function() { return new PdfService(...); };
$pdfService = $container['get']('pdf'); // Instanciation
