<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NexoBank · Panel Bancario (Patrón MVC)</title>
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
          <form method="POST" action="/transferir.php">
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

                <form method="POST" action="/tarjeta.php" style="margin-top: 14px;">
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
          <h3>📑 Solicitar Préstamo Simulado</h3>
          <form method="POST" action="/prestamo.php">
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

          <?php if (!empty($prestamos)): ?>
            <div style="margin-top: 20px;">
              <small style="font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Mis Solicitudes:</small>
              <ul style="list-style: none; margin-top: 8px;">
                <?php foreach ($prestamos as $p): ?>
                  <li style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; display: flex; justify-content: space-between;">
                    <span>$<?= number_format($p['monto'], 2) ?> (<?= $p['plazo_meses'] ?> meses)</span>
                    <strong style="color: var(--primary);"><?= $p['estado'] ?></strong>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>
</body>
</html>
