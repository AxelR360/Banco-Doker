<?php

class ClienteController {
    public static function index(): void {
        Auth::requireAuth(['ADMIN', 'ASESOR']);
        $db = Database::getConnection();

        $search = trim($_GET['q'] ?? '');
        if ($search !== '') {
            $stmt = $db->prepare("
                SELECT * FROM clientes
                WHERE identificacion LIKE :q OR nombres LIKE :q OR apellidos LIKE :q OR correo LIKE :q
                ORDER BY id_cliente DESC
            ");
            $stmt->execute(['q' => "%{$search}%"]);
        } else {
            $stmt = $db->query("SELECT * FROM clientes ORDER BY id_cliente DESC");
        }

        $clientes = $stmt->fetchAll();
        Response::json($clientes, 200);
    }

    public static function show(array $params): void {
        $currentUser = Auth::requireAuth();
        $id = (int)$params['id'];

        // Si es cliente, solo puede ver su propio perfil (RN-08, RNF-04)
        if ($currentUser['rol'] === 'CLIENTE' && $currentUser['id_cliente'] !== $id) {
            Response::error('No tienes permiso para consultar información de otro cliente', 403);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM clientes WHERE id_cliente = :id");
        $stmt->execute(['id' => $id]);
        $cliente = $stmt->fetch();

        if (!$cliente) {
            Response::error('Cliente no encontrado', 404);
        }

        Response::json($cliente, 200);
    }

    public static function create(): void {
        Auth::requireAuth(['ADMIN', 'ASESOR']);
        $body = Router::getJsonBody();

        $identificacion = trim($body['identificacion'] ?? '');
        $nombres = trim($body['nombres'] ?? '');
        $apellidos = trim($body['apellidos'] ?? '');
        $correo = trim($body['correo'] ?? '');
        $telefono = trim($body['telefono'] ?? '');
        $direccion = trim($body['direccion'] ?? '');

        if (empty($identificacion) || empty($nombres) || empty($apellidos) || empty($correo)) {
            Response::error('Campos obligatorios incompletos (identificacion, nombres, apellidos, correo)', 400);
        }

        $db = Database::getConnection();

        // Validar unicidad de identificacion y correo (BD-04, RF-01)
        $check = $db->prepare("SELECT id_cliente FROM clientes WHERE identificacion = :identificacion OR correo = :correo");
        $check->execute(['identificacion' => $identificacion, 'correo' => $correo]);
        if ($check->fetch()) {
            Response::error('Ya existe un cliente con la misma identificación o correo', 409);
        }

        $stmt = $db->prepare("
            INSERT INTO clientes (identificacion, nombres, apellidos, correo, telefono, direccion, estado)
            VALUES (:identificacion, :nombres, :apellidos, :correo, :telefono, :direccion, 'ACTIVO')
        ");
        $stmt->execute([
            'identificacion' => $identificacion,
            'nombres'        => $nombres,
            'apellidos'      => $apellidos,
            'correo'         => $correo,
            'telefono'       => $telefono ?: null,
            'direccion'      => $direccion ?: null
        ]);

        $newId = (int)$db->lastInsertId();
        Response::json(['id_cliente' => $newId], 201, 'Cliente registrado exitosamente');
    }
}
