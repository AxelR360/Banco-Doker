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
    if (getenv('RENDER') === 'true' && is_string($adminPassword) && $adminPassword !== '') {
        $db->exec("CREATE TABLE IF NOT EXISTS banco_migraciones (
            nombre VARCHAR(100) PRIMARY KEY,
            aplicada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        $db->beginTransaction();
        $migration = $db->prepare('INSERT IGNORE INTO banco_migraciones (nombre) VALUES (?)');
        $migration->execute(['20261112_habilitar_admin_local']);
        if ($migration->rowCount() === 1) {
            $admin = $db->query("SELECT id_usuario FROM usuarios WHERE username = 'admin' AND rol = 'ADMIN' FOR UPDATE")->fetchColumn();
            if ($admin === false) {
                throw new RuntimeException('No existe el administrador del banco.');
            }
            $update = $db->prepare("UPDATE usuarios SET password_hash = ?, estado = 'ACTIVO' WHERE id_usuario = ?");
            $update->execute([password_hash($adminPassword, PASSWORD_DEFAULT), $admin]);
            fwrite(STDOUT, "Acceso local del administrador habilitado.\n");
        }
        $db->commit();
    }
    fwrite(STDOUT, "Esquema de credenciales preparado.\n");
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "No se pudo preparar el acceso local. Comprueba la conexión, los permisos CREATE/ALTER y que exista el usuario admin con rol ADMIN.\n");
    exit(1);
}
