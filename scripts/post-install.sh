#!/usr/bin/env bash
# Post-install cleanup for the clean test PrestaShop container.
# Run manually after the container finishes installing:
#   ./scripts/post-install.sh [container_name]
set -euo pipefail

CONTAINER="${1:-prestashop_clean}"

echo "[post-install] Checking container '$CONTAINER'..."

# Wait for install to complete
until docker exec "$CONTAINER" test -f /var/www/html/app/config/parameters.php 2>/dev/null; do
    echo "[post-install] Waiting for installation to finish..."
    sleep 5
done

# Remove install folder
docker exec "$CONTAINER" bash -c '
    if [ -d /var/www/html/install ]; then
        rm -rf /var/www/html/install
        echo "[post-install] Removed /install"
    else
        echo "[post-install] /install already removed"
    fi
'

# Rename admin folder
ADMIN_DIR=$(docker exec "$CONTAINER" bash -c 'ls -d /var/www/html/admin*/ 2>/dev/null | grep -v admin-api | head -1 | xargs basename')

if [ "$ADMIN_DIR" = "admin" ]; then
    SUFFIX=$(head -c 16 /dev/urandom | xxd -p | head -c 20)
    docker exec "$CONTAINER" mv "/var/www/html/admin" "/var/www/html/admin${SUFFIX}"
    echo "[post-install] Renamed admin → admin${SUFFIX}"
    echo "[post-install] Back office: http://localhost:8199/admin${SUFFIX}/"
else
    echo "[post-install] Admin folder: $ADMIN_DIR"
    echo "[post-install] Back office: http://localhost:8199/${ADMIN_DIR}/"
fi
