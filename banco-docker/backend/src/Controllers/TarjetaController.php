<?php

class TarjetaController {
    public static function mias(): void {
        $currentUser = Auth::requireAuth();
        $db = Database::getConnection();

        $idCliente = $currentUser['id_cliente'];
        if (!$idCliente && in_array($currentUser['rol'], ['ADMIN', 'ASESOR'])) {
            $stmt = $db->query("
                SELECT t.*, c.numero_cuenta, cl.nombres, cl.apellidos 
                FROM tarjetas t
                JOIN cuentas c ON t.id_cuenta = c.id_cuenta
                JOIN clientes cl ON c.id_cliente = cl.id_cliente
            ");
            Response::json($stmt->fetchAll(), 200);
            return;
        }

        $stmt = $db->prepare("
            SELECT t.id_tarjeta, t.id_cuenta, t.ultimos4, t.tipo, t.titular, t.vencimiento, t.estado
            FROM tarjetas t
            JOIN cuentas c ON t.id_cuenta = c.id_cuenta
            WHERE c.id_cliente = :id_cliente
        ");
        $stmt->execute(['id_cliente' => $idCliente]);
        Response::json($stmt->fetchAll(), 200);
    }

    public static function updateEstado(array $params): void {
        $currentUser = Auth::requireAuth();
        $id = (int)$params['id'];
        $body = Router::getJsonBody();

        $nuevoEstado = strtoupper(trim($body['estado'] ?? ''));
        if (!in_array($nuevoEstado, ['ACTIVA', 'BLOQUEADA', 'VENCIDA'], true)) {
            Response::error('Estado de tarjeta inválido. Opciones: ACTIVA, BLOQUEADA', 400);
        }

        $db = Database::getConnection();

        // Validar titularidad
        $stmt = $db->prepare("
            SELECT t.*, c.id_cliente 
            FROM tarjetas t
            JOIN cuentas c ON t.id_cuenta = c.id_cuenta
            WHERE t.id_tarjeta = :id
        ");
        $stmt->execute(['id' => $id]);
        $tarjeta = $stmt->fetch();

        if (!$tarjeta) {
            Response::error('Tarjeta no encontrada', 404);
        }

        if ($currentUser['rol'] === 'CLIENTE' && (int)$tarjeta['id_cliente'] !== $currentUser['id_cliente']) {
            Response::error('No tienes autorización para gestionar esta tarjeta', 403);
        }

        $up = $db->prepare("UPDATE tarjetas SET estado = :estado WHERE id_tarjeta = :id");
        $up->execute(['estado' => $nuevoEstado, 'id' => $id]);

        Response::json([
            'id_tarjeta' => $id,
            'estado'     => $nuevoEstado
        ], 200, "Estado de tarjeta actualizado a {$nuevoEstado}");
    }
}
