# Templates et Interface Utilisateur

## Rôle

Templates Twig pour rendu HTML et interface tactile optimisée pour usage atelier.

## Structure templates

```
templates/
├── layout.twig              # Layout principal (header, nav, footer)
├── dashboard.twig           # Tableau de bord (peu utilisé)
├── admin/
│   ├── dashboard.twig       # Admin home
│   └── prestations.twig     # Gestion catalogue (accordéon catégories)
├── auth/
│   └── login.twig           # Formulaire login
├── clients/
│   ├── index.twig           # Liste clients
│   ├── show.twig            # Fiche client 360° (KPIs, tickets, factures)
│   └── edit.twig            # Form client (create/update)
├── tickets/
│   ├── edit.twig            # Form ticket + sélection prestations tactile
│   └── queue.twig           # File d'attente tickets ouverts
├── devis/
│   ├── builder.twig         # Catalogue/Interface principale (ex /devis/new)
│   └── show.twig            # Affichage devis
├── factures/
│   └── show.twig            # Affichage facture
├── pdf/
│   ├── devis.twig           # Template PDF devis
│   └── facture.twig         # Template PDF facture
├── search/
│   └── results.twig         # Résultats recherche
├── components/
│   └── pave_numerique.twig  # Pavé numérique tactile (non utilisé)
└── velos/
    ├── index.twig           # Liste vélos (legacy, peu utilisé)
    └── edit.twig            # Form vélo (legacy)
```

## Layout principal (layout.twig)

**Structure** :
- `<head>` : Titre, meta, CSS
- `<body>` : Header avec navigation, bloc contenu, footer
- Navigation : Catalogue, Clients, File d'attente, Admin, Recherche, Logout

**CSS** : `public/css/style.css` (inline dans certains templates)

**JavaScript** : Alpine.js 3.15.2 pour interactivité (accordéons, modals, calculs)

## Catalogue (devis/builder.twig)

**Interface principale** : Page d'accueil tactile

**Sections** :
1. **Header client** : Sélection client existant ou "Nouveau client"
2. **Catalogue prestations** : Tuiles par catégories (accordéon Alpine.js)
3. **Panier/Récapitulatif** : Totaux HT/TTC en temps réel
4. **Actions** : 
   - Générer devis PDF direct (sans save DB)
   - Créer client puis ticket

**Interactivité Alpine.js** :
- Calcul totaux en temps réel
- Accordéons catégories
- Modal nouveau client
- Gestion quantités/prix override

**Tuiles catalogue** :
- Prestation classique : libellé + prix MO + prix pièce optionnel
- Vente vélo : libellé + prix libre
- Prestation custom : libellé + prix libre

## Fiche client (clients/show.twig)

**Vue 360°** :

**Bloc KPIs** :
- Tickets ouverts/fermés
- Devis générés
- Factures impayées + montant total impayé
- Dernière activité

**Liste tickets** :
- Affichage chronologique
- Infos vélo (brand, model) affiché
- Lien vers facture si existante
- Actions : éditer ticket

**Interactions** :
- Clic ticket → édition
- Clic facture → affichage PDF
- Bouton "Nouveau ticket"

## File d'attente (tickets/queue.twig)

**Affichage** :
- Tableau tickets status='open'
- Tri chronologique : plus ancien en haut (FIFO)
- Colonnes : Client, Vélo, Date réception, Ancienneté

**But** : Prioriser le travail atelier

## Form ticket (tickets/edit.twig)

**Structure** :

**Bloc client** : Nom, coordonnées (lecture seule)

**Bloc vélo** : 
- Marque, modèle, série, notes
- Champs éditables directement

**Catalogue tactile** :
- Tuiles prestations cliquables
- Quantité + override prix
- Pièces associées optionnelles

**Récapitulatif** :
- Liste lignes ajoutées
- Totaux HT/TTC
- Boutons actions : Sauvegarder, Générer devis, Facturer

**Documents récents** : Devis/factures client affichés pour contexte

## Admin prestations (admin/prestations.twig)

**Interface** :
- Accordéon par catégorie
- Inline editing (AJAX)
- Bouton suppression + undo 5s
- Form ajout prestation inline
- Export Excel

**Auto-save** : 
- Modification → debounce → POST AJAX → retour 204
- Feedback visuel (spinner, checkmark)

**Gestion catégories** :
- Dropdown sélection ou création inline
- Suppression catégorie = suppression toutes prestations associées

## Templates PDF

### pdf/devis.twig

**Contenu** :
- En-tête : Logo entreprise (env), coordonnées
- Client : Nom, adresse (si fournis)
- Numéro devis, date
- Tableau lignes : Libellé, Qté, Prix HT, Total HT
- Totaux : HT, TVA (0), TTC
- Footer : Mentions légales

**Affichage conditionnel** :
- Client optionnel (devis direct)
- Marque vélo optionnelle

### pdf/facture.twig

**Similaire devis avec** :
- Numéro officiel (via NumberingService)
- Mention paiement si réglé (méthode + montant)
- Conditions de paiement
- Coordonnées bancaires (env)

## Composants réutilisables

**pave_numerique.twig** : Pavé numérique tactile (non intégré actuellement)

## JavaScript / Interactivité

**Alpine.js** utilisé pour :
- `x-data` : État local composants
- `x-show` / `x-if` : Affichage conditionnel
- `x-on:click` : Handlers événements
- `x-model` : Binding bidirectionnel
- `x-init` : Initialisation composants

**Exemples patterns** :
```html
<!-- Accordéon catégorie -->
<div x-data="{ open: false }">
  <button @click="open = !open">Toggle</button>
  <div x-show="open">Contenu</div>
</div>

<!-- Calcul total temps réel -->
<input x-model.number="qty">
<input x-model.number="price">
<span x-text="qty * price"></span>
```

## CSS / Styles

**Style inline** : CSS embarqué dans templates pour déploiement simple

**Classes utilitaires** :
- Grille responsive (flexbox)
- Boutons tactiles larges (usage atelier)
- Couleurs cohérentes (bleu primaire, vert succès, rouge danger)

**Optimisations tactile** :
- Boutons min 44x44px (recommandation accessibilité)
- Espacements généreux
- Police lisible (16px base)

## Variables d'environnement dans templates

Accessibles via `env` :
- `COMPANY_NAME` : Nom entreprise
- `COMPANY_ADDRESS` : Adresse
- `COMPANY_EMAIL` : Email contact
- `COMPANY_PHONE` : Téléphone
- Etc.

Usage : `{{ env.COMPANY_NAME }}`

## Points d'attention

- **Pas de framework CSS** : Styles custom pour contrôle total
- **Alpine.js léger** : Alternative à frameworks lourds (React, Vue)
- **Templates compilés** : Twig compile en PHP (cache automatique si activé)
- **Sécurité** : Auto-escape Twig activé par défaut (protection XSS)
