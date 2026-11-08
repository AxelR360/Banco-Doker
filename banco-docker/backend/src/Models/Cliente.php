<?php

namespace Models;

use Core\Model;

class Cliente extends Model {
    public function findByIdentificacionOrEmail(string $identificacion, string $correo): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM clientes 
            WHERE identificacion = :id OR correo = :correo
        ");
        $stmt->execute(['id' => $identificacion, 'correo' => $correo]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function findById(int $idCliente): ?array {
        $stmt = $this->db->prepare("SELECT * FROM clientes WHERE id_cliente = :id");
        $stmt->execute(['id' => $idCliente]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function create(string $identificacion, string $nombres, string $apellidos, string $correo, ?string $telefono): int {
        $stmt = $this->db->prepare("
            INSERT INTO clientes (identificacion, nombres, apellidos, correo, telefono, estado, creado_en)
            VALUES (:id, :nom, :ape, :cor, :tel, 'ACTIVO', NOW())
        ");
        $stmt->execute([
            'id'  => $identificacion,
            'nom' => $nombres,
            'ape' => $apellidos,
            'cor' => $correo,
            'tel' => $telefono
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function all(): array {
        $stmt = $this->db->query("SELECT * FROM clientes ORDER BY id_cliente DESC");
        return $stmt->fetchAll();
    }
}
