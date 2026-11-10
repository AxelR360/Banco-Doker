import API_URL from '../api'
import { useState, useEffect } from 'react'
import { ArrowLeft, CreditCard, HandCoins, Home, WalletCards, Users, FileText, CheckCircle2 } from 'lucide-react'

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

  const token = localStorage.getItem('nexo_token')

  const notify = (message) => {
    setToast(message)
    window.clearTimeout(window.__nexoToastTimer)
    window.__nexoToastTimer = window.setTimeout(() => setToast(''), 3000)
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

      // 3. PrÃ©stamos
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

  // NavegaciÃ³n segÃºn el rol (IU-02)
  const navigation = user.rol === 'CLIENTE' ? [
    { label: 'Inicio', icon: Home },
    { label: 'Mis cuentas', icon: WalletCards },
    { label: 'Transferencias', icon: ArrowLeft },
    { label: 'Tarjetas', icon: CreditCard },
    { label: 'PrÃ©stamos', icon: HandCoins },
  ] : [
    { label: 'Inicio', icon: Home },
    { label: 'GestiÃ³n Clientes', icon: Users },
    { label: 'Cuentas & DepÃ³sitos', icon: WalletCards },
    { label: 'Evaluar PrÃ©stamos', icon: FileText },
  ]

  const selectSection = (section) => {
    setActive(section)
    if (section === 'Transferencias') setShowTransferModal(true)
    setMenuOpen(false)
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
          descripcion
        })
      })
      const result = await res.json()
      if (!res.ok || !result.success) {
        throw new Error(result.message || 'Error en la transferencia')
      }
      notify(`âœ… Transferencia exitosa. Ref: ${result.data.referencia.substring(0, 8)}`)
      setShowTransferModal(false)
      refreshData()
      return true
    } catch (err) {
      notify(`âŒ ${err.message}`)
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

  // Solicitar prÃ©stamo (RF-14, CU-03)
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
      notify(`âœ… Solicitud de prÃ©stamo enviada (Estado: SOLICITADO)`)
      setShowLoanModal(false)
      refreshData()
    } catch (err) {
      notify(`âŒ Error: ${err.message}`)
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
    executeTransfer,
    toggleCardStatus,
    requestLoan,
    onLogout
  }
}




