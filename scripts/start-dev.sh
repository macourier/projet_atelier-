#!/usr/bin/env bash
# Script de démarrage pour le serveur de développement (PHP intégré)
# Usage: ./scripts/start-dev.sh
set -e

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

# Ensure data dir exists
mkdir -p data

echo "Démarrage du serveur de développement sur http://localhost:8080"
php -S localhost:8080 -t public
