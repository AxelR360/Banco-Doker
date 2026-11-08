<?php

class MovimientoController {
    public static function transferencia(): void {
        $currentUser = Auth::requireAuth(['CLIENTE', 'ADMIN', 'ASESOR']);
        $body = Router::getJsonBody();

        $origenId = (int)($body['id_cuenta_origen'] ?? 0);
        $destinoId = (int)($body['id_cuenta_destino'] ?? 0);
        $monto = (float)($body['monto'] ?? 0);
        $descripcion = trim($body['descripcion'] ?? 'Transferencia bancaria');

        // Validaciones de negocio (RN-02, RN-05)
        if ($monto <= 0) {
            Response::error('El monto debe ser mayor que cero (RN-02)', 400);
        }
        if ($origenId <= 0 || $destinoId <= 0) {
            Response::error('Debe especificar cuenta origen y cuenta destino válidas', 400);
        }
        if ($origenId === $destinoId) {
            Response::error('No se puede transferir a la misma cuenta (RN-05)', 400);
        }

        $db = Database::getConnection();

        // Validar que el cliente autenticado sea dueño de la cuenta origen (RN-08, RF-06)
        if ($currentUser['rol'] === 'CLIENTE') {
            $stmtAuth = $db->prepare("SELECT id_cliente FROM cuentas WHERE id_cuenta = :id");
            $stmtAuth->execute(['id' => $origenId]);
            $cuentaOrigenAuth = $stmtAuth->fetch();
            if (!$cuentaOrigenAuth || (int)$cuentaOrigenAuth['id_cliente'] !== $currentUser['id_cliente']) {
                Response::error('No tienes autorización para realizar transferencias desde esta cuenta origen', 403);
            }
        }

        // Generar UUID único para la referencia (RN-07)
        $referencia = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        // Usar el Stored Procedure `sp_transferir` definido en la base de datos
        try {
            $sp = $db->prepare("CALL sp_transferir(:origen, :destino, :monto, :referencia, :usuario, :desc)");
            $sp->execute([
                'origen'      => $origenId,
                'destino'     => $destinoId,
                'monto'       => $monto,
                'referencia'  => $referencia,
                'usuario'     => $currentUser['id_usuario'],
                'desc'        => $descripcion
            ]);
            $sp->closeCursor();

            // Consultar nuevo saldo de la cuenta origen
            $stmtSaldo = $db->prepare("SELECT saldo FROM cuentas WHERE id_cuenta = :id");
            $stmtSaldo->execute(['id' => $origenId]);
            $nuevoSaldo = (float)$stmtSaldo->fetchColumn();

            Response::json([
                'referencia'    => $referencia,
                'nuevo_saldo'   => $nuevoSaldo,
                'monto'         => $monto,
                'fecha'         => date('Y-m-d H:i:s'),
                'comprobante'   => 'TRANS-' . strtoupper(substr($referencia, 0, 8))
            ], 200, 'Transferencia realizada con éxito');
        } catch (PDOException $e) {
            $errMsg = $e->getMessage();
            // Errores lanzados por SIGNAL SQLSTATE 45000 en sp_transferir
            if (str_contains($errMsg, 'Saldo insuficiente')) {
                Response::error('Saldo insuficiente para completar la transferencia (RN-03)', 400);
            }
            if (str_contains($errMsg, 'Cuenta bloqueada o cerrada')) {
                Response::error('Una de las cuentas está bloqueada o cerrada (RN-01)', 400);
            }
            if (str_contains($errMsg, 'Monedas distintas')) {
                Response::error('Monedas distintas: no se permite conversión automática (RN-06)', 400);
            }
            Response::error('Error en la transferencia: ' . $errMsg, 400);
        }
    }

