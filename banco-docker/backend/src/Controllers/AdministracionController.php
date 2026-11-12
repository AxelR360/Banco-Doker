<?php
class AdministracionController {
    public static function usuarios(): void {
        Auth::requireAuth(['ADMIN']);
        Response::json(Database::getConnection()->query(
            'SELECT id_usuario, username, keycloak_id, rol, id_cliente, estado FROM usuarios ORDER BY username'
        )->fetchAll());
    }

    public static function vincularUsuario(): void {
        Auth::requireAuth(['ADMIN']);
        $body = Router::getJsonBody();
        $sub = trim($body['keycloak_id'] ?? '');
        $username = trim($body['username'] ?? '');
        $rol = $body['rol'] ?? '';
        $cliente = (int)($body['id_cliente'] ?? 0);
        if (!preg_match('/^[0-9a-f-]{36}$/iD', $sub) || $username === '' || strlen($username) > 60
            || !in_array($rol, ['ADMIN', 'ASESOR', 'CLIENTE'], true) || ($rol === 'CLIENTE' && $cliente <= 0)) {
            Response::error('Indica el ID de Keycloak, usuario, rol y cliente asociado cuando corresponda', 400);
        }
        $db = Database::getConnection();
        if ($cliente > 0) {
            $stmt = $db->prepare("SELECT id_cliente FROM clientes WHERE id_cliente = ? AND estado = 'ACTIVO'");
            $stmt->execute([$cliente]);
            if (!$stmt->fetch()) Response::error('Cliente activo no encontrado', 404);
        }
        try {
            $stmt = $db->prepare("INSERT INTO usuarios (keycloak_id, username, rol, id_cliente, estado) VALUES (?, ?, ?, ?, 'ACTIVO')");
            $stmt->execute([$sub, $username, $rol, $cliente ?: null]);
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? 0) === 1062) Response::error('El usuario o cliente ya está vinculado', 409);
            throw $error;
        }
        Response::json(['id_usuario' => (int)$db->lastInsertId()], 201, 'Usuario de Keycloak vinculado');
    }
    public static function reportes(): void {
        Auth::requireAuth(['ADMIN']);
        $db = Database::getConnection();
        Response::json([
            'clientes' => $db->query('SELECT estado, COUNT(*) AS total FROM clientes GROUP BY estado')->fetchAll(),
            'cuentas' => $db->query('SELECT moneda, COUNT(*) AS total, SUM(saldo) AS saldo FROM cuentas GROUP BY moneda')->fetchAll(),
            'movimientos' => $db->query('SELECT tipo, moneda, COUNT(*) AS total, SUM(monto) AS monto FROM movimientos GROUP BY tipo, moneda')->fetchAll()
        ]);
    }

    public static function actualizarCliente(array $params): void {
        Auth::requireAuth(['ADMIN', 'ASESOR']);
        $body = Router::getJsonBody();
        $correo = trim($body['correo'] ?? '');
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 120) {
            Response::error('Ingresa un correo válido', 400);
        }
        $telefono = trim($body['telefono'] ?? '');
        $direccion = trim($body['direccion'] ?? '');
        if (strlen($telefono) > 15 || strlen($direccion) > 150) {
            Response::error('Teléfono o dirección demasiado largos', 400);
        }
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id_cliente FROM clientes WHERE id_cliente = ?');
        $stmt->execute([(int)$params['id']]);
        if (!$stmt->fetch()) Response::error('Cliente no encontrado', 404);
        try {
            $stmt = $db->prepare('UPDATE clientes SET correo = ?, telefono = ?, direccion = ? WHERE id_cliente = ?');
            $stmt->execute([$correo, $telefono, $direccion, (int)$params['id']]);
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? 0) === 1062) Response::error('El correo ya está registrado', 409);
            throw $error;
        }
        Response::json(['id_cliente' => (int)$params['id']], 200, 'Cliente actualizado');
    }
}
