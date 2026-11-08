<?php

namespace Models;

use Core\Model;
use PDOException;

class Movimiento extends Model {
    public function getByCuenta(int $idCuenta, int $limit = 20): array {
        $stmt = $this->db->prepare("
            SELECT m.*, co.numero_cuenta as cta_origen, cd.numero_cuenta as cta_destino
            FROM movimientos m
            LEFT JOIN cuentas co ON m.id_cuenta_origen = co.id_cuenta
            LEFT JOIN cuentas cd ON m.id_cuenta_destino = cd.id_cuenta
            WHERE m.id_cuenta_origen = :id1 OR m.id_cuenta_destino = :id2
            ORDER BY m.fecha DESC LIMIT :lim
        ");
        $stmt->bindValue(':id1', $idCuenta, \PDO::PARAM_INT);
        $stmt->bindValue(':id2', $idCuenta, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function transferir(int $origenId, int $destinoId, float $monto, string $referencia, int $usuarioId, string $desc): string {
        $sp = $this->db->prepare("CALL sp_transferir(:orig, :dest, :monto, :ref, :usr, :desc)");
        $sp->execute([
            'orig'  => $origenId,
            'dest'  => $destinoId,
            'monto' => $monto,
            'ref'   => $referencia,
            'usr'   => $usuarioId,
            'desc'  => $desc
        ]);
        $sp->closeCursor();
        return $referencia;
    }
}
