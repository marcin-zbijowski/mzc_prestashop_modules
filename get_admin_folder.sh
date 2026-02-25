#!/usr/bin/env bash
set -euo pipefail

CONTAINER="${1:-prestashop_clean}"

ADMIN_DIR=$(docker exec "$CONTAINER" bash -c 'ls -d /var/www/html/admin*/ 2>/dev/null | grep -v admin-api | head -1 | xargs basename')

if [ -z "$ADMIN_DIR" ]; then
    echo "Error: no admin folder found in container '$CONTAINER'"
    exit 1
fi

echo "$ADMIN_DIR"
