<?php

namespace Models;

use Core\Model;

class Prestamo extends Model {
    public function getByCliente(int $idCliente): array {
        $stmt = $this->db->prepare("SELECT * FROM prestamos WHERE id_cliente = :cli ORDER BY id_prestamo DESC");
        $stmt->execute(['cli' => $idCliente]);
        return $stmt->fetchAll();
    }

    public function create(int $idCliente, float $monto, int $plazoMeses, float $tasaAnual = 14.50): int {
        $stmt = $this->db->prepare("
            INSERT INTO prestamos (id_cliente, monto, plazo_meses, tasa_anual, estado, solicitado_en)
            VALUES (:cli, :monto, :plazo, :tasa, 'SOLICITADO', NOW())
        ");
        $stmt->execute([
            'cli'   => $idCliente,
            'monto' => $monto,
            'plazo' => $plazoMeses,
            'tasa'  => $tasaAnual
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function all(): array {
        $stmt = $this->db->query("
            SELECT p.*, cl.nombres, cl.apellidos, cl.identificacion 
            FROM prestamos p 
            JOIN clientes cl ON p.id_cliente = cl.id_cliente 
            ORDER BY p.id_prestamo DESC
        ");
        return $stmt->fetchAll();
    }
}
