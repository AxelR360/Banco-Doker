<?php

class PrestamoController {
    public static function index(): void {
        $currentUser = Auth::requireAuth();
        $db = Database::getConnection();

        if ($currentUser['rol'] === 'CLIENTE') {
            $stmt = $db->prepare("
                SELECT p.*, c.nombres, c.apellidos
                FROM prestamos p
                JOIN clientes c ON p.id_cliente = c.id_cliente
                WHERE p.id_cliente = :id
                ORDER BY p.id_prestamo DESC
            ");
            $stmt->execute(['id' => $currentUser['id_cliente']]);
        } else {
            $stmt = $db->query("
                SELECT p.*, c.nombres, c.apellidos, c.identificacion
                FROM prestamos p
                JOIN clientes c ON p.id_cliente = c.id_cliente
                ORDER BY p.id_prestamo DESC
            ");
        }

        Response::json($stmt->fetchAll(), 200);
    }

    public static function create(): void {
        $currentUser = Auth::requireAuth(['CLIENTE', 'ADMIN', 'ASESOR']);
        $body = Router::getJsonBody();

        $idCliente = $currentUser['rol'] === 'CLIENTE' ? $currentUser['id_cliente'] : (int)($body['id_cliente'] ?? 0);
        $monto = (float)($body['monto'] ?? 0);
        $plazo = (int)($body['plazo_meses'] ?? 12);

        if ($idCliente <= 0) {
            Response::error('Identificador de cliente no válido', 400);
        }
        if ($monto <= 0) {
            Response::error('El monto debe ser superior a 0', 400);
        }
        if ($plazo <= 0 || $plazo > 120) {
            Response::error('El plazo en meses debe estar entre 1 y 120 meses', 400);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO prestamos (id_cliente, monto, plazo_meses, tasa_anual, estado, solicitado_en)
            VALUES (:id_cliente, :monto, :plazo, 14.50, 'SOLICITADO', NOW())
        ");
        $stmt->execute([
            'id_cliente' => $idCliente,
            'monto'      => $monto,
            'plazo'      => $plazo
        ]);

        $idPrestamo = (int)$db->lastInsertId();
        Response::json(['id_prestamo' => $idPrestamo, 'estado' => 'SOLICITADO'], 201, 'Solicitud de préstamo enviada para revisión');
    }

    public static function updateEstado(array $params): void {
        $currentUser = Auth::requireAuth(['ADMIN', 'ASESOR']);
        $id = (int)$params['id'];
        $body = Router::getJsonBody();

        $nuevoEstado = strtoupper(trim($body['estado'] ?? ''));
        $observacion = trim($body['observacion'] ?? '');

        $estadosValidos = ['SOLICITADO', 'EN_REVISION', 'APROBADO', 'RECHAZADO', 'CANCELADO'];
        if (!in_array($nuevoEstado, $estadosValidos, true)) {
            Response::error('Estado no válido. Opciones: ' . implode(', ', $estadosValidos), 400);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE prestamos
            SET estado = :estado,
                id_evaluador = :evaluador,
                observacion = :obs,
                resuelto_en = NOW()
            WHERE id_prestamo = :id
        ");
        $stmt->execute([
            'estado'    => $nuevoEstado,
            'evaluador' => $currentUser['id_usuario'],
            'obs'       => $observacion ?: null,
            'id'        => $id
        ]);

        Response::json(['id_prestamo' => $id, 'estado' => $nuevoEstado], 200, 'Estado del préstamo actualizado');
    }
}
