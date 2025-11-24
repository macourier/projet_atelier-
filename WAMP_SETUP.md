# Configuration de WAMP pour PROJET_ATELIER

## Prérequis

- WAMP installé (testé avec WAMP 3.x)
- PHP 8.2+
- Composer
- Extensions PHP requises : gd, mbstring, zip, pdo_sqlite

## Étapes de configuration

### 1. VirtualHost Apache

Ouvrir le fichier `C:\wamp64\bin\apache\apache2.x.y\conf\extra\httpd-vhosts.conf`

Ajouter le VirtualHost suivant :

```apacheconf
<VirtualHost *:80>
    ServerName projetatelier.local
    DocumentRoot "c:/Users/Utilisateur/Desktop/projet_atelier/public"

    <Directory "c:/Users/Utilisateur/Desktop/projet_atelier/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 2. Configuration du fichier hosts

Éditer `C:\Windows\System32\drivers\etc\hosts` (en mode administrateur)

Ajouter :
```
127.0.0.1    projetatelier.local
```

### 3. Activation des modules Apache

Vérifier dans `httpd.conf` que les modules suivants sont actifs :
- `LoadModule rewrite_module modules/mod_rewrite.so`

### 4. Préparation du projet

Dans le dossier du projet, exécuter :
```powershell
composer install
Copy-Item .env.example .env
composer migrate
```

### 5. Création du .htaccess

Créer `public/.htaccess` avec le contenu :

```apacheconf
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

### 6. Redémarrage de WAMP

- Cliquer sur l'icône WAMP
- Sélectionner "Restart All Services"

### Accès à l'application

Ouvrir dans le navigateur : 
`http://projetatelier.local`

## Dépannage

- Vérifier les logs Apache si problème
- S'assurer que toutes les extensions PHP sont activées
- Vérifier les permissions des dossiers
