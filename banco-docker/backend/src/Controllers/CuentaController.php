<?php

class CuentaController {
    public static function mias(): void {
        $currentUser = Auth::requireAuth(['CLIENTE', 'ADMIN', 'ASESOR']);
        $db = Database::getConnection();

        $idCliente = $currentUser['id_cliente'];
        if (!$idCliente) {
            // Si es admin/asesor sin cliente asignado, lista todas o vacío
            $stmt = $db->query("
                SELECT c.*, cl.nombres, cl.apellidos 
                FROM cuentas c
                JOIN clientes cl ON c.id_cliente = cl.id_cliente
                ORDER BY c.id_cuenta DESC
            ");
            Response::json($stmt->fetchAll(), 200);
            return;
        }

        $stmt = $db->prepare("
            SELECT * FROM cuentas
            WHERE id_cliente = :id_cliente
            ORDER BY id_cuenta ASC
        ");
        $stmt->execute(['id_cliente' => $idCliente]);
        $cuentas = $stmt->fetchAll();

        Response::json($cuentas, 200);
    }

    public static function show(array $params): void {
        $currentUser = Auth::requireAuth();
        $id = (int)$params['id'];
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT c.*, cl.nombres, cl.apellidos, cl.identificacion
            FROM cuentas c
            JOIN clientes cl ON c.id_cliente = cl.id_cliente
            WHERE c.id_cuenta = :id
        ");
        $stmt->execute(['id' => $id]);
        $cuenta = $stmt->fetch();

        if (!$cuenta) {
            Response::error('Cuenta bancaria no encontrada', 404);
        }

        if ($currentUser['rol'] === 'CLIENTE' && $currentUser['id_cliente'] !== (int)$cuenta['id_cliente']) {
            Response::error('No tienes autorización para consultar esta cuenta', 403);
        }

        Response::json($cuenta, 200);
    }

    public static function create(): void {
        $currentUser = Auth::requireAuth(['CLIENTE', 'ADMIN', 'ASESOR']);
        $body = Router::getJsonBody();

        $idCliente = ($currentUser['rol'] === 'CLIENTE')
            ? (int)($currentUser['id_cliente'] ?? 0)
            : (int)($body['id_cliente'] ?? $currentUser['id_cliente'] ?? 0);

        $tipo = strtoupper(trim($body['tipo'] ?? 'AHORROS'));
        $moneda = strtoupper(trim($body['moneda'] ?? 'USD'));
        $saldoInicial = (float)($body['saldo'] ?? 0.0);

        if ($idCliente <= 0) {
            Response::error('Debe indicar un id_cliente válido', 400);
        }

        if (!in_array($tipo, ['AHORROS', 'CORRIENTE'], true)) {
            Response::error('Tipo de cuenta inválido. Permitidos: AHORROS, CORRIENTE', 400);
        }

        $db = Database::getConnection();

        // Validar que el cliente exista y esté ACTIVO
        $stmt = $db->prepare("SELECT estado FROM clientes WHERE id_cliente = :id");
        $stmt->execute(['id' => $idCliente]);
        $cliente = $stmt->fetch();

        if (!$cliente) {
            Response::error('El cliente especificado no existe', 404);
        }
        if ($cliente['estado'] !== 'ACTIVO') {
            Response::error('No se pueden abrir cuentas para un cliente no activo o bloqueado', 400);
        }

        // Generar número de cuenta único de 10 dígitos (ej: 220000000X)
        $numeroCuenta = '22' . str_pad((string)mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);

        $insert = $db->prepare("
            INSERT INTO cuentas (numero_cuenta, id_cliente, tipo, moneda, saldo, estado, abierta_en)
            VALUES (:numero, :id_cliente, :tipo, :moneda, :saldo, 'ACTIVA', NOW())
        ");
        $insert->execute([
            'numero'     => $numeroCuenta,
            'id_cliente' => $idCliente,
            'tipo'       => $tipo,
            'moneda'     => $moneda,
            'saldo'      => $saldoInicial
        ]);

        $idCuenta = (int)$db->lastInsertId();

        Response::json([
            'id_cuenta'     => $idCuenta,
            'numero_cuenta' => $numeroCuenta,
            'saldo'         => $saldoInicial
        ], 201, 'Cuenta aperturada exitosamente');
    }
}
