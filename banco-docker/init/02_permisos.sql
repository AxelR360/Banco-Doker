-- Permisos para el usuario de la aplicación (se ejecuta después de crear banco_digital)
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON banco_digital.* TO 'banco_user'@'%';
FLUSH PRIVILEGES;
