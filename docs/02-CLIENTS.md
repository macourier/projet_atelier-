# Module Gestion Clients

## Rôle

CRUD complet des clients avec vue 360° incluant historique tickets, factures et KPIs.

## Fichiers concernés

- `src/Controller/ClientController.php`
- `templates/clients/index.twig`
- `templates/clients/show.twig`
- `templates/clients/edit.twig`

## Logique métier

### Fonctions principales

**index()** : Liste tous les clients (ordre alphabétique)

**show($id)** : Vue détaillée client avec :
- Informations client (nom, adresse, email, phone, note)
- Liste des tickets (avec infos vélo stockées sur ticket)
- KPIs : tickets ouverts/fermés, devis, factures impayées, montant impayé total, dernière activité
- Dernier vélo enregistré (marque/modèle) pour faciliter création nouveau ticket
- Factures liées par ticket

**edit($id)** : Formulaire d'édition/création client
- Pré-remplissage depuis query params lors création depuis catalogue
- Pré-remplissage modèle vélo depuis dernier ticket
- Support paramètre `return` pour redirection post-sauvegarde

**update($id)** : Sauvegarde client
- Create si id=0, Update sinon
- Propage le modèle vélo saisi vers tous les tickets du client ayant modèle vide
- Gestion redirections complexes (retour catalogue, autostart ticket, etc.)

**select($id)** : Sélection client depuis catalogue
- Crée un ticket pour le client
- Insère les prestations postées depuis le panier catalogue
- Enregistre infos vélo si fournies
- Redirige vers tableau de bord client

## Dépendances internes

- Table `clients` (name, address, email, phone, note)
- Table `tickets` (pour affichage historique et stats)
- Table `factures` (pour calcul impayés)
- Table `devis` (pour comptage)
- `TicketService` (pour gestion prestations lors select)

## Points importants

- Le champ `note` client permet annotations privées (migration 013)
- Les infos vélo (brand, model, serial, notes) sont stockées sur le **ticket**, plus sur une entité velo séparée
- KPIs calculés en temps réel lors de chaque affichage (pas de cache)
- Propagation intelligente du modèle vélo vers tickets existants
- Support workflows complexes : catalogue → nouveau client → autostart ticket

## Routes

```
GET  /clients              → index()
GET  /clients/{id}         → show()
GET  /clients/{id}/edit    → edit()
POST /clients/{id}/edit    → update()
POST /clients/{id}/select  → select() [depuis catalogue]
```

## Flux création client

1. Depuis catalogue : clic "Nouveau client"
2. Formulaire pré-rempli avec données saisies (nom, tel, etc.)
3. Sauvegarde → création client
4. Retour catalogue avec `client_id` en paramètre
5. Sélection client → création ticket automatique
