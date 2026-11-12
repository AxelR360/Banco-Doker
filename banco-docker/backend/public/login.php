<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya tiene sesión activa, va directo al dashboard
if (!empty($_SESSION['user'])) {
    header('Location: /dashboard.php');
    exit;
}

require_once __DIR__ . '/../src/Response.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/LocalCredentials.php';

$error = '';
$success = '';
$tab = $_GET['tab'] ?? 'login'; // 'login' o 'registro'

// PROCESAR FORMULARIOS EN PHP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $db = Database::getConnection();

    // 1. INICIAR SESIÓN (Permite USUARIO o NÚMERO DE CÉDULA)
    if ($action === 'login') {
        $loginInput = trim($_POST['login_input'] ?? '');
        $password   = (string)($_POST['password'] ?? '');

        if (empty($loginInput)) {
            $error = 'Por favor ingrese su usuario o su número de cédula.';
        } else {
            $stmt = $db->prepare("
                SELECT u.*,
                       c.identificacion, c.nombres, c.apellidos, c.correo, c.telefono
                FROM usuarios u
                LEFT JOIN clientes c ON u.id_cliente = c.id_cliente
                WHERE u.username = :input1 OR c.identificacion = :input2
            ");
            $stmt->execute(['input1' => $loginInput, 'input2' => $loginInput]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Credenciales no encontradas. No existe un usuario o cédula registrado con ese valor.';
            } elseif ($user['estado'] !== 'ACTIVO') {
                $error = 'Su usuario se encuentra inactivo o bloqueado.';
            } else {
                if (!LocalCredentials::verify($user, $password)) {
                    $error = 'Credenciales incorrectas.';
                } else {
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
                    header('Location: /dashboard.php');
                    exit;
                }
            }
        }
    }

    // 2. CREAR CUENTA PARA EL BANCO (REGISTRO COMPLETO EN PHP)
    elseif ($action === 'registro') {
        $tab = 'registro';
        $identificacion = trim($_POST['identificacion'] ?? '');
        $nombres        = trim($_POST['nombres'] ?? '');
        $apellidos      = trim($_POST['apellidos'] ?? '');
        $correo         = trim($_POST['correo'] ?? '');
        $telefono       = trim($_POST['telefono'] ?? '');
        $username       = trim($_POST['username'] ?? '');
        $password       = (string)($_POST['password'] ?? '');

        if (empty($identificacion) || empty($nombres) || empty($apellidos) || empty($correo) || empty($username) || !LocalCredentials::validPassword($password)) {
            $error = 'Por favor complete todos los campos obligatorios.';
        } else {
            // Validar si ya existe cédula o correo (BD-04)
            $checkC = $db->prepare("SELECT id_cliente FROM clientes WHERE identificacion = :id OR correo = :correo");
            $checkC->execute(['id' => $identificacion, 'correo' => $correo]);
            if ($checkC->fetch()) {
                $error = 'Ya existe un cliente registrado con esa cédula o correo electrónico.';
            } else {
                // Validar si ya existe el nombre de usuario
                $checkU = $db->prepare("SELECT id_usuario FROM usuarios WHERE username = :u");
                $checkU->execute(['u' => $username]);
                if ($checkU->fetch()) {
                    $error = 'El nombre de usuario elegido ya está ocupado. Elija otro.';
                } else {
                    // Transacción atómica en MySQL (RNF-05)
                    $db->beginTransaction();
                    try {
                        // A. Crear registro de Cliente
                        $stmtCli = $db->prepare("
                            INSERT INTO clientes (identificacion, nombres, apellidos, correo, telefono, estado, creado_en)
                            VALUES (:id, :nom, :ape, :cor, :tel, 'ACTIVO', NOW())
                        ");
                        $stmtCli->execute([
                            'id'  => $identificacion,
                            'nom' => $nombres,
                            'ape' => $apellidos,
                            'cor' => $correo,
                            'tel' => $telefono ?: null
                        ]);
                        $idCliente = (int)$db->lastInsertId();

                        // B. Crear Cuenta Bancaria de Ahorros con saldo inicial de bienvenida
                        $numeroCuenta = '22' . str_pad((string)mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
                        $saldoInicial = 250.00;
                        $stmtCta = $db->prepare("
                            INSERT INTO cuentas (numero_cuenta, id_cliente, tipo, moneda, saldo, estado, abierta_en)
                            VALUES (:num, :cli, 'AHORROS', 'USD', :sal, 'ACTIVA', NOW())
                        ");
                        $stmtCta->execute([
                            'num' => $numeroCuenta,
                            'cli' => $idCliente,
                            'sal' => $saldoInicial
                        ]);
                        $idCuenta = (int)$db->lastInsertId();

                        // C. Crear Tarjeta de Débito ficticia (RN-09, BD-07)
                        $tokenTarj = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
                        $ult4 = (string)mt_rand(1000, 9999);
                        $titular = strtoupper("{$nombres} {$apellidos}");
                        $stmtTarj = $db->prepare("
                            INSERT INTO tarjetas (id_cuenta, token, ultimos4, tipo, titular, vencimiento, estado, creada_en)
                            VALUES (:cta, :tok, :u4, 'DEBITO', :tit, DATE_ADD(CURDATE(), INTERVAL 4 YEAR), 'ACTIVA', NOW())
                        ");
                        $stmtTarj->execute([
                            'cta' => $idCuenta,
                            'tok' => $tokenTarj,
                            'u4'  => $ult4,
                            'tit' => $titular
                        ]);

                        // D. Crear Usuario asociado
                        $keycloakId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
                        $stmtUsu = $db->prepare("
                            INSERT INTO usuarios (keycloak_id, username, rol, id_cliente, estado, creado_en, password_hash)
                            VALUES (:kid, :u, 'CLIENTE', :cli, 'ACTIVO', NOW(), :hash)
                        ");
                        $stmtUsu->execute([
                            'kid' => $keycloakId,
                            'u'   => $username,
                            'cli' => $idCliente,
                            'hash' => password_hash($password, PASSWORD_DEFAULT)
                        ]);
                        $idUsuario = (int)$db->lastInsertId();

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

                        header('Location: /dashboard.php');
                        exit;
                    } catch (Exception $e) {
                        $db->rollBack();
                        error_log('Error al registrar una cuenta. Código SQL: ' . $e->getCode());
                        $error = 'No se pudo crear la cuenta. Comprueba los datos e inténtalo de nuevo.';
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NexoBank · Sistema Bancario Digital en PHP</title>
  <link rel="stylesheet" href="/css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="brand-header">
      <div class="brand-icon">🏛️</div>
      <h1>Nexo<span>Bank</span></h1>
      <p>Tu banco, siempre contigo</p>
    </div>
    <div class="welcome-illustration">
      <span aria-hidden="true">🏛️</span>
      <p>Hola, te damos la bienvenida</p>
    </div>

    <!-- Pestañas Iniciar Sesión / Crear Cuenta -->
    <div class="auth-tabs">
      <a href="?tab=login" class="<?= $tab === 'login' ? 'active' : '' ?>">Iniciar Sesión</a>
      <a href="?tab=registro" class="<?= $tab === 'registro' ? 'active' : '' ?>">Crear Usuario</a>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($tab === 'login'): ?>
      <!-- INICIAR CON KEYCLOAK SSO -->
      <a href="<?= getenv('RENDER') === 'true' ? 'https://banco-dokr.onrender.com/?sso=1' : '/?sso=1' ?>" class="btn-sso">
        🔐 Iniciar con <strong>Keycloak SSO</strong>
      </a>

      <div class="divider"><span>Ingresa con tu usuario</span></div>

      <!-- FORMULARIO DE LOGIN (USUARIO O NÚMERO DE CÉDULA) -->
      <form method="POST" action="/login.php">
        <input type="hidden" name="action" value="login">

        <div class="form-group">
          <label>Usuario o Número de Cédula</label>
          <input type="text" name="login_input" id="login_input" autocomplete="username" placeholder="Tu usuario o cédula" required autofocus>
        </div>

        <div class="form-group">
          <label>Contraseña</label>
          <input type="password" name="password" id="password_input" placeholder="Tu contraseña" autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn-primary">Ingresar a la plataforma ➔</button>
      </form>



    <?php else: ?>
      <!-- FORMULARIO PARA CREAR CUENTA PARA EL BANCO -->
      <form method="POST" action="/login.php?tab=registro">
        <input type="hidden" name="action" value="registro">

        <div class="form-row">
          <div class="form-group">
            <label>Nombres *</label>
            <input type="text" name="nombres" placeholder="Ej. Roberto Gary" required autofocus>
          </div>
          <div class="form-group">
            <label>Apellidos *</label>
            <input type="text" name="apellidos" placeholder="Ej. Rosero Pulupa" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Número de Cédula *</label>
            <input type="text" name="identificacion" placeholder="Ej. 0801234567" maxlength="13" required>
          </div>
          <div class="form-group">
            <label>Teléfono Celular</label>
            <input type="text" name="telefono" placeholder="0990000000">
          </div>
        </div>

        <div class="form-group">
          <label>Correo Electrónico *</label>
          <input type="email" name="correo" placeholder="usuario@ejemplo.test" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Nombre de Usuario *</label>
            <input type="text" name="username" placeholder="Ej. roberto.rosero" required>
          </div>
          <div class="form-group">
            <label>Contraseña *</label>
            <input type="password" name="password" placeholder="Clave (ej. 12345)" required>
          </div>
        </div>

        <button type="submit" class="btn-primary">Crear Cuenta y Recibir Tarjeta ➔</button>
      </form>
    <?php endif; ?>

  </div>
</div>

<script>
function fillLogin(val) {
  document.getElementById('login_input').value = val;
  document.getElementById('password_input').value = '12345';
  document.forms[0].submit();
}
</script>
</body>
</html>
