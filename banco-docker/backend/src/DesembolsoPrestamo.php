<?php

class DesembolsoPrestamo {
    public static function aprobar(PDO $db, int $id, int $evaluador, ?string $observacion = null): int {
        $db->beginTransaction();
        try {
            $consulta = $db->prepare('SELECT id_cliente, monto, estado FROM prestamos WHERE id_prestamo = ? FOR UPDATE');
            $consulta->execute([$id]);
            $prestamo = $consulta->fetch();
            if (!$prestamo || !in_array($prestamo['estado'], ['SOLICITADO', 'EN_REVISION'], true)) {
                throw new DomainException('La solicitud ya fue resuelta o no existe.');
            }

            $consulta = $db->prepare("
                SELECT id_cuenta FROM cuentas
                WHERE id_cliente = ? AND estado = 'ACTIVA' AND moneda = 'USD'
                ORDER BY id_cuenta LIMIT 1 FOR UPDATE
            ");
            $consulta->execute([$prestamo['id_cliente']]);
            $cuenta = $consulta->fetchColumn();
            if ($cuenta === false) {
                throw new DomainException('El cliente necesita una cuenta activa en USD para recibir el préstamo.');
            }

            $stmt = $db->prepare('UPDATE cuentas SET saldo = saldo + ? WHERE id_cuenta = ?');
            $stmt->execute([$prestamo['monto'], $cuenta]);

            $stmt = $db->prepare("
                INSERT INTO movimientos (referencia, tipo, id_cuenta_destino, monto, moneda, descripcion, estado, id_usuario)
                VALUES (UUID(), 'DEPOSITO', ?, ?, 'USD', ?, 'COMPLETADO', ?)
            ");
            $stmt->execute([$cuenta, $prestamo['monto'], "Desembolso préstamo #{$id}", $evaluador]);

            $stmt = $db->prepare("
                UPDATE prestamos SET estado = 'APROBADO', id_evaluador = ?, observacion = ?, resuelto_en = NOW()
                WHERE id_prestamo = ?
            ");
            $stmt->execute([$evaluador, $observacion, $id]);
            $db->commit();
            return (int)$cuenta;
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
}
