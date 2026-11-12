# Sistema de Gestión Bancaria Digital

Proyecto académico del Grupo 4 de la UTLVTE con datos de demostración.

## Arrancar en Docker Desktop

Desde la carpeta `banco-docker`, con Docker Desktop en modo de contenedores Linux:

```powershell
docker compose up -d --build --wait
docker compose ps
```

El [Dockerfile del backend](backend/Dockerfile) construye PHP 8.3 con Apache, PDO MySQL y las dependencias de Composer. [Docker Compose](docker-compose.yml) levanta MySQL 8.4 como servicio separado, compila React y configura Nginx y Keycloak. Los servicios publicados escuchan en la interfaz local.

| Servicio | Acceso |
| --- | --- |
| React y API | http://localhost |
| Vistas PHP clásicas | http://localhost/login.php |
| Consola de Keycloak | http://localhost:8085/admin/ |
| phpMyAdmin | http://localhost:8081 |
| MySQL | `127.0.0.1:3307`, base `banco_digital` |

El usuario de MySQL para una instalación nueva es `banco_user`, con clave `12345` si no se cambia en `.env`. La consola de Keycloak usa `admin` y `admin123`. En el banco, los usuarios de prueba `admin`, `asesor1`, `axel.rosero` y `melany.zambrano` usan `12345` tanto en el formulario local como en Keycloak. El realm `banco-digital` se importa al arrancar Keycloak.

El backend genera una clave JWT al arrancar. Para conservar los tokens locales entre reinicios, define una clave propia de al menos 32 caracteres en `BANCO_JWT_SECRET` en `.env`.

### Volúmenes MySQL creados con una versión anterior

Para habilitar el registro con contraseña en un volumen existente, comprueba si existe la columna nueva. Si el primer comando no devuelve filas, ejecuta el segundo una vez. Esto conserva las cuentas y los movimientos.

```powershell
docker compose exec mysql mysql -uroot -p banco_digital -e "SHOW COLUMNS FROM usuarios LIKE 'password_hash';"
docker compose exec mysql mysql -uroot -p banco_digital -e "ALTER TABLE usuarios ADD COLUMN password_hash VARCHAR(255) NULL;"
```

La base nueva aplica este cambio al inicializarse. Las cuentas creadas mediante formularios antiguos carecen de una contraseña recuperable. Para habilitar su acceso local, un administrador de la base debe asignarles un hash generado con `password_hash` de PHP. Los usuarios de demostración conservan sus credenciales. El registro en el banco crea credenciales locales.

## Render y Aiven

El backend publicado usa el mismo Dockerfile, con `banco-docker/backend` como contexto. La API pública se sirve en `https://banco-bakend.onrender.com/api/v1`. Configura en Render `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` y `DB_SSL=1` con la conexión de Aiven. Para Keycloak configura `KEYCLOAK_REALM_URL` con la URL HTTPS del realm publicado y `VITE_KEYCLOAK_URL` con la URL HTTPS de Keycloak al compilar el frontend.

Para habilitar el registro en una base Aiven anterior, ejecuta el `SHOW COLUMNS` y, si falta la columna, el `ALTER TABLE` de arriba desde tu cliente SQL conectado a Aiven. Usa el esquema configurado en `DB_NAME`. Las cuentas locales existentes requieren su propio hash de contraseña o acceso mediante un usuario vinculado en Keycloak. Las claves de demostración de Docker están deshabilitadas en Render.

## API REST

Base local `http://localhost/api/v1`. El inicio de sesión devuelve el token en `data.token`. Envía `Authorization: Bearer <token>` en las rutas protegidas. Las respuestas exitosas incluyen los resultados en `data`.

### Consultar cuentas y saldos por cédula

```powershell
$login = Invoke-RestMethod -Method Post -Uri 'http://localhost/api/v1/auth/login' `
  -ContentType 'application/json' -Body (@{username='asesor1'; password='12345'} | ConvertTo-Json)
$headers = @{ Authorization = "Bearer $($login.data.token)" }
(Invoke-RestMethod -Uri 'http://localhost/api/v1/clientes/cedula/0801000001/cuentas' -Headers $headers).data
```

La ruta acepta una cédula de diez dígitos y devuelve una fila por cuenta con `id_cuenta`, `numero_cuenta`, `tipo`, `moneda`, `saldo` y `estado`. Solo pueden usarla ADMIN y ASESOR. Una cédula válida sin cliente devuelve 404. Un cliente existente sin cuentas devuelve una lista vacía. Para consultar tu propio saldo como CLIENTE usa `GET /cuentas/mias`, o `GET /cuentas/{id}` para una cuenta específica. En Render usa `https://banco-bakend.onrender.com/api/v1/clientes/cedula/{cedula}/cuentas` con un token de esa instalación.

