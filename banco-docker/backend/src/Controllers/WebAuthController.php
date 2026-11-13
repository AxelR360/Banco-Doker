<?php

namespace Controllers;

use Core\Controller;
use Database;
use Models\Usuario;
use Models\Cliente;
use Models\Cuenta;
use Models\Tarjeta;
use Exception;

class WebAuthController extends Controller {
    private Usuario $usuarioModel;
    private Cliente $clienteModel;
    private Cuenta $cuentaModel;
    private Tarjeta $tarjetaModel;

    public function __construct() {
        $this->usuarioModel = new Usuario();
        $this->clienteModel = new Cliente();
        $this->cuentaModel  = new Cuenta();
        $this->tarjetaModel = new Tarjeta();
    }

    public function showLogin(): void {
        if (!empty($_SESSION['user'])) {
            $this->redirect('/');
        }
        $tab = $_GET['tab'] ?? 'login';
        $this->render('auth/login', [
            'tab'     => $tab,
            'error'   => $_SESSION['flash_error'] ?? '',
            'success' => $_SESSION['flash_success'] ?? ''
        ]);
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);
    }

    public function login(): void {
        $loginInput = trim($_POST['login_input'] ?? '');
        $password   = trim($_POST['password'] ?? '');

        if (empty($loginInput)) {
            $_SESSION['flash_error'] = 'Por favor ingrese su usuario o su número de cédula.';
            $this->redirect('/login.php');
        }

        // Buscar por usuario o por número de cédula en el Modelo
        $user = $this->usuarioModel->findByLoginOrCedula($loginInput);

        if (!$user) {
            $_SESSION['flash_error'] = 'Credenciales no encontradas. No existe un usuario o cédula registrado con ese valor.';
            $this->redirect('/login.php');
        }

        if ($user['estado'] !== 'ACTIVO') {
            $_SESSION['flash_error'] = 'Su usuario se encuentra inactivo o bloqueado.';
            $this->redirect('/login.php');
        }

        // Verificación de contraseña (permite 12345 o el mismo usuario en entorno académico)
        if (!empty($password) && $password !== '12345' && $password !== $user['username'] && $password !== 'admin' && $password !== 'password') {
            $_SESSION['flash_error'] = 'Contraseña incorrecta. (Para pruebas usa 12345 o tu usuario).';
            $this->redirect('/login.php');
        }

        $fullName = trim(($user['nombres'] ?? '') . ' ' . ($user['apellidos'] ?? ''));
        $_SESSION['user'] = [
            'id_usuario'     => (int)$user['id_usuario'],
            'username'       => $user['username'],
            'rol'            => $user['rol'],
            'id_cliente'     => $user['id_cliente'] ? (int)$user['id_cliente'] : null,
            'identificacion' => $user['identificacion'] ?? '',
            'nombre'         => $fullName ?: ucfirst($user['username']),
            'correo'         => $user['correo'] ?? ''
        ];

        $this->redirect('/');
    }

    public function register(): void {
        $identificacion = trim($_POST['identificacion'] ?? '');
        $nombres        = trim($_POST['nombres'] ?? '');
        $apellidos      = trim($_POST['apellidos'] ?? '');
        $correo         = trim($_POST['correo'] ?? '');
        $telefono       = trim($_POST['telefono'] ?? '');
        $username       = trim($_POST['username'] ?? '');
        $password       = trim($_POST['password'] ?? '12345');

        if (empty($identificacion) || empty($nombres) || empty($apellidos) || empty($correo) || empty($username)) {
            $_SESSION['flash_error'] = 'Por favor complete todos los campos obligatorios.';
            $this->redirect('/login.php?tab=registro');
        }

        // 1. Validar si ya existe cédula o correo en el Modelo Cliente
        if ($this->clienteModel->findByIdentificacionOrEmail($identificacion, $correo)) {
            $_SESSION['flash_error'] = 'Ya existe un cliente registrado con esa cédula o correo electrónico.';
            $this->redirect('/login.php?tab=registro');
        }

        // 2. Validar si ya existe el nombre de usuario en el Modelo Usuario
        if ($this->usuarioModel->findByUsername($username)) {
            $_SESSION['flash_error'] = 'El nombre de usuario elegido ya está ocupado. Elija otro.';
            $this->redirect('/login.php?tab=registro');
        }

        // 3. Crear registros atómicamente con transacción PDO
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // A. Crear Cliente
            $idCliente = $this->clienteModel->create($identificacion, $nombres, $apellidos, $correo, $telefono ?: null);

            // B. Crear Cuenta Bancaria de Ahorros con saldo cero
            $numeroCuenta = '22' . str_pad((string)mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
            $idCuenta = $this->cuentaModel->create($numeroCuenta, $idCliente, 'AHORROS', 0.00, 'USD');

            // C. Crear Tarjeta de Débito ficticia
            $tokenTarj = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
            $ult4 = (string)mt_rand(1000, 9999);
            $titular = strtoupper("{$nombres} {$apellidos}");
            $this->tarjetaModel->create($idCuenta, $tokenTarj, $ult4, $titular, 'DEBITO');

            // D. Crear Usuario asociado
            $keycloakId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
            $idUsuario = $this->usuarioModel->create($keycloakId, $username, 'CLIENTE', $idCliente);

            $db->commit();

            // Iniciar sesión inmediatamente
            $_SESSION['user'] = [
                'id_usuario'     => $idUsuario,
                'username'       => $username,
                'rol'            => 'CLIENTE',
                'id_cliente'     => $idCliente,
                'identificacion' => $identificacion,
                'nombre'         => "{$nombres} {$apellidos}",
                'correo'         => $correo
            ];

            $this->redirect('/');
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash_error'] = 'Error al crear la cuenta: ' . $e->getMessage();
            $this->redirect('/login.php?tab=registro');
        }
    }

    public function logout(): void {
        $_SESSION = [];
        session_destroy();
        $this->redirect('/login.php');
    }
}
