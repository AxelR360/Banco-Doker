FROM quay.io/keycloak/keycloak:26.7.4

# Variables de entorno optimizadas para Render (Free Tier 512MB RAM) y Docker Compose
ENV KC_HEALTH_ENABLED=true \
    KC_HTTP_ENABLED=true \
    KC_PROXY_HEADERS=xforwarded \
    KC_HOSTNAME_STRICT=false \
    KC_HOSTNAME_STRICT_HTTPS=false \
    KC_SPI_STICKY_SESSION_ENCODER__INFINISPAN__SHOULD_ATTACH_ROUTE=false \
    JAVA_OPTS_KC_HEAP="-XX:InitialRAMPercentage=15 -XX:MaxRAMPercentage=40" \
    JAVA_OPTS_APPEND="-XX:MaxMetaspaceSize=160m -XX:ReservedCodeCacheSize=48m -XX:+UseSerialGC"

# Copiar el realm con permisos para el usuario keycloak (1000)
COPY --chown=keycloak:keycloak banco-realm.json /opt/keycloak/data/import/banco-realm.json
COPY --chown=keycloak:keycloak start-render.sh /opt/keycloak/bin/start-render.sh

EXPOSE 8080 10000

# Compatible con Render ($PORT dinámico) y Docker Compose ($PORT omitido -> 8080)
ENTRYPOINT ["/bin/sh", "/opt/keycloak/bin/start-render.sh"]
