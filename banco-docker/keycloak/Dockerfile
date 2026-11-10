FROM quay.io/keycloak/keycloak:26.8.2
COPY banco-public-realm.json /opt/keycloak/data/import/banco-digital-realm.json
CMD ["start", "--import-realm"]
