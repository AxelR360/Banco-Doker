<?php

require_once __DIR__ . '/src/Database.php';

try {
    $db = Database::getConnection();
    $column = $db->query("SHOW COLUMNS FROM usuarios LIKE 'password_hash'");
    if (!$column->fetch()) {
        try {
            $db->exec('ALTER TABLE usuarios ADD COLUMN password_hash VARCHAR(255) NULL');
            fwrite(STDOUT, "Migración de credenciales aplicada.\n");
        } catch (PDOException $error) {
            // Otro contenedor puede aplicar la misma migración durante el despliegue.
            if (($error->errorInfo[1] ?? null) !== 1060) {
                throw $error;
            }
        }
    }
    $adminPassword = getenv('BANCO_ADMIN_PASSWORD');
    if (is_string($adminPassword) && $adminPassword !== '') {
        $db->beginTransaction();
        $admin = $db->query(
            "SELECT id_usuario, password_hash, estado FROM usuarios
             WHERE username = 'admin' AND rol = 'ADMIN' FOR UPDATE"
        )->fetch();
        if (!$admin) {
            throw new RuntimeException('No existe el administrador del banco.');
        }
        if (!password_verify($adminPassword, $admin['password_hash'] ?? '') || $admin['estado'] !== 'ACTIVO') {
            $update = $db->prepare("UPDATE usuarios SET password_hash = ?, estado = 'ACTIVO' WHERE id_usuario = ?");
            $update->execute([password_hash($adminPassword, PASSWORD_DEFAULT), $admin['id_usuario']]);
            fwrite(STDOUT, "Administrador sincronizado con la configuración de Render.\n");
        }
        $db->commit();
        fwrite(STDOUT, "Configuración de acceso admin verificada.\n");
    } else {
        fwrite(STDOUT, "Sincronización admin omitida: BANCO_ADMIN_PASSWORD no está definida o está vacía.\n");
    }
    fwrite(STDOUT, "Esquema de credenciales preparado.\n");
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "No se pudo preparar el acceso local. Comprueba la conexión, los permisos CREATE/ALTER y que exista el usuario admin con rol ADMIN.\n");
    exit(1);
}
