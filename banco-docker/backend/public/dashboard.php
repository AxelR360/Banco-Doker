<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user'])) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../src/Database.php';

$user = $_SESSION['user'];
$db = Database::getConnection();
$puedeEvaluarPrestamos = in_array($user['rol'], ['ADMIN', 'ASESOR'], true);
if ($puedeEvaluarPrestamos) {
    $_SESSION['prestamo_csrf'] ??= bin2hex(random_bytes(32));
}

$flashSuccess = '';
$flashError = '';

// PROCESAR OPERACIONES BANCARIAS EN PHP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. TRANSFERENCIA BANCARIA SIMULADA (CU-02, RF-11)
    if ($action === 'transferir') {
        $origenId  = (int)($_POST['id_cuenta_origen'] ?? 0);
        $destinoId = (int)($_POST['id_cuenta_destino'] ?? 0);
        $monto     = (float)($_POST['monto'] ?? 0);
        $desc      = trim($_POST['descripcion'] ?? 'Transferencia bancaria en línea');

        if ($monto <= 0) {
            $flashError = 'El monto a transferir debe ser mayor a cero (RN-02).';
        } elseif ($origenId <= 0 || $destinoId <= 0) {
            $flashError = 'Debe seleccionar una cuenta de origen y especificar la cuenta de destino.';
        } elseif ($origenId === $destinoId) {
            $flashError = 'No se puede transferir a la misma cuenta (RN-05).';
        } else {
            // Generar UUID único de referencia (RN-07)
            $ref = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

            try {
                $sp = $db->prepare("CALL sp_transferir(:orig, :dest, :monto, :ref, :usr, :desc)");
                $sp->execute([
                    'orig'  => $origenId,
                    'dest'  => $destinoId,
                    'monto' => $monto,
                    'ref'   => $ref,
                    'usr'   => $user['id_usuario'],
                    'desc'  => $desc
                ]);
                $sp->closeCursor();
                $flashSuccess = "✅ ¡Transferencia de \${$monto} realizada con éxito! Referencia: " . strtoupper(substr($ref, 0, 8));
            } catch (PDOException $e) {
                $flashError = 'Error en la transferencia: ' . $e->getMessage();
            }
        }
    }

    // 2. SOLICITUD DE PRÉSTAMO (CU-03, RF-14)
    elseif ($action === 'prestamo' && $user['rol'] === 'CLIENTE') {
        $monto = (float)($_POST['monto'] ?? 0);
        $plazo = (int)($_POST['plazo_meses'] ?? 12);

        if ($monto <= 0) {
            $flashError = 'El monto del préstamo debe ser mayor a 0.';
        } else {
            $stmtP = $db->prepare("
                INSERT INTO prestamos (id_cliente, monto, plazo_meses, tasa_anual, estado, solicitado_en)
                VALUES (:cli, :monto, :plazo, 14.50, 'SOLICITADO', NOW())
            ");
            $stmtP->execute([
                'cli'   => $user['id_cliente'],
                'monto' => $monto,
                'plazo' => $plazo
            ]);
            $flashSuccess = "✅ Solicitud de préstamo por \${$monto} registrada en estado SOLICITADO.";
        }
    }

    // 3. BLOQUEAR / DESBLOQUEAR TARJETA (RF-13, CA-09)
    elseif ($action === 'toggle_tarjeta') {
        $idTarjeta = (int)($_POST['id_tarjeta'] ?? 0);
        $estadoActual = $_POST['estado_actual'] ?? 'ACTIVA';
        $nuevoEstado = $estadoActual === 'ACTIVA' ? 'BLOQUEADA' : 'ACTIVA';

        $stmtTar = $db->prepare("UPDATE tarjetas SET estado = :est WHERE id_tarjeta = :id");
        $stmtTar->execute(['est' => $nuevoEstado, 'id' => $idTarjeta]);
        $flashSuccess = "Tarjeta actualizada a {$nuevoEstado}.";
    }

    // 4. ASESOR/ADMIN: EVALUAR PRÉSTAMO (RF-15)
    elseif ($action === 'evaluar_prestamo' && $puedeEvaluarPrestamos) {
        $idPrestamo = (int)($_POST['id_prestamo'] ?? 0);
        $csrf = $_POST['prestamo_csrf'] ?? '';
        if (!is_string($csrf) || !hash_equals($_SESSION['prestamo_csrf'], $csrf) || $idPrestamo <= 0) {
            $flashError = 'No se pudo validar la aprobación. Recarga el panel e inténtalo de nuevo.';
        } else {
            $stmtEv = $db->prepare("
                UPDATE prestamos SET estado = 'APROBADO', id_evaluador = :eval, resuelto_en = NOW()
                WHERE id_prestamo = :id AND estado IN ('SOLICITADO', 'EN_REVISION')
            ");
            $stmtEv->execute(['eval' => $user['id_usuario'], 'id' => $idPrestamo]);
            if ($stmtEv->rowCount() === 1) {
                $flashSuccess = "Préstamo #{$idPrestamo} aprobado.";
            } else {
                $flashError = 'La solicitud ya fue resuelta o no existe.';
            }
        }
    }
}

// CONSULTAR CUENTAS DEL USUARIO
if ($user['rol'] === 'CLIENTE') {
    $stmtCuentas = $db->prepare("SELECT * FROM cuentas WHERE id_cliente = :cli ORDER BY id_cuenta ASC");
    $stmtCuentas->execute(['cli' => $user['id_cliente']]);
} else {
    $stmtCuentas = $db->query("
        SELECT c.*, cl.nombres, cl.apellidos, cl.identificacion 
        FROM cuentas c 
        JOIN clientes cl ON c.id_cliente = cl.id_cliente 
        ORDER BY c.id_cuenta DESC LIMIT 20
    ");
}
$cuentas = $stmtCuentas->fetchAll();

// CONSULTAR TARJETAS
if ($user['rol'] === 'CLIENTE') {
    $stmtTarjetas = $db->prepare("
        SELECT t.* FROM tarjetas t 
        JOIN cuentas c ON t.id_cuenta = c.id_cuenta 
        WHERE c.id_cliente = :cli
    ");
    $stmtTarjetas->execute(['cli' => $user['id_cliente']]);
} else {
    $stmtTarjetas = $db->query("
        SELECT t.*, c.numero_cuenta, cl.nombres, cl.apellidos 
        FROM tarjetas t
        JOIN cuentas c ON t.id_cuenta = c.id_cuenta
        JOIN clientes cl ON c.id_cliente = cl.id_cliente
        LIMIT 10
    ");
}
$tarjetas = $stmtTarjetas->fetchAll();

// CONSULTAR MOVIMIENTOS RECIENTES
if (!empty($cuentas)) {
    $primeraCuentaId = $cuentas[0]['id_cuenta'];
    $stmtMov = $db->prepare("
        SELECT m.*, co.numero_cuenta as cta_origen, cd.numero_cuenta as cta_destino
        FROM movimientos m
        LEFT JOIN cuentas co ON m.id_cuenta_origen = co.id_cuenta
        LEFT JOIN cuentas cd ON m.id_cuenta_destino = cd.id_cuenta
        WHERE m.id_cuenta_origen = :id1 OR m.id_cuenta_destino = :id2
        ORDER BY m.fecha DESC LIMIT 15
    ");
    $stmtMov->execute(['id1' => $primeraCuentaId, 'id2' => $primeraCuentaId]);
    $movimientos = $stmtMov->fetchAll();
} else {
    $movimientos = [];
}

// CONSULTAR PRÉSTAMOS
if ($user['rol'] === 'CLIENTE') {
    $stmtPres = $db->prepare("SELECT * FROM prestamos WHERE id_cliente = :cli ORDER BY id_prestamo DESC");
    $stmtPres->execute(['cli' => $user['id_cliente']]);
} else {
    $stmtPres = $db->query("
        SELECT p.*, cl.nombres, cl.apellidos, cl.identificacion 
        FROM prestamos p 
        JOIN clientes cl ON p.id_cliente = cl.id_cliente 
        ORDER BY (p.estado IN ('SOLICITADO', 'EN_REVISION')) DESC, p.id_prestamo DESC
    ");
}
$prestamos = $stmtPres->fetchAll();

// CALCULAR SALDO TOTAL
$saldoTotal = 0;
foreach ($cuentas as $c) {
    if ($user['rol'] === 'CLIENTE' || $c['id_cliente'] == $user['id_cliente']) {
        $saldoTotal += (float)$c['saldo'];
    }
}
if ($saldoTotal === 0 && !empty($cuentas)) {
    $saldoTotal = (float)$cuentas[0]['saldo'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NexoBank · Panel Bancario en PHP</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="dash-shell">
  <!-- BARRA LATERAL -->
  <aside class="sidebar">
    <div class="brand">
      <span>🏛️ Nexo<span>Bank</span></span>
    </div>
    <ul class="nav-links">
      <li><a href="/" class="active">📊 Resumen General</a></li>
      <li><a href="#transferencias">💸 Transferencias</a></li>
      <li><a href="#tarjetas">💳 Mis Tarjetas</a></li>
      <li><a href="#prestamos">📑 Préstamos</a></li>
      <li style="margin-top: auto;"><a href="/logout.php" class="logout">🚪 Cerrar Sesión</a></li>
    </ul>
  </aside>

  <!-- CONTENIDO PRINCIPAL -->
  <main class="dash-main">
    <header class="dash-header">
      <div>
        <h2>Buenos días, <?= htmlspecialchars($user['nombre']) ?></h2>
        <p>
          Rol: <span class="badge-role badge-<?= strtolower($user['rol']) ?>"><?= $user['rol'] ?></span>
          <?php if (!empty($user['identificacion'])): ?>
            · Cédula: <strong><?= htmlspecialchars($user['identificacion']) ?></strong>
          <?php endif; ?>
          · Usuario: <strong><?= htmlspecialchars($user['username']) ?></strong>
        </p>
      </div>
      <div class="user-pill">
        <div class="avatar"><?= strtoupper(substr($user['nombre'], 0, 2)) ?></div>
        <div class="user-info">
          <strong><?= htmlspecialchars($user['nombre']) ?></strong>
          <span class="badge-role badge-<?= strtolower($user['rol']) ?>"><?= $user['rol'] ?></span>
        </div>
      </div>
    </header>

    <?php if (!empty($flashSuccess)): ?>
      <div class="alert alert-success"><?= $flashSuccess ?></div>
    <?php endif; ?>
    <?php if (!empty($flashError)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <!-- TARJETAS DE SALDO Y RESUMEN -->
    <div class="cards-grid">
      <div class="dash-card card-dark">
        <div class="card-title-sm">Saldo Total Disponible</div>
        <div class="card-big-num">$ <?= number_format($saldoTotal, 2) ?> USD</div>
        <p style="font-size: 13px; opacity: 0.8;">
          <?= !empty($cuentas) ? $cuentas[0]['tipo'] . ' ···· ' . substr($cuentas[0]['numero_cuenta'], -4) : 'Sin cuenta activa' ?>
        </p>
      </div>

      <div class="dash-card">
        <div class="card-title-sm" style="color: var(--text-muted);">Cuentas Activas</div>
        <div class="card-big-num" style="color: #0f172a;"><?= count($cuentas) ?></div>
        <p style="font-size: 13px; color: var(--success); font-weight: 600;">Moneda: USD (Sin comisión)</p>
      </div>

      <div class="dash-card">
        <div class="card-title-sm" style="color: var(--text-muted);">Préstamos Solicitados</div>
        <div class="card-big-num" style="color: #0f172a;"><?= count($prestamos) ?></div>
        <p style="font-size: 13px; color: var(--text-muted);">Simulación académica UTLVTE</p>
      </div>
    </div>

    <!-- SECCIÓN DE OPERACIONES Y MOVIMIENTOS -->
    <div class="content-grid">
      <div>
        <!-- FORMULARIO DE TRANSFERENCIA BANCARIA (CU-02, RF-11) -->
        <div class="panel" id="transferencias">
          <h3>💸 Realizar Transferencia Inmediata</h3>
          <form method="POST" action="/">
            <input type="hidden" name="action" value="transferir">

            <div class="form-group">
              <label>Cuenta de Origen</label>
              <select name="id_cuenta_origen" required>
                <?php foreach ($cuentas as $cta): ?>
                  <option value="<?= $cta['id_cuenta'] ?>">
                    Cuenta <?= htmlspecialchars($cta['numero_cuenta']) ?> (<?= $cta['tipo'] ?>) - Saldo: $<?= number_format($cta['saldo'], 2) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Cuenta Destino (ID o Número)</label>
                <input type="text" name="id_cuenta_destino" placeholder="Ej. 2 o 3" required>
              </div>
              <div class="form-group">
                <label>Monto a Transferir ($ USD)</label>
                <input type="number" step="0.01" min="0.01" name="monto" placeholder="0.00" required>
              </div>
            </div>

            <div class="form-group">
              <label>Descripción / Concepto</label>
              <input type="text" name="descripcion" placeholder="Ej. Pago de arriendo, servicios">
            </div>

            <button type="submit" class="btn-primary" style="margin-top: 10px;">Transferir Fondos en Línea ➔</button>
          </form>
        </div>

        <!-- HISTORIAL DE MOVIMIENTOS RECIENTES (RF-12, RN-07) -->
        <div class="panel">
          <h3>📋 Historial de Movimientos</h3>
          <?php if (empty($movimientos)): ?>
            <p style="color: var(--text-muted); font-size: 13px;">No hay movimientos registrados para esta cuenta.</p>
          <?php else: ?>
            <table class="table-custom">
              <thead>
                <tr>
                  <th>Tipo</th>
                  <th>Concepto</th>
                  <th>Fecha</th>
                  <th style="text-align: right;">Monto</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($movimientos as $m): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($m['tipo']) ?></strong></td>
                    <td><?= htmlspecialchars($m['descripcion'] ?: 'Operación') ?><br><small style="color: #94a3b8; font-size: 11px;">Ref: <?= substr($m['referencia'], 0, 18) ?>...</small></td>
                    <td><?= htmlspecialchars(substr($m['fecha'], 0, 16)) ?></td>
                    <td style="text-align: right; font-weight: 700; color: <?= $m['tipo'] === 'DEPOSITO' ? 'var(--success)' : 'inherit' ?>;">
                      <?= $m['tipo'] === 'DEPOSITO' ? '+' : '-' ?> $<?= number_format($m['monto'], 2) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>

      <!-- COLUMNA DERECHA: TARJETAS Y PRÉSTAMOS -->
      <div>
        <!-- TARJETAS VIRTUALES (RF-13, CA-09) -->
        <div class="panel" id="tarjetas">
          <h3>💳 Tarjetas Ficticias</h3>
          <?php if (empty($tarjetas)): ?>
            <p style="color: var(--text-muted); font-size: 13px;">No posee tarjetas asignadas.</p>
          <?php else: ?>
            <?php foreach ($tarjetas as $t): ?>
              <div class="bank-card-visual <?= $t['estado'] === 'BLOQUEADA' ? 'blocked' : '' ?>">
                <div style="display: flex; justify-content: space-between; font-size: 12px; letter-spacing: 1px;">
                  <span>NEXO BANK · <?= htmlspecialchars($t['tipo']) ?></span>
                  <span style="font-weight: 700;"><?= htmlspecialchars($t['estado']) ?></span>
                </div>
                <div style="font: 700 18px 'Manrope'; letter-spacing: 2px; margin: 24px 0 16px;">
                  •••• &nbsp; •••• &nbsp; •••• &nbsp; <?= htmlspecialchars($t['ultimos4']) ?>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 11px;">
                  <div><small style="opacity: 0.7; font-size: 9px; display: block;">TITULAR</small><?= htmlspecialchars($t['titular']) ?></div>
                  <div><small style="opacity: 0.7; font-size: 9px; display: block;">VENCE</small><?= substr($t['vencimiento'], 0, 7) ?></div>
                </div>

                <form method="POST" action="/" style="margin-top: 14px;">
                  <input type="hidden" name="action" value="toggle_tarjeta">
                  <input type="hidden" name="id_tarjeta" value="<?= $t['id_tarjeta'] ?>">
                  <input type="hidden" name="estado_actual" value="<?= $t['estado'] ?>">
                  <button type="submit" style="width: 100%; height: 32px; border: 0; border-radius: 8px; background: rgba(255,255,255,0.22); color: white; font-weight: 700; font-size: 11px;">
                    <?= $t['estado'] === 'ACTIVA' ? '🔒 Bloquear Tarjeta' : '🔓 Desbloquear Tarjeta' ?>
                  </button>
                </form>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- SOLICITAR PRÉSTAMO (CU-03, RF-14) -->
        <div class="panel" id="prestamos">
          <h3><?= $puedeEvaluarPrestamos ? 'Solicitudes de préstamos' : 'Solicitar Préstamo Simulado' ?></h3>
          <?php if (!$puedeEvaluarPrestamos): ?>
          <form method="POST" action="/dashboard.php#prestamos">
            <input type="hidden" name="action" value="prestamo">

            <div class="form-group">
              <label>Monto Solicitado ($ USD)</label>
              <input type="number" step="50" min="100" name="monto" placeholder="Ej. 1500.00" required>
            </div>

            <div class="form-group">
              <label>Plazo en Meses</label>
              <select name="plazo_meses">
                <option value="6">6 meses (Tasa 14.5%)</option>
                <option value="12" selected>12 meses (1 año)</option>
                <option value="24">24 meses (2 años)</option>
                <option value="36">36 meses (3 años)</option>
              </select>
            </div>

            <button type="submit" class="btn-primary" style="margin-top: 10px;">Solicitar Préstamo ➔</button>
          </form>
          <?php endif; ?>

          <?php if (!empty($prestamos)): ?>
            <div style="margin-top: 20px;">
              <?php if (!$puedeEvaluarPrestamos): ?>
                <small style="font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Mis solicitudes</small>
              <?php endif; ?>
              <ul style="list-style: none; margin-top: 8px;">
                <?php foreach ($prestamos as $p): ?>
                  <li style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; display: flex; justify-content: space-between;">
                    <span>
                      <?php if ($puedeEvaluarPrestamos): ?>
                        #<?= (int)$p['id_prestamo'] ?> · <?= htmlspecialchars($p['nombres'] . ' ' . $p['apellidos']) ?><br>
                      <?php endif; ?>
                      $<?= number_format($p['monto'], 2) ?> (<?= $p['plazo_meses'] ?> meses)
                    </span>
                    <div>
                      <strong style="color: var(--primary);"><?= htmlspecialchars($p['estado']) ?></strong>
                      <?php if ($puedeEvaluarPrestamos && in_array($p['estado'], ['SOLICITADO', 'EN_REVISION'], true)): ?>
                        <form method="POST" action="/dashboard.php#prestamos">
                          <input type="hidden" name="action" value="evaluar_prestamo">
                          <input type="hidden" name="id_prestamo" value="<?= (int)$p['id_prestamo'] ?>">
                          <input type="hidden" name="prestamo_csrf" value="<?= htmlspecialchars($_SESSION['prestamo_csrf']) ?>">
                          <button type="submit" class="btn-primary">Aprobar</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php else: ?>
            <p>Todavía no hay solicitudes.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>
</body>
</html>
