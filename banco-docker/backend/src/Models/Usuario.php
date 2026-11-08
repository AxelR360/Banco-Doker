<?php

namespace Models;

use Core\Model;
use PDO;

class Usuario extends Model {
    /**
     * Busca usuario por nombre de usuario O por número de cédula (identificación)
     */
    public function findByLoginOrCedula(string $input): ?array {
        $stmt = $this->db->prepare("
            SELECT u.id_usuario, u.keycloak_id, u.username, u.rol, u.id_cliente, u.estado,
                   c.identificacion, c.nombres, c.apellidos, c.correo, c.telefono
            FROM usuarios u
            LEFT JOIN clientes c ON u.id_cliente = c.id_cliente
            WHERE u.username = :in1 OR c.identificacion = :in2
        ");
        $stmt->execute(['in1' => $input, 'in2' => $input]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function findByUsername(string $username): ?array {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE username = :u");
        $stmt->execute(['u' => $username]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function create(string $keycloakId, string $username, string $rol, ?int $idCliente): int {
        $stmt = $this->db->prepare("
            INSERT INTO usuarios (keycloak_id, username, rol, id_cliente, estado, creado_en)
            VALUES (:kid, :u, :r, :cli, 'ACTIVO', NOW())
        ");
        $stmt->execute([
            'kid' => $keycloakId,
            'u'   => $username,
            'r'   => $rol,
            'cli' => $idCliente
        ]);
        return (int)$this->db->lastInsertId();
    }
}
