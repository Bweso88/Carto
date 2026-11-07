#!/usr/bin/env bash
# Construit une archive prête à déployer, AVEC les dépendances (vendor/) et le frontend compilé.
# À lancer sur un poste qui a accès à Internet. Le serveur cible n'a alors besoin
# ni d'Internet, ni de Composer, ni de Node.js.
#
#   ./deploy/build-release.sh            -> merimap-release-AAAAMMJJ-HHMM.tar.gz
set -euo pipefail

cd "$(dirname "$0")/.."
STAMP=$(date +%Y%m%d-%H%M)
OUT="merimap-release-${STAMP}.tar.gz"
TMP=$(mktemp -d)

echo "→ Copie des sources"
rsync -a --exclude-from=<(printf '%s\n' .git node_modules vendor .env 'storage/logs/*' 'database/*.sqlite' 'merimap-release-*' public/hot) ./ "$TMP/merimap/"

cd "$TMP/merimap"
echo "→ Dépendances PHP (sans les outils de développement)"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "→ Compilation du frontend"
npm ci --no-audit --no-fund
php artisan wayfinder:generate --with-form
npm run build
rm -rf node_modules

cd "$TMP"
tar -czf "$OLDPWD/$OUT" merimap
rm -rf "$TMP"
echo "✓ Archive prête : $OUT"
