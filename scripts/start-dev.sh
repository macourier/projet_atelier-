#!/usr/bin/env bash
# Script d'initialisation pour préparer l'environnement de développement
# Ne démarre PAS le serveur automatiquement — il prépare data/, .env et les dépendances.
# Usage: ./scripts/start-dev.sh

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

echo "[info] Préparation du projet dans: $ROOT_DIR"

# Ensure data dir exists
if [ ! -d "data" ]; then
  mkdir -p data
  echo "[info] Dossier data/ créé"
else
  echo "[info] Dossier data/ existe"
fi

# Create .env from example if missing
if [ ! -f ".env" ] && [ -f ".env.example" ]; then
  cp .env.example .env
  echo "[info] .env créé à partir de .env.example"
elif [ -f ".env" ]; then
  echo "[info] .env existe"
else
  echo "[warn] Aucun .env.example trouvé ; vérifie la configuration d'environnement"
fi

# Install composer dependencies if vendor/ is missing
if [ ! -d "vendor" ]; then
  echo "[info] Répertoire vendor/ introuvable — exécution de composer install"
  if command -v composer >/dev/null 2>&1; then
    composer install --no-interaction --prefer-dist
  else
    echo "[error] Composer introuvable. Installe Composer ou exécute 'composer install' manuellement."
    exit 2
  fi
else
  echo "[info] Dépendances Composer déjà installées (vendor/ existe)"
fi

# Run migrations using composer script (calls bin/migrate.php)
echo "[info] Exécution de la migration SQLite (composer migrate)"
if command -v composer >/dev/null 2>&1; then
  composer migrate
else
  php bin/migrate.php
fi

echo "[ok] Préparation terminée."
echo "Pour démarrer le serveur de développement, exécute :"
echo "  composer start"
