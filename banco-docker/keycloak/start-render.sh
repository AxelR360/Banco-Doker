#!/bin/sh
set -eu

if [ "${KC_RECOVERY_MODE:-false}" = "true" ]; then
    : "${KC_BOOTSTRAP_ADMIN_USERNAME:?Falta KC_BOOTSTRAP_ADMIN_USERNAME}"
    : "${KC_BOOTSTRAP_ADMIN_PASSWORD:?Falta KC_BOOTSTRAP_ADMIN_PASSWORD}"
    echo 'Ejecutando bootstrap-admin en la base configurada.'
    /opt/keycloak/bin/kc.sh bootstrap-admin user --optimized \
        --username "$KC_BOOTSTRAP_ADMIN_USERNAME" \
        --password:env KC_BOOTSTRAP_ADMIN_PASSWORD \
        --no-prompt
fi

exec /opt/keycloak/bin/kc.sh start --optimized --import-realm \
    --http-host=0.0.0.0 --http-port="${PORT:-8080}" "$@"
