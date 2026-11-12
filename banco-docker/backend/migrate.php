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
    fwrite(STDOUT, "Esquema de credenciales preparado.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "No se pudo preparar el esquema de credenciales. Comprueba la conexión y el permiso ALTER de la base configurada.\n");
    exit(1);
}
