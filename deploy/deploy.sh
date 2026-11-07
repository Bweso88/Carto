#!/usr/bin/env bash
# Installe ou met à jour MeriMap sur le serveur à partir d'une archive de build-release.sh.
# Chaque version est installée dans releases/<date>, puis « current » bascule dessus :
# en cas de problème, on revient en arrière en repointant le lien vers la version précédente.
#
#   sudo ./deploy.sh merimap-release-20261106-1500.tar.gz
set -euo pipefail

ARCHIVE=${1:?Usage : deploy.sh <archive.tar.gz>}
BASE=${MERIMAP_BASE:-/var/www/merimap}
WEB_USER=${MERIMAP_WEB_USER:-www-data}
RELEASE="$BASE/releases/$(date +%Y%m%d-%H%M%S)"

mkdir -p "$BASE/releases" "$BASE/shared/storage"
if [ ! -f "$BASE/shared/.env" ]; then
    echo "✗ $BASE/shared/.env manquant : copiez .env.example, renseignez-le, puis relancez." >&2
    exit 1
fi

echo "→ Extraction dans $RELEASE"
mkdir -p "$RELEASE"
tar -xzf "$ARCHIVE" -C "$RELEASE" --strip-components=1

# Fichiers partagés entre les versions : configuration et données (logs, sessions, fichiers)
ln -sfn "$BASE/shared/.env" "$RELEASE/.env"
if [ ! -d "$BASE/shared/storage/app" ]; then
    cp -a "$RELEASE/storage/." "$BASE/shared/storage/"
fi
rm -rf "$RELEASE/storage"
ln -sfn "$BASE/shared/storage" "$RELEASE/storage"

cd "$RELEASE"
echo "→ Migrations et caches"
php artisan migrate --force
php artisan db:seed --class=ReferenceSeeder --force
php artisan optimize

chown -R "$WEB_USER:$WEB_USER" "$BASE/shared/storage" "$RELEASE/bootstrap/cache"

echo "→ Bascule de la version active"
ln -sfn "$RELEASE" "$BASE/current"
systemctl reload php8.3-fpm 2>/dev/null || true

# On garde les 5 dernières versions
ls -1dt "$BASE"/releases/*/ | tail -n +6 | xargs -r rm -rf

echo "✓ MeriMap déployé : $RELEASE"
