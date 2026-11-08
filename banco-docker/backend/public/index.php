<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Si la petición es para la API REST en JSON
if (str_starts_with($uri, '/api/')) {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    require_once __DIR__ . '/../src/Response.php';
    require_once __DIR__ . '/../src/Database.php';
    require_once __DIR__ . '/../src/Auth.php';
    require_once __DIR__ . '/../src/Router.php';
    require_once __DIR__ . '/../src/Controllers/AuthController.php';
    require_once __DIR__ . '/../src/Controllers/ClienteController.php';
    require_once __DIR__ . '/../src/Controllers/CuentaController.php';
    require_once __DIR__ . '/../src/Controllers/MovimientoController.php';
    require_once __DIR__ . '/../src/Controllers/PrestamoController.php';
    require_once __DIR__ . '/../src/Controllers/TarjetaController.php';

    $router = new Router();
    $router->post('/api/v1/auth/login',    [AuthController::class, 'login']);
    $router->post('/api/v1/auth/registro', [AuthController::class, 'registro']);
    $router->get('/api/v1/auth/me',        [AuthController::class, 'me']);
    $router->get('/api/v1/clientes',       [ClienteController::class, 'index']);
    $router->post('/api/v1/clientes',      [ClienteController::class, 'create']);
    $router->get('/api/v1/clientes/{id}',  [ClienteController::class, 'show']);
    $router->get('/api/v1/cuentas/mias',   [CuentaController::class, 'mias']);
    $router->post('/api/v1/cuentas',       [CuentaController::class, 'create']);
    $router->get('/api/v1/cuentas/{id}',   [CuentaController::class, 'show']);
    $router->post('/api/v1/movimientos/transferencia', [MovimientoController::class, 'transferencia']);
    $router->post('/api/v1/movimientos/deposito',      [MovimientoController::class, 'deposito']);
    $router->post('/api/v1/movimientos/retiro',        [MovimientoController::class, 'retiro']);
    $router->get('/api/v1/cuentas/{id}/movimientos',   [MovimientoController::class, 'porCuenta']);
    $router->get('/api/v1/prestamos',               [PrestamoController::class, 'index']);
    $router->post('/api/v1/prestamos',              [PrestamoController::class, 'create']);
    $router->patch('/api/v1/prestamos/{id}/estado', [PrestamoController::class, 'updateEstado']);
    $router->get('/api/v1/tarjetas/mias',           [TarjetaController::class, 'mias']);
    $router->patch('/api/v1/tarjetas/{id}/estado',  [TarjetaController::class, 'updateEstado']);

    $router->dispatch();
    exit;
}

// Enrutamiento de la aplicación web en PHP puro
if ($uri === '/logout.php' || $uri === '/logout') {
    require __DIR__ . '/logout.php';
    exit;
}

if ($uri === '/login.php' || $uri === '/login') {
    require __DIR__ . '/login.php';
    exit;
}

// Ruta principal ('/' o '/index.php'): Si no está logueado, va a login.php; si está logueado, va a dashboard.php
if (empty($_SESSION['user'])) {
    require __DIR__ . '/login.php';
    exit;
} else {
    require __DIR__ . '/dashboard.php';
    exit;
}
