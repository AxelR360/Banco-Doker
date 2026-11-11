FROM quay.io/keycloak/keycloak:24.0.5

# Variables de entorno optimizadas para Render (Free Tier 512MB RAM) y Docker Compose
ENV KEYCLOAK_ADMIN=admin \
    KEYCLOAK_ADMIN_PASSWORD=admin \
    KC_HEALTH_ENABLED=true \
    KC_HTTP_ENABLED=true \
    KC_PROXY_HEADERS=xforwarded \
    KC_HOSTNAME_STRICT=false \
    KC_HOSTNAME_STRICT_HTTPS=false \
    KC_SPI_STICKY_SESSION_ENCODER__INFINISPAN__SHOULD_ATTACH_ROUTE=false \
    JAVA_OPTS_APPEND="-Xms64m -Xmx256m -XX:MetaspaceSize=96m -XX:MaxMetaspaceSize=384m -XX:+UseG1GC"

# Copiar el realm con permisos para el usuario keycloak (1000)
COPY --chown=keycloak:keycloak banco-realm.json /opt/keycloak/data/import/banco-realm.json

EXPOSE 8080 10000

# Compatible con Render ($PORT dinámico) y Docker Compose ($PORT omitido -> 8080)
ENTRYPOINT ["/bin/sh", "-c", "PORT_HTTP=${PORT:-8080}; exec /opt/keycloak/bin/kc.sh start-dev --import-realm --http-host=0.0.0.0 --http-port=$PORT_HTTP"]
