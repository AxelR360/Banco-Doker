import API_URL from '../api'
import { useState, useEffect } from 'react'
import { ArrowLeftRight, CreditCard, HandCoins, Home, WalletCards, Users, FileText } from 'lucide-react'

export function useDashboardController(initialUser, onLogout) {
  const [user, setUser] = useState(initialUser || {
    username: 'usuario',
    nombre: 'Usuario Demo',
    rol: 'CLIENTE',
    id_cliente: 1
  })
  const [active, setActive] = useState('Inicio')
  const [menuOpen, setMenuOpen] = useState(false)
  const [toast, setToast] = useState('')
  const [cuentas, setCuentas] = useState([])
  const [tarjetas, setTarjetas] = useState([])
  const [movimientos, setMovimientos] = useState([])
  const [prestamos, setPrestamos] = useState([])
  const [loading, setLoading] = useState(false)

  // Modales
  const [showTransferModal, setShowTransferModal] = useState(false)
  const [showLoanModal, setShowLoanModal] = useState(false)
  const [showDepositModal, setShowDepositModal] = useState(false)
  const [showCreateAccountModal, setShowCreateAccountModal] = useState(false)

  const token = localStorage.getItem('nexo_token')

  const notify = (message) => {
    setToast(message)
    window.clearTimeout(window.__nexoToastTimer)
    window.__nexoToastTimer = window.setTimeout(() => setToast(''), 3500)
  }

  // Cargar datos de la API
  const refreshData = async () => {
    if (!token) return
    try {
      // 1. Cuentas
      const resCuentas = await fetch(`${API_URL}/api/v1/cuentas/mias`, {
        headers: { Authorization: `Bearer ${token}` }
      })
      const dataCuentas = await resCuentas.json()
      if (dataCuentas.success && Array.isArray(dataCuentas.data)) {
        setCuentas(dataCuentas.data)

        // Cargar movimientos de la primera cuenta si existe
        if (dataCuentas.data.length > 0) {
          const firstId = dataCuentas.data[0].id_cuenta
          const resMov = await fetch(`${API_URL}/api/v1/cuentas/${firstId}/movimientos`, {
            headers: { Authorization: `Bearer ${token}` }
          })
          const dataMov = await resMov.json()
          if (dataMov.success && Array.isArray(dataMov.data)) {
            setMovimientos(dataMov.data)
          }
        }
      }

      // 2. Tarjetas
      const resTarjetas = await fetch(`${API_URL}/api/v1/tarjetas/mias`, {
        headers: { Authorization: `Bearer ${token}` }
      })
      const dataTarjetas = await resTarjetas.json()
      if (dataTarjetas.success && Array.isArray(dataTarjetas.data)) {
        setTarjetas(dataTarjetas.data)
      }

      // 3. Préstamos
      const resPrestamos = await fetch(`${API_URL}/api/v1/prestamos`, {
        headers: { Authorization: `Bearer ${token}` }
      })
      const dataPrestamos = await resPrestamos.json()
      if (dataPrestamos.success && Array.isArray(dataPrestamos.data)) {
        setPrestamos(dataPrestamos.data)
      }
    } catch (e) {
      console.warn('Usando datos locales o error de red:', e)
    }
  }

  useEffect(() => {
    refreshData()
  }, [])

  // Navegación según el rol (IU-02)
  const navigation = user.rol === 'CLIENTE' ? [
    { label: 'Inicio', icon: Home },
    { label: 'Mis cuentas', icon: WalletCards },
    { label: 'Transferencias', icon: ArrowLeftRight },
    { label: 'Tarjetas', icon: CreditCard },
    { label: 'Préstamos', icon: HandCoins },
  ] : [
    { label: 'Inicio', icon: Home },
    { label: 'Gestión Clientes', icon: Users },
    { label: 'Cuentas & Depósitos', icon: WalletCards },
    { label: 'Evaluar Préstamos', icon: FileText },
  ]

  const selectSection = (section) => {
    setActive(section)
    setMenuOpen(false)
    if (section === 'Transferencias' || section === 'Transeerencias') {
      setShowTransferModal(true)
    } else if (section === 'Cuentas & Depósitos' || section === 'Cuentas & DepÃ³sitos') {
      setShowDepositModal(true)
    } else if (section === 'Préstamos' || section === 'PrÃ©stamos' || section === 'Evaluar Préstamos' || section === 'Evaluar PrÃ©stamos') {
      setShowLoanModal(true)
    }
  }

  // Realizar transferencia (CU-02, RF-11)
  const executeTransfer = async ({ origenId, destinoId, monto, descripcion }) => {
    try {
      const res = await fetch(`${API_URL}/api/v1/movimientos/transferencia`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`
        },
        body: JSON.stringify({
          id_cuenta_origen: origenId,
          id_cuenta_destino: destinoId,
          monto: parseFloat(monto),
          descripcion: descripcion || 'Transferencia inmediata'
        })
      })
      const result = await res.json()
      if (!res.ok || !result.success) {
        throw new Error(result.message || 'Error en la transferencia')
      }
      const ref = result.data?.referencia ? result.data.referencia.substring(0, 8) : 'OK'
      notify(`✅ Transferencia exitosa. Ref: ${ref}`)
      setShowTransferModal(false)
      refreshData()
      return true
    } catch (err) {
      notify(`❌ ${err.message}`)
      return false
    }
  }

  // Realizar depósito de fondos
  const executeDeposit = async ({ cuentaId, monto, descripcion }) => {
    try {
      const res = await fetch(`${API_URL}/api/v1/movimientos/deposito`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`
        },
        body: JSON.stringify({
          id_cuenta: cuentaId,
          monto: parseFloat(monto),
          descripcion: descripcion || 'Depósito en efectivo'
        })
      })
      const result = await res.json()
      if (!res.ok || !result.success) {
        throw new Error(result.message || 'Error en el depósito')
      }
      notify(`✅ Depósito de $${parseFloat(monto).toFixed(2)} acreditado correctamente`)
      setShowDepositModal(false)
      refreshData()
      return true
    } catch (err) {
      notify(`❌ ${err.message}`)
      return false
    }
  }

  // Crear nueva cuenta bancaria
  const executeCreateAccount = async ({ tipo, saldoInicial }) => {
    try {
      const res = await fetch(`${API_URL}/api/v1/cuentas`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`
        },
        body: JSON.stringify({
          id_cliente: user.id_cliente,
          tipo: tipo || 'AHORROS',
          moneda: 'USD',
          saldo: parseFloat(saldoInicial || 0)
        })
      })
      const result = await res.json()
      if (!res.ok || !result.success) {
        throw new Error(result.message || 'Error al abrir la cuenta')
      }
      notify(`✅ Cuenta creada exitosamente: ${result.data?.numero_cuenta || ''}`)
      setShowCreateAccountModal(false)
      refreshData()
      return true
    } catch (err) {
      notify(`❌ ${err.message}`)
      return false
    }
  }

  // Alternar bloqueo de tarjeta (RF-13, CA-09)
  const toggleCardStatus = async (idTarjeta, currentEstado) => {
    const nuevo = currentEstado === 'ACTIVA' ? 'BLOQUEADA' : 'ACTIVA'
    try {
      const res = await fetch(`${API_URL}/api/v1/tarjetas/${idTarjeta}/estado`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`
        },
        body: JSON.stringify({ estado: nuevo })
      })
      const result = await res.json()
      if (!res.ok || !result.success) throw new Error(result.message)
      notify(`Tarjeta actualizada a ${nuevo}`)
      refreshData()
    } catch (err) {
      notify(`Error: ${err.message}`)
    }
  }

  // Solicitar préstamo (RF-14, CU-03)
  const requestLoan = async ({ monto, plazo }) => {
    try {
      const res = await fetch(`${API_URL}/api/v1/prestamos`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`
        },
        body: JSON.stringify({ monto: parseFloat(monto), plazo_meses: parseInt(plazo, 10) })
      })
      const result = await res.json()
      if (!res.ok || !result.success) throw new Error(result.message)
      notify(`✅ Solicitud de préstamo enviada (Estado: SOLICITADO)`)
      setShowLoanModal(false)
      refreshData()
    } catch (err) {
      notify(`❌ Error: ${err.message}`)
    }
  }

  return {
    user,
    active,
    menuOpen,
    setMenuOpen,
    toast,
    navigation,
    notify,
    selectSection,
    cuentas,
    tarjetas,
    movimientos,
    prestamos,
    showTransferModal,
    setShowTransferModal,
    showLoanModal,
    setShowLoanModal,
    showDepositModal,
    setShowDepositModal,
    showCreateAccountModal,
    setShowCreateAccountModal,
    executeTransfer,
    executeDeposit,
    executeCreateAccount,
    toggleCardStatus,
    requestLoan,
    refreshData,
    onLogout
  }
}
