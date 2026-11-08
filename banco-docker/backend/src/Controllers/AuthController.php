<?php

class AuthController {
    public static function login(): void {
        $body = Router::getJsonBody();
        $username = trim($body['username'] ?? '');
        $password = trim($body['password'] ?? '');

        if (empty($username)) {
            Response::error('Debe ingresar un nombre de usuario', 400);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT u.id_usuario, u.keycloak_id, u.username, u.rol, u.id_cliente, u.estado,
                   c.identificacion, c.nombres, c.apellidos, c.correo
            FROM usuarios u
            LEFT JOIN clientes c ON u.id_cliente = c.id_cliente
            WHERE u.username = :username
        ");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error('Credenciales incorrectas o usuario no registrado', 401);
        }

        if ($user['estado'] !== 'ACTIVO') {
            Response::error('El usuario se encuentra inactivo o bloqueado', 403);
        }

        // Validación de contraseña flexible para entorno académico (acepta "12345" o el mismo username)
        if (!empty($password) && $password !== '12345' && $password !== $username && $password !== 'admin' && $password !== 'password') {
            Response::error('Contraseña incorrecta (para pruebas usa 12345 o tu usuario)', 401);
        }

        $fullName = trim(($user['nombres'] ?? '') . ' ' . ($user['apellidos'] ?? ''));
        if (empty($fullName)) {
            $fullName = ucfirst($user['username']);
        }

        $tokenPayload = [
            'id_usuario' => (int)$user['id_usuario'],
            'username'   => $user['username'],
            'rol'        => $user['rol'],
            'id_cliente' => $user['id_cliente'] ? (int)$user['id_cliente'] : null,
            'nombre'     => $fullName,
            'correo'     => $user['correo'] ?? ''
        ];

        $token = Auth::generateToken($tokenPayload);

        Response::json([
            'token' => $token,
            'user'  => $tokenPayload
        ], 200, 'Inicio de sesión exitoso');
    }

    public static function registro(): void {
        $body = Router::getJsonBody();
        $nombres = trim($body['nombres'] ?? '');
        $apellidos = trim($body['apellidos'] ?? '');
        $identificacion = trim($body['identificacion'] ?? '');
        $correo = trim($body['correo'] ?? '');
        $telefono = trim($body['telefono'] ?? '');
        $username = trim($body['username'] ?? '');
        $password = trim($body['password'] ?? '12345');

        if (empty($nombres) || empty($apellidos) || empty($identificacion) || empty($correo) || empty($username)) {
            Response::error('Por favor complete todos los campos obligatorios', 400);
        }

        $db = Database::getConnection();

        // Validar unicidad (BD-04)
        $check = $db->prepare("SELECT id_cliente FROM clientes WHERE identificacion = :id OR correo = :correo");
        $check->execute(['id' => $identificacion, 'correo' => $correo]);
        if ($check->fetch()) {
            Response::error('Ya existe un cliente con esa identificación o correo electrónico', 409);
        }

        $checkUser = $db->prepare("SELECT id_usuario FROM usuarios WHERE username = :u");
        $checkUser->execute(['u' => $username]);
        if ($checkUser->fetch()) {
            Response::error('El nombre de usuario ya está registrado, elija otro', 409);
        }

        $db->beginTransaction();
        try {
            // 1. Crear registro en clientes
            $stmtC = $db->prepare("
                INSERT INTO clientes (identificacion, nombres, apellidos, correo, telefono, estado, creado_en)
                VALUES (:identificacion, :nombres, :apellidos, :correo, :telefono, 'ACTIVO', NOW())
            ");
            $stmtC->execute([
                'identificacion' => $identificacion,
                'nombres'        => $nombres,
                'apellidos'      => $apellidos,
                'correo'         => $correo,
                'telefono'       => $telefono ?: null
            ]);
            $idCliente = (int)$db->lastInsertId();

            // 2. Crear cuenta de ahorros inicial con bono de bienvenida de $250.00
            $numCuenta = '22' . str_pad((string)mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
            $stmtAcc = $db->prepare("
                INSERT INTO cuentas (numero_cuenta, id_cliente, tipo, moneda, saldo, estado, abierta_en)
                VALUES (:num, :cliente, 'AHORROS', 'USD', 250.00, 'ACTIVA', NOW())
            ");
            $stmtAcc->execute(['num' => $numCuenta, 'cliente' => $idCliente]);
            $idCuenta = (int)$db->lastInsertId();

            // 3. Crear tarjeta de débito ficticia asociada
            $tokenTarjeta = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            $ultimos4 = (string)mt_rand(1000, 9999);
            $titular = strtoupper("{$nombres} {$apellidos}");
            $stmtT = $db->prepare("
                INSERT INTO tarjetas (id_cuenta, token, ultimos4, tipo, titular, vencimiento, estado, creada_en)
                VALUES (:cta, :tok, :u4, 'DEBITO', :tit, DATE_ADD(CURDATE(), INTERVAL 4 YEAR), 'ACTIVA', NOW())
            ");
            $stmtT->execute(['cta' => $idCuenta, 'tok' => $tokenTarjeta, 'u4' => $ultimos4, 'tit' => $titular]);

            // 4. Crear usuario vinculado con keycloak_id (UUID)
            $keycloakUuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            $stmtU = $db->prepare("
                INSERT INTO usuarios (keycloak_id, username, rol, id_cliente, estado, creado_en)
                VALUES (:k_id, :u, 'CLIENTE', :id_c, 'ACTIVO', NOW())
            ");
            $stmtU->execute([
                'k_id' => $keycloakUuid,
                'u'    => $username,
                'id_c' => $idCliente
            ]);
            $idUsuario = (int)$db->lastInsertId();

            $db->commit();

            $tokenPayload = [
                'id_usuario' => $idUsuario,
                'username'   => $username,
                'rol'        => 'CLIENTE',
                'id_cliente' => $idCliente,
                'nombre'     => "{$nombres} {$apellidos}",
                'correo'     => $correo
            ];
            $token = Auth::generateToken($tokenPayload);

            Response::json([
                'token' => $token,
                'user'  => $tokenPayload
            ], 201, 'Usuario y cuenta bancaria creados correctamente');
        } catch (Exception $e) {
            $db->rollBack();
            Response::error('Error al registrar usuario: ' . $e->getMessage(), 500);
        }
    }

    public static function me(): void {
        $currentUser = Auth::requireAuth();
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT u.id_usuario, u.username, u.rol, u.id_cliente, u.estado,
                   c.identificacion, c.nombres, c.apellidos, c.correo, c.telefono
            FROM usuarios u
            LEFT JOIN clientes c ON u.id_cliente = c.id_cliente
            WHERE u.id_usuario = :id
        ");
        $stmt->execute(['id' => $currentUser['id_usuario']]);
        $user = $stmt->fetch();

        Response::json($user, 200);
    }
}
