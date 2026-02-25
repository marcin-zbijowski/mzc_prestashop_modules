#!/bin/bash
# Post-install script: remove /install and rename /admin for security
set -e

echo "[post-install] Waiting for PrestaShop installation to finish..."

# Wait for the install to complete (parameters.php is written at the end)
while [ ! -f /var/www/html/app/config/parameters.php ]; do
    sleep 5
done

# Give it a moment to finish writing
sleep 3

# Remove install folder
if [ -d /var/www/html/install ]; then
    rm -rf /var/www/html/install
    echo "[post-install] Removed /install folder"
fi

# Rename admin folder (skip admin-api)
ADMIN_DIR=$(ls -d /var/www/html/admin*/ 2>/dev/null | grep -v admin-api | head -1)
if [ -n "$ADMIN_DIR" ] && [ "$(basename "$ADMIN_DIR")" = "admin" ]; then
    RANDOM_SUFFIX=$(head -c 16 /dev/urandom | md5sum | head -c 20)
    mv "$ADMIN_DIR" "/var/www/html/admin${RANDOM_SUFFIX}"
    echo "[post-install] Renamed admin folder to admin${RANDOM_SUFFIX}"
else
    echo "[post-install] Admin folder already renamed: $(basename "$ADMIN_DIR")"
fi
