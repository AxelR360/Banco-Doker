#!/bin/sh
set -eu

# One-time recovery for an existing master realm, using the configured DB.
if [ "${KC_RECOVERY_MODE:-false}" = "true" ]; then
    : "${KC_BOOTSTRAP_ADMIN_USERNAME:?Falta KC_BOOTSTRAP_ADMIN_USERNAME en Render}"
    : "${KC_BOOTSTRAP_ADMIN_PASSWORD:?Falta KC_BOOTSTRAP_ADMIN_PASSWORD en Render}"
    echo 'Ejecutando la recuperación administrativa sobre la base configurada.'
    /opt/keycloak/bin/kc.sh bootstrap-admin user \
        --username "$KC_BOOTSTRAP_ADMIN_USERNAME" \
        --password:env KC_BOOTSTRAP_ADMIN_PASSWORD \
        --no-prompt
fi

exec /opt/keycloak/bin/kc.sh start-dev --import-realm \
    --http-host=0.0.0.0 --http-port="${PORT:-8080}" "$@"
