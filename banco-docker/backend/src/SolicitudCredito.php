<?php

class SolicitudCredito {
    public static function emitir(PDO $db, int $cliente): int {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT CONCAT(nombres, ' ', apellidos) FROM clientes WHERE id_cliente = ? AND estado = 'ACTIVO' FOR UPDATE");
            $stmt->execute([$cliente]);
            $titular = $stmt->fetchColumn();
            if ($titular === false) {
                throw new DomainException('La solicitud requiere un cliente activo.');
            }

            $stmt = $db->prepare("SELECT id_cuenta FROM cuentas WHERE id_cliente = ? AND estado = 'ACTIVA' ORDER BY id_cuenta LIMIT 1 FOR UPDATE");
            $stmt->execute([$cliente]);
            $cuenta = $stmt->fetchColumn();
            if ($cuenta === false) {
                throw new DomainException('Necesitas una cuenta activa para solicitar la tarjeta.');
            }

            $stmt = $db->prepare("SELECT 1 FROM tarjetas t JOIN cuentas c ON c.id_cuenta = t.id_cuenta WHERE c.id_cliente = ? AND t.tipo = 'CREDITO' AND t.estado IN ('ACTIVA', 'BLOQUEADA') LIMIT 1");
            $stmt->execute([$cliente]);
            if ($stmt->fetchColumn()) {
                throw new DomainException('Ya tienes una tarjeta de crédito asignada.');
            }

            $stmt = $db->prepare("
                SELECT COUNT(*) FROM movimientos m
                WHERE m.estado = 'COMPLETADO' AND EXISTS (
                    SELECT 1 FROM cuentas c WHERE c.id_cliente = ?
                    AND c.id_cuenta IN (m.id_cuenta_origen, m.id_cuenta_destino)
                )
            ");
            $stmt->execute([$cliente]);
            if ((int)$stmt->fetchColumn() < 3) {
                throw new DomainException('Evaluación simulada no aprobada. Necesitas al menos tres movimientos completados.');
            }

            $stmt = $db->prepare("
                INSERT INTO tarjetas (id_cuenta, token, ultimos4, tipo, titular, vencimiento, estado)
                VALUES (?, UUID(), ?, 'CREDITO', LEFT(?, 80), DATE_ADD(CURDATE(), INTERVAL 4 YEAR), 'ACTIVA')
            ");
            $stmt->execute([$cuenta, sprintf('%04d', random_int(0, 9999)), $titular]);
            $id = (int)$db->lastInsertId();
            $db->commit();
            return $id;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }
}
