FROM quay.io/keycloak/keycloak:26.8.2
ENV KC_DB=mysql
RUN /opt/keycloak/bin/kc.sh build
COPY banco-public-realm.json /opt/keycloak/data/import/banco-digital-realm.json
CMD ["start", "--optimized", "--import-realm"]
