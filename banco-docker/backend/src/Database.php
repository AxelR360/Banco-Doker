<?php

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: 'mysql';
            $port = getenv('DB_PORT') ?: '3306';
            $db   = getenv('DB_NAME') ?: 'banco_digital';
            $user = getenv('DB_USER') ?: 'banco_user';
            $pass = getenv('DB_PASS') ?: '12345';

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // SSL para MySQL en la nube (Aiven). Solo se activa si DB_SSL=1,
            // así el docker-compose local sigue funcionando sin SSL.
            if (getenv('DB_SSL') === '1') {
                $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                Response::error('Error al conectar con la base de datos MySQL: ' . $e->getMessage(), 500);
            }
        }
        return self::$instance;
    }
}
