<?php

namespace Controllers;

use Core\Controller;
use Models\Cuenta;
use Models\Tarjeta;
use Models\Movimiento;
use Models\Prestamo;
use Exception;

class DashboardController extends Controller {
    private Cuenta $cuentaModel;
    private Tarjeta $tarjetaModel;
    private Movimiento $movimientoModel;
    private Prestamo $prestamoModel;

    public function __construct() {
        $this->cuentaModel      = new Cuenta();
        $this->tarjetaModel     = new Tarjeta();
        $this->movimientoModel  = new Movimiento();
        $this->prestamoModel    = new Prestamo();
    }

    public function index(): void {
        if (empty($_SESSION['user'])) {
            $this->redirect('/login.php');
        }

        $user = $_SESSION['user'];

        // Obtener cuentas a través del Modelo Cuenta
        if ($user['rol'] === 'CLIENTE') {
            $cuentas = $this->cuentaModel->getByCliente($user['id_cliente']);
            $tarjetas = $this->tarjetaModel->getByCliente($user['id_cliente']);
            $prestamos = $this->prestamoModel->getByCliente($user['id_cliente']);
        } else {
            $cuentas = $this->cuentaModel->all();
            $tarjetas = $this->tarjetaModel->getByCliente($cuentas[0]['id_cliente'] ?? 1);
            $prestamos = $this->prestamoModel->all();
        }

        // Obtener movimientos de la cuenta principal
        $movimientos = [];
        if (!empty($cuentas)) {
            $movimientos = $this->movimientoModel->getByCuenta($cuentas[0]['id_cuenta'], 15);
        }

        // Calcular saldo total
        $saldoTotal = 0;
        foreach ($cuentas as $c) {
            if ($user['rol'] === 'CLIENTE' || (isset($c['id_cliente']) && $c['id_cliente'] == $user['id_cliente'])) {
                $saldoTotal += (float)$c['saldo'];
            }
        }
        if ($saldoTotal === 0 && !empty($cuentas)) {
            $saldoTotal = (float)$cuentas[0]['saldo'];
        }

        $this->render('dashboard/index', [
            'user'         => $user,
            'cuentas'      => $cuentas,
            'tarjetas'     => $tarjetas,
            'prestamos'    => $prestamos,
            'movimientos'  => $movimientos,
            'saldoTotal'   => $saldoTotal,
            'flashSuccess' => $_SESSION['flash_success'] ?? '',
            'flashError'   => $_SESSION['flash_error'] ?? ''
        ]);

        unset($_SESSION['flash_success'], $_SESSION['flash_error']);
    }

    public function transferir(): void {
        if (empty($_SESSION['user'])) { $this->redirect('/login.php'); }

        $user      = $_SESSION['user'];
        $origenId  = (int)($_POST['id_cuenta_origen'] ?? 0);
        $destinoId = (int)($_POST['id_cuenta_destino'] ?? 0);
        $monto     = (float)($_POST['monto'] ?? 0);
        $desc      = trim($_POST['descripcion'] ?? 'Transferencia bancaria en línea');

        if ($monto <= 0) {
            $_SESSION['flash_error'] = 'El monto debe ser mayor a cero (RN-02).';
        } elseif ($origenId <= 0 || $destinoId <= 0) {
            $_SESSION['flash_error'] = 'Especifique cuenta de origen y cuenta de destino válidas.';
        } elseif ($origenId === $destinoId) {
            $_SESSION['flash_error'] = 'No se puede transferir a la misma cuenta (RN-05).';
        } else {
            $ref = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
            try {
                $this->movimientoModel->transferir($origenId, $destinoId, $monto, $ref, $user['id_usuario'], $desc);
                $_SESSION['flash_success'] = "✅ ¡Transferencia de \${$monto} realizada con éxito! Ref: " . strtoupper(substr($ref, 0, 8));
            } catch (Exception $e) {
                $_SESSION['flash_error'] = 'Error en la transferencia: ' . $e->getMessage();
            }
        }

        $this->redirect('/');
    }

    public function solicitarPrestamo(): void {
        if (empty($_SESSION['user'])) { $this->redirect('/login.php'); }

        $user  = $_SESSION['user'];
        $monto = (float)($_POST['monto'] ?? 0);
        $plazo = (int)($_POST['plazo_meses'] ?? 12);

        if ($monto <= 0) {
            $_SESSION['flash_error'] = 'El monto del préstamo debe ser mayor a 0.';
        } else {
            $this->prestamoModel->create($user['id_cliente'] ?: 1, $monto, $plazo);
            $_SESSION['flash_success'] = "✅ Solicitud de préstamo por \${$monto} registrada en estado SOLICITADO.";
        }

        $this->redirect('/#prestamos');
    }

    public function toggleTarjeta(): void {
        if (empty($_SESSION['user'])) { $this->redirect('/login.php'); }

        $idTarjeta    = (int)($_POST['id_tarjeta'] ?? 0);
        $estadoActual = $_POST['estado_actual'] ?? 'ACTIVA';

        $nuevo = $this->tarjetaModel->toggleEstado($idTarjeta, $estadoActual);
        $_SESSION['flash_success'] = "Estado de tarjeta actualizado a {$nuevo}.";

        $this->redirect('/#tarjetas');
    }
}
