<?php

namespace Models;

use Core\Model;

class Cuenta extends Model {
    public function getByCliente(int $idCliente): array {
        $stmt = $this->db->prepare("
            SELECT * FROM cuentas 
            WHERE id_cliente = :cli 
            ORDER BY id_cuenta ASC
        ");
        $stmt->execute(['cli' => $idCliente]);
        return $stmt->fetchAll();
    }

    public function findById(int $idCuenta): ?array {
        $stmt = $this->db->prepare("SELECT * FROM cuentas WHERE id_cuenta = :id");
        $stmt->execute(['id' => $idCuenta]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function create(string $numeroCuenta, int $idCliente, string $tipo, float $saldoInicial, string $moneda = 'USD'): int {
        $stmt = $this->db->prepare("
            INSERT INTO cuentas (numero_cuenta, id_cliente, tipo, moneda, saldo, estado, abierta_en)
            VALUES (:num, :cli, :tipo, :mon, :saldo, 'ACTIVA', NOW())
        ");
        $stmt->execute([
            'num'   => $numeroCuenta,
            'cli'   => $idCliente,
            'tipo'  => $tipo,
            'mon'   => $moneda,
            'saldo' => $saldoInicial
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function all(): array {
        $stmt = $this->db->query("
            SELECT c.*, cl.nombres, cl.apellidos, cl.identificacion 
            FROM cuentas c 
            JOIN clientes cl ON c.id_cliente = cl.id_cliente 
            ORDER BY c.id_cuenta DESC
        ");
        return $stmt->fetchAll();
    }
}
