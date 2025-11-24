# Module Authentification

## Rôle

Gestion de l'authentification utilisateur basée sur sessions PHP.

## Fichiers concernés

- `src/Controller/AuthController.php`
- `src/Middleware/AuthMiddleware.php`
- `templates/auth/login.twig`

## Logique métier

### AuthController

**Responsabilités** :
- Affichage du formulaire de login
- Vérification des identifiants (email + mot de passe)
- Création/destruction de session utilisateur
- Redirection post-login vers `/catalogue`

**Méthodes principales** :
- `showLogin()` : Affiche le formulaire de connexion
- `handleLogin()` : Traite la soumission du formulaire (vérification password_hash)
- `logout()` : Détruit la session et redirige vers `/login`

### AuthMiddleware

**Responsabilités** :
- Protège les routes nécessitant une authentification
- Vérifie la présence de `$_SESSION['user_id']`
- Redirige vers `/login` si non authentifié
- Retourne 401 JSON pour les requêtes AJAX

**Appliqué sur** : Toutes les routes sauf `/login` et `/logout` (via groupe dans `routes.php`)

## Dépendances

- Table `users` (email, password_hash)
- Sessions PHP natives
- PDO pour requête utilisateur

## Points importants

- Mot de passe hashé avec `password_hash()` / `password_verify()`
- Session régénérée lors du logout pour sécurité
- Messages d'erreur stockés temporairement dans `$_SESSION['auth_error']`
- Pas de système de rôles avancés actuellement (champ `roles` présent mais non utilisé)

## Route d'authentification

```
GET  /login  → showLogin()
POST /login  → handleLogin()
GET  /logout → logout()
```

## Améliorations possibles

- Système de rôles/permissions
- Remember me (cookie persistant)
- Limitation tentatives de connexion
- Double authentification (2FA)
- Reset password par email
