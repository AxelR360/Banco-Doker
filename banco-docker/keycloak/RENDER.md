# Recuperar Keycloak en Render

El servicio usa Keycloak 26.7.4. Conserva las variables de conexión KC_DB, KC_DB_URL, KC_DB_USERNAME y KC_DB_PASSWORD que ya apuntan a la base de Aiven. Antes de actualizar, confirma que hay una copia reciente de esa base.

Para crear un administrador temporal en el realm master:

1. En Environment del servicio Keycloak, conserva todas las variables de base y configura KC_RECOVERY_MODE=true, KC_BOOTSTRAP_ADMIN_USERNAME=recuperacion y KC_BOOTSTRAP_ADMIN_PASSWORD con una contraseña fuerte nueva.
2. Despliega esta versión. El arranque ejecutará kc.sh bootstrap-admin user contra la misma base y luego iniciará el servidor.
3. Entra a https://banco-keycloak.onrender.com/admin/master/console/ con ese usuario temporal.
4. En Users, restablece las credenciales del administrador que quieres conservar.
5. Desactiva KC_RECOVERY_MODE y elimina las dos variables KC_BOOTSTRAP_ADMIN_*. Despliega otra vez. Comprueba que el acceso permanente funciona y elimina el administrador temporal.

No borres ni cambies la base de Aiven. Keycloak migra el esquema al pasar de 24 a 26; no restaures una copia antigua del código contra la base ya migrada. Si hay que revertir, primero restaura la copia de seguridad compatible.

El modo normal no ejecuta el comando de recuperación. Solo se activa con KC_RECOVERY_MODE=true.
