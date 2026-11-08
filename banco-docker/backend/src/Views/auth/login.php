<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NexoBank · Sistema Bancario Digital (MVC)</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="brand-header">
      <div class="brand-icon">🏛️</div>
      <h1>Nexo<span>Bank</span></h1>
      <p>Sistema Bancario Digital · UTLVTE Grupo #4 (Patrón MVC)</p>
    </div>

    <!-- Pestañas Iniciar Sesión / Crear Cuenta -->
    <div class="auth-tabs">
      <a href="/login.php?tab=login" class="<?= $tab === 'login' ? 'active' : '' ?>">Iniciar Sesión</a>
      <a href="/login.php?tab=registro" class="<?= $tab === 'registro' ? 'active' : '' ?>">➕ Crear Cuenta de Banco</a>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($tab === 'login'): ?>
      <!-- INICIAR CON KEYCLOAK SSO -->
      <a href="http://localhost:8085/realms/banco-digital/protocol/openid-connect/auth?client_id=banco-frontend&redirect_uri=http%3A%2F%2Flocalhost%2F&response_type=code&scope=openid" class="btn-sso">
        🔐 Iniciar con <strong>Keycloak SSO</strong> (:8085)
      </a>

      <div class="divider"><span>o con credenciales locales</span></div>

      <!-- FORMULARIO DE LOGIN (USUARIO O NÚMERO DE CÉDULA) -->
      <form method="POST" action="/login.php">
        <input type="hidden" name="action" value="login">

        <div class="form-group">
          <label>Usuario o Número de Cédula</label>
          <input type="text" name="login_input" id="login_input" placeholder="Ej. 0801000001 o axel.rosero" required autofocus>
        </div>

        <div class="form-group">
          <label>Contraseña</label>
          <input type="password" name="password" id="password_input" placeholder="Contraseña (predeterminada: 12345)">
        </div>

        <button type="submit" class="btn-primary">Ingresar al Sistema ➔</button>
      </form>

      <!-- DEMOSTRACIÓN RÁPIDA -->
      <div class="demo-box">
        <p>⚡ Accesos de Demostración con Cédula o Usuario:</p>
        <div class="demo-grid">
          <button type="button" class="btn-demo" onclick="fillLogin('0801000001')">
            👤 Axel Rosero
            <small>Cédula: 0801000001</small>
          </button>
          <button type="button" class="btn-demo" onclick="fillLogin('0801000002')">
            👤 Melany Zambrano
            <small>Cédula: 0801000002</small>
          </button>
          <button type="button" class="btn-demo" onclick="fillLogin('admin')">
            👑 Admin
            <small>Usuario: admin</small>
          </button>
          <button type="button" class="btn-demo" onclick="fillLogin('asesor1')">
            💼 Asesor
            <small>Usuario: asesor1</small>
          </button>
        </div>
      </div>

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
