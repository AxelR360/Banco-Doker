# Sistema de Gestión Bancaria Digital (Grupo #4 - UTLVTE)

Proyecto académico desarrollado según la norma **IEEE Std 830-1998**.

## 🛠️ Stack Tecnológico Completo
* **Backend**: PHP 8.3 (API REST estructurada en capas, PDO MySQL y validación JWT).
* **Frontend**: JavaScript / React + Lucide Icons (construido con Vite).
* **Servidor Web & Proxy Inverso**: Nginx Alpine en el puerto `80`.
* **Identidad & SSO / OIDC**: Keycloak 24.0 en el puerto `8080`.
* **Base de Datos**: MySQL 8.4 (InnoDB con procedimientos almacenados y triggers) en puerto `3307`.
* **Administrador BD**: phpMyAdmin en puerto `8081`.

---

## 🚀 Despliegue en Docker Compose

1. Abre **Docker Desktop**.
2. En esta carpeta ejecuta:
   ```bash
   docker compose up -d --build
   ```

### 🔗 Puntos de acceso:
* 🌐 **Aplicación Web (Nginx)**: [http://localhost](http://localhost)
* 🔐 **Keycloak SSO (IAM/OIDC)**: [http://localhost:8080](http://localhost:8080)
  * Consola de administración: usuario `admin` / clave `admin`
  * Realm: `banco-digital`
* 🗄️ **phpMyAdmin**: [http://localhost:8081](http://localhost:8081)
* 🐬 **MySQL**: `localhost:3307` (usuario `banco_user`, clave `12345`, db `banco_digital`)

---

## 👥 Cuentas y Roles Preconfigurados (Keycloak & MySQL)

| Usuario | Rol | Contraseña | Asociación en Base de Datos |
|---|---|---|---|
| `admin` | **ADMIN** | `12345` | Administrador general de la plataforma |
| `asesor1` | **ASESOR** | `12345` | Asesor bancario (gestión de clientes y préstamos) |
| `axel.rosero` | **CLIENTE** | `12345` | Cliente 1: Axel Rosero (Cuenta 2200000001) |
| `melany.zambrano` | **CLIENTE** | `12345` | Cliente 2: Melany Zambrano (Cuenta 2200000002) |
