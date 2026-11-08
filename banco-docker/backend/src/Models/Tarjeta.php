<?php

namespace Models;

use Core\Model;

class Tarjeta extends Model {
    public function getByCliente(int $idCliente): array {
        $stmt = $this->db->prepare("
            SELECT t.* FROM tarjetas t 
            JOIN cuentas c ON t.id_cuenta = c.id_cuenta 
            WHERE c.id_cliente = :cli
        ");
        $stmt->execute(['cli' => $idCliente]);
        return $stmt->fetchAll();
    }

    public function create(int $idCuenta, string $token, string $ultimos4, string $titular, string $tipo = 'DEBITO'): int {
        $stmt = $this->db->prepare("
            INSERT INTO tarjetas (id_cuenta, token, ultimos4, tipo, titular, vencimiento, estado, creada_en)
            VALUES (:cta, :tok, :u4, :tipo, :tit, DATE_ADD(CURDATE(), INTERVAL 4 YEAR), 'ACTIVA', NOW())
        ");
        $stmt->execute([
            'cta'  => $idCuenta,
            'tok'  => $token,
            'u4'   => $ultimos4,
            'tipo' => $tipo,
            'tit'  => $titular
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function toggleEstado(int $idTarjeta, string $estadoActual): string {
        $nuevo = $estadoActual === 'ACTIVA' ? 'BLOQUEADA' : 'ACTIVA';
        $stmt = $this->db->prepare("UPDATE tarjetas SET estado = :est WHERE id_tarjeta = :id");
        $stmt->execute(['est' => $nuevo, 'id' => $idTarjeta]);
        return $nuevo;
    }
}