    public static function deposito(): void {
        $currentUser = Auth::requireAuth(['ADMIN', 'ASESOR']);
        $body = Router::getJsonBody();

        $idCuenta = (int)($body['id_cuenta'] ?? 0);
        $monto = (float)($body['monto'] ?? 0);
        $descripcion = trim($body['descripcion'] ?? 'Depósito de fondos');

        if ($idCuenta <= 0 || $monto <= 0) {
            Response::error('Datos inválidos. El monto debe ser mayor a 0', 400);
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT estado, moneda FROM cuentas WHERE id_cuenta = :id FOR UPDATE");
            $stmt->execute(['id' => $idCuenta]);
            $cuenta = $stmt->fetch();

            if (!$cuenta) {
                $db->rollBack();
                Response::error('Cuenta no encontrada', 404);
            }
            if ($cuenta['estado'] !== 'ACTIVA') {
                $db->rollBack();
                Response::error('La cuenta no está activa para recibir depósitos', 400);
            }

            $update = $db->prepare("UPDATE cuentas SET saldo = saldo + :monto WHERE id_cuenta = :id");
            $update->execute(['monto' => $monto, 'id' => $idCuenta]);

            $ref = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

            $insert = $db->prepare("
                INSERT INTO movimientos (referencia, tipo, id_cuenta_destino, monto, moneda, descripcion, estado, id_usuario)
                VALUES (:ref, 'DEPOSITO', :id_cuenta, :monto, :moneda, :desc, 'COMPLETADO', :id_user)
            ");
            $insert->execute([
                'ref'      => $ref,
                'id_cuenta'=> $idCuenta,
                'monto'    => $monto,
                'moneda'   => $cuenta['moneda'],
                'desc'     => $descripcion,
                'id_user'  => $currentUser['id_usuario']
            ]);

            $db->commit();
            Response::json(['referencia' => $ref, 'monto' => $monto], 200, 'Depósito acreditado correctamente');
        } catch (Exception $e) {
            $db->rollBack();
            Response::error('Error procesando el depósito: ' . $e->getMessage(), 500);
        }
    }

    public static function retiro(): void {
        $currentUser = Auth::requireAuth();
        $body = Router::getJsonBody();

        $idCuenta = (int)($body['id_cuenta'] ?? 0);
        $monto = (float)($body['monto'] ?? 0);
        $descripcion = trim($body['descripcion'] ?? 'Retiro simulado');

        if ($idCuenta <= 0 || $monto <= 0) {
            Response::error('Datos inválidos. El monto debe ser mayor a 0', 400);
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT id_cliente, estado, saldo, moneda FROM cuentas WHERE id_cuenta = :id FOR UPDATE");
            $stmt->execute(['id' => $idCuenta]);
            $cuenta = $stmt->fetch();

            if (!$cuenta) {
                $db->rollBack();
                Response::error('Cuenta no encontrada', 404);
            }
            if ($currentUser['rol'] === 'CLIENTE' && (int)$cuenta['id_cliente'] !== $currentUser['id_cliente']) {
                $db->rollBack();
                Response::error('No tienes permisos sobre esta cuenta', 403);
            }
            if ($cuenta['estado'] !== 'ACTIVA') {
                $db->rollBack();
                Response::error('La cuenta está bloqueada o cerrada', 400);
            }
            if ((float)$cuenta['saldo'] < $monto) {
                $db->rollBack();
                Response::error('Saldo insuficiente para realizar el retiro', 400);
            }

            $update = $db->prepare("UPDATE cuentas SET saldo = saldo - :monto WHERE id_cuenta = :id");
            $update->execute(['monto' => $monto, 'id' => $idCuenta]);

            $ref = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

            $insert = $db->prepare("
                INSERT INTO movimientos (referencia, tipo, id_cuenta_origen, monto, moneda, descripcion, estado, id_usuario)
                VALUES (:ref, 'RETIRO', :id_cuenta, :monto, :moneda, :desc, 'COMPLETADO', :id_user)
            ");
            $insert->execute([
                'ref'      => $ref,
                'id_cuenta'=> $idCuenta,
                'monto'    => $monto,
                'moneda'   => $cuenta['moneda'],
                'desc'     => $descripcion,
                'id_user'  => $currentUser['id_usuario']
            ]);

            $db->commit();
            Response::json(['referencia' => $ref, 'monto' => $monto], 200, 'Retiro efectuado con éxito');
        } catch (Exception $e) {
            $db->rollBack();
            Response::error('Error procesando el retiro: ' . $e->getMessage(), 500);
        }
    }

    public static function porCuenta(array $params): void {
        $currentUser = Auth::requireAuth();
        $idCuenta = (int)$params['id'];
        $db = Database::getConnection();

        // Validar titularidad
        $stmtC = $db->prepare("SELECT id_cliente FROM cuentas WHERE id_cuenta = :id");
        $stmtC->execute(['id' => $idCuenta]);
        $cuenta = $stmtC->fetch();
        if (!$cuenta) {
            Response::error('Cuenta no encontrada', 404);
        }
        if ($currentUser['rol'] === 'CLIENTE' && (int)$cuenta['id_cliente'] !== $currentUser['id_cliente']) {
            Response::error('No tienes autorización para ver los movimientos de esta cuenta', 403);
        }

        $stmt = $db->prepare("
            SELECT m.*, 
                   co.numero_cuenta AS cuenta_origen_num,
                   cd.numero_cuenta AS cuenta_destino_num
            FROM movimientos m
            LEFT JOIN cuentas co ON m.id_cuenta_origen = co.id_cuenta
            LEFT JOIN cuentas cd ON m.id_cuenta_destino = cd.id_cuenta
            WHERE m.id_cuenta_origen = :id1 OR m.id_cuenta_destino = :id2
            ORDER BY m.fecha DESC
            LIMIT 50
        ");
        $stmt->execute(['id1' => $idCuenta, 'id2' => $idCuenta]);
        $movimientos = $stmt->fetchAll();

        Response::json($movimientos, 200);
    }
}