### Endpoints

Todas las rutas de esta tabla se agregan a la base `/api/v1`.

| Método | Ruta | Operación |
| --- | --- | --- |
| POST | `/auth/login` | Iniciar sesión local con `username` y `password` |
| POST | `/auth/registro` | Registrar un usuario local con `nombres`, `apellidos`, `identificacion`, `correo`, `username`, `password` y `telefono` opcional |
| GET | `/auth/me` | Consultar el usuario autenticado |
| GET | `/clientes?q=texto` | Buscar clientes por identificación, nombre, apellido o correo (ADMIN, ASESOR) |
| POST | `/clientes` | Registrar un cliente (ADMIN, ASESOR) |
| GET | `/clientes/{id}` | Consultar un cliente por ID |
| GET | `/clientes/cedula/{cedula}/cuentas` | Consultar cuentas y saldo por cédula (ADMIN, ASESOR) |
| GET | `/cuentas/mias` | Listar cuentas y saldos visibles para el usuario |
| POST | `/cuentas` | Abrir una cuenta |
| GET | `/cuentas/{id}` | Consultar una cuenta y su saldo por ID |
| GET | `/cuentas/{id}/movimientos` | Consultar movimientos de una cuenta |
| POST | `/movimientos/transferencia` | Transferir entre cuentas |
| POST | `/movimientos/deposito` | Registrar un depósito |
| POST | `/movimientos/retiro` | Registrar un retiro |
| GET | `/prestamos` | Listar préstamos visibles para el usuario |
| POST | `/prestamos` | Solicitar un préstamo |
| PATCH | `/prestamos/{id}/estado` | Cambiar el estado de un préstamo (ADMIN, ASESOR) |
| GET | `/tarjetas/mias` | Listar tarjetas visibles para el usuario |
| PATCH | `/tarjetas/{id}/estado` | Cambiar el estado de una tarjeta |

## Administración

El frontend ofrece un panel para ADMIN y ASESOR con edición de correo, teléfono y dirección de clientes y evaluación de préstamos. ADMIN también puede ver reportes y vincular el ID de un usuario existente de Keycloak con su perfil local.

| Método | Ruta | Permiso |
| --- | --- | --- |
| PATCH | /api/v1/clientes/{id} | ADMIN, ASESOR |
| GET | /api/v1/reportes | ADMIN |
| GET | /api/v1/usuarios | ADMIN |
| POST | /api/v1/usuarios | ADMIN |

La vinculación recibe keycloak_id, username, rol e id_cliente. El rol CLIENTE requiere un cliente activo. El historial admite pagina (desde 1), limite (1 a 100) y tipo (DEPOSITO, RETIRO o TRANSFERENCIA).

## Respaldo y restauración

En Docker, crea un respaldo desde el cliente MySQL del contenedor. El archivo queda primero dentro del contenedor y después se copia al equipo. Ejecuta desde banco-docker.

~~~powershell
docker compose exec mysql sh -c 'MYSQL_PWD=$MYSQL_ROOT_PASSWORD mysqldump -uroot --single-transaction --routines --triggers --no-tablespaces banco_digital > /tmp/banco-respaldo.sql'
docker cp banco-mysql:/tmp/banco-respaldo.sql ./banco-respaldo.sql
~~~

Para restaurar en un entorno de recuperación, copia el respaldo al contenedor y cárgalo con el cliente MySQL. La restauración reemplaza las tablas incluidas en el respaldo.

~~~powershell
docker cp ./banco-respaldo.sql banco-mysql:/tmp/banco-restaurar.sql
docker compose exec mysql sh -c 'MYSQL_PWD=$MYSQL_ROOT_PASSWORD mysql -uroot banco_digital < /tmp/banco-restaurar.sql'
~~~

En Aiven usa MySQL Workbench conectado mediante TLS. En Server > Data Export selecciona el esquema configurado en DB_NAME, incluye estructura, datos, rutinas y triggers, y exporta a un archivo SQL. Para comprobar la recuperación, importa ese archivo mediante Server > Data Import a una base de prueba y verifica el número de clientes, cuentas y movimientos. Conserva los respaldos fuera del repositorio.
