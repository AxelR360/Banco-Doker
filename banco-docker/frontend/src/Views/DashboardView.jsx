import AdministrationPanel from './AdministrationPanel'
import React, { useState } from 'react'
import {
  ArrowDownLeft, ArrowLeftRight, ArrowUpRight, Bell, ChevronDown,
  ChevronRight, CircleHelp, HandCoins, Landmark, Menu, Search, Settings,
  ShieldCheck, LogOut, CheckCircle2, AlertCircle, X, CreditCard, Lock, Unlock, PlusCircle
} from 'lucide-react'

export default function DashboardView({ controller }) {
  const {
    user, active, menuOpen, setMenuOpen, toast, navigation, notify, selectSection,
    cuentas, tarjetas, movimientos, prestamos,
    showTransferModal, setShowTransferModal,
    showLoanModal, setShowLoanModal,
    showDepositModal, setShowDepositModal,
    showCreateAccountModal, setShowCreateAccountModal,
    executeTransfer, executeDeposit, executeCreateAccount,
    solicitarCredito, toggleCardStatus, requestLoan, onLogout
  } = controller

  // Transfer form state
  const [transferOrigen, setTransferOrigen] = useState('')
  const [transferDestino, setTransferDestino] = useState('')
  const [transferMonto, setTransferMonto] = useState('')
  const [transferDesc, setTransferDesc] = useState('')

  // Deposit form state
  const [depositCuenta, setDepositCuenta] = useState('')
  const [depositMonto, setDepositMonto] = useState('')
  const [depositDesc, setDepositDesc] = useState('')

  // Create account form state
  const [newAccTipo, setNewAccTipo] = useState('AHORROS')

  // Loan form state
  const [loanMonto, setLoanMonto] = useState('')
  const [loanPlazo, setLoanPlazo] = useState('12')

  // Calculate total balance from cuentas
  const totalBalance = cuentas.length > 0
    ? cuentas.reduce((acc, c) => acc + parseFloat(c.saldo || 0), 0).toFixed(2)
    : '0.00'

  const primaryAccount = cuentas[0] || {
    numero_cuenta: 'Sin cuentas',
    tipo: 'AHORROS',
    saldo: totalBalance,
    moneda: 'USD'
  }

  const userInitials = user.nombre
    ? user.nombre.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()
    : 'US'

  const handleTransferSubmit = async (e) => {
    e.preventDefault()
    const success = await executeTransfer({
      origenId: transferOrigen || (cuentas[0] ? cuentas[0].id_cuenta : 1),
      destinoId: transferDestino,
      monto: transferMonto,
      descripcion: transferDesc || 'Transferencia inmediata'
    })
    if (!success) return
    setTransferMonto('')
    setTransferDestino('')
    setTransferDesc('')
  }

  const handleDepositSubmit = (e) => {
    e.preventDefault()
    executeDeposit({
      cuentaId: depositCuenta || (cuentas[0] ? cuentas[0].id_cuenta : 1),
      monto: depositMonto,
      descripcion: depositDesc || 'Depósito de fondos'
    })
    setDepositMonto('')
    setDepositDesc('')
  }

  const handleCreateAccountSubmit = (e) => {
    e.preventDefault()
    executeCreateAccount({
      tipo: newAccTipo
    })
  }

  const handleLoanSubmit = (e) => {
    e.preventDefault()
    requestLoan({ monto: loanMonto, plazo: loanPlazo })
    setLoanMonto('')
  }

  return (
    <div className="app-shell">
      <aside className={`sidebar ${menuOpen ? 'open' : ''}`}>
        <div className="brand">
          <span className="brand-mark"><Landmark size={22} /></span>
          <span>Nexo<span>Bank</span></span>
        </div>
        <nav>
          <p className="nav-label">Navegación</p>
          {navigation.map(({ label, icon: Icon }) => (
            <button key={label} className={active === label ? 'active' : ''} onClick={() => selectSection(label)}>
              <Icon size={20} /> {label}
            </button>
          ))}
          <p className="nav-label secondary">Cuenta</p>
          <button onClick={onLogout} className="logout-nav-btn">
            <LogOut size={20} /> Cerrar Sesión
          </button>
        </nav>
        <div className="support">
          <CircleHelp size={19} />
          <div>
            <strong>Soporte Bancario</strong>
            <span>UTLVTE Grupo #4</span>
          </div>
        </div>
      </aside>

      {menuOpen && <button className="backdrop" aria-label="Cerrar menú" onClick={() => setMenuOpen(false)} />}

      <main>
        <header>
          <button className="menu-button" aria-label="Abrir menú" onClick={() => setMenuOpen(true)}>
            <Menu />
          </button>
          <div className="search">
            <Search size={18} />
            <input aria-label="Buscar" placeholder="Buscar movimientos, cuentas..." />
          </div>
          <div className="header-actions">
            <button className="icon-button" aria-label="Notificaciones">
              <Bell size={20} /><span />
            </button>
            <div className="profile-pill">
              <span className="avatar">{userInitials}</span>
              <div>
                <strong>{user.nombre}</strong>
                <small className={`badge-role ${user.rol.toLowerCase()}`}>{user.rol}</small>
              </div>
              <button onClick={onLogout} className="btn-logout-small" title="Cerrar sesión">
                <LogOut size={16} />
              </button>
            </div>
          </div>
        </header>

        <section className="content">
          {active === 'Gestión Clientes' && user.rol !== 'CLIENTE' ? <AdministrationPanel user={user} /> : <>
          <div className="welcome">
            <div>
              <p>Sesión activa como {user.username}</p>
              <h1>Panel de Control · {user.rol}</h1>
            </div>
            <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
              <button className="primary" style={{ background: '#10b981', borderColor: '#10b981' }} onClick={() => setShowDepositModal(true)}>
                <span>＋</span> Depósito
              </button>
              <button className="primary" onClick={() => setShowTransferModal(true)}>
                <span>＋</span> Nueva transferencia
              </button>
            </div>
          </div>

          <div className="balance-grid">
            <article className="balance-card dark-card">
              <div className="card-top">
                <span>Saldo Total Disponible</span>
                <span className="badge-currency">{primaryAccount.moneda || 'USD'}</span>
              </div>
              <strong>$ {totalBalance}</strong>
              <p>{primaryAccount.tipo} ··· {primaryAccount.numero_cuenta.slice(-4)}</p>
              <div className="mini-chart" aria-hidden="true">
                {Array.from({ length: 10 }, (_, i) => <i key={i} />)}
              </div>
            </article>

            <article className="balance-card light-card">
              <div className="card-top">
                <span>Cuentas Vinculadas</span>
                <span className="trend">{cuentas.length || 1} Activa(s)</span>
              </div>
              <strong>{cuentas.length ? cuentas[0].numero_cuenta : '2200000001'}</strong>
              <div style={{ marginTop: '10px' }}>
                <button
                  type="button"
                  style={{
                    background: 'rgba(79, 70, 229, 0.08)',
                    color: '#4f46e5',
                    border: '1px solid rgba(79, 70, 229, 0.2)',
                    padding: '5px 10px',
                    borderRadius: '6px',
                    fontSize: '12px',
                    fontWeight: '600',
                    cursor: 'pointer'
                  }}
                  onClick={() => setShowCreateAccountModal(true)}
                >
                  ＋ Abrir otra cuenta
                </button>
              </div>
              <div className="bar-chart" aria-hidden="true">
                {Array.from({ length: 7 }, (_, i) => <i key={i} />)}
              </div>
            </article>

            <article className="balance-card light-card">
              <div className="card-top">
                <span>Préstamos Simulados</span>
                <span className="trend">{prestamos.length} Solicitud(es)</span>
              </div>
              <strong>$ {prestamos.reduce((sum, p) => sum + parseFloat(p.monto || 0), 0).toFixed(2)}</strong>
              <div className="bar-chart expenses" aria-hidden="true">
                {Array.from({ length: 7 }, (_, i) => <i key={i} />)}
              </div>
            </article>
          </div>

          <section className="panel" id="ahorros">
            <h2>Cuentas de ahorro</h2>
            {cuentas.some(c => c.tipo === 'AHORROS') ? cuentas.filter(c => c.tipo === 'AHORROS').map(c => (
              <p key={c.id_cuenta}>{c.numero_cuenta} · {c.estado} · {c.moneda} {Number(c.saldo).toFixed(2)}</p>
            )) : <p>No hay cuentas de ahorro.</p>}
          </section>

          <div className="quick-section">
            <div className="section-title"><h2>Acciones Rápidas</h2></div>
            <div className="quick-grid">
              <button onClick={() => setShowTransferModal(true)}>
                <span className="quick-icon purple"><ArrowUpRight /></span>
                <div><strong>Transferir</strong><small>Enviar dinero entre cuentas</small></div>
                <ChevronRight />
              </button>
              <button onClick={() => setShowDepositModal(true)}>
                <span className="quick-icon green"><ArrowDownLeft /></span>
                <div><strong>Depósito</strong><small>Añadir fondos a la cuenta</small></div>
                <ChevronRight />
              </button>
              <button onClick={() => setShowLoanModal(true)}>
                <span className="quick-icon orange"><HandCoins /></span>
                <div><strong>Solicitar Préstamo</strong><small>Simulación académica</small></div>
                <ChevronRight />
              </button>
            </div>
          </div>

          <div className="bottom-grid">
            <article className="panel movements">
              <div className="panel-heading">
                <div>
                  <h2>Movimientos Recientes</h2>
                  <p>Trazabilidad y auditoría (RN-07, BD-08)</p>
                </div>
                <button onClick={() => notify('Historial actualizado')}>
                  Ver todos <ChevronRight size={16} />
                </button>
              </div>
              <div className="movement-list">
                {movimientos.length > 0 ? (
                  movimientos.map(item => (
                    <div className="movement" key={item.id_movimiento || item.referencia}>
                      <span className={`movement-icon ${item.tipo === 'DEPOSITO' ? 'in' : 'out'}`}>
                        {item.tipo === 'DEPOSITO' ? <ArrowDownLeft /> : <ArrowUpRight />}
                      </span>
                      <div>
                        <strong>{item.descripcion || item.tipo}</strong>
                        <small>Ref: {(item.referencia || '').substring(0, 18)}...</small>
                      </div>
                      <time>{(item.fecha || '').substring(0, 16)}</time>
                      <b className={item.tipo === 'DEPOSITO' ? 'positive' : ''}>
                        {item.tipo === 'DEPOSITO' ? '+' : '-'} ${parseFloat(item.monto).toFixed(2)}
                      </b>
                    </div>
                  ))
                ) : (
                  <div className="movement-empty">
                    <p>No hay movimientos registrados para esta cuenta.</p>
                  </div>
                )}
              </div>
            </article>

            <article className="panel card-panel">
              {[['CREDITO', 'Tarjetas de crédito'], ['DEBITO', 'Tarjetas de débito']].map(([tipo, titulo]) => {
                const grupo = tarjetas.filter(t => t.tipo === tipo)
                return <section key={tipo}>
                  <h2>{titulo}</h2>
                  {tipo === 'CREDITO' && user.rol === 'CLIENTE' && <>
                    <p>Evaluación simulada con el historial de tus cuentas.</p>
                    <button type="button" className="outline-button" onClick={solicitarCredito}>Solicitar tarjeta de crédito</button>
                  </>}
                  {grupo.length ? grupo.map(t => (
                  <div key={t.id_tarjeta} className={`bank-card ${t.estado === 'BLOQUEADA' ? 'blocked' : ''}`}>
                    <div className="card-header-inner">
                      <span>NEXO BANK · {t.tipo}</span>
                      <span className={`badge-card-status ${t.estado.toLowerCase()}`}>{t.estado}</span>
                    </div>
                    <div className="chip" />
                    <strong>•••• &nbsp; •••• &nbsp; •••• &nbsp; {t.ultimos4}</strong>
                    <footer>
                      <span><small>TITULAR</small>{t.titular}</span>
                      <span><small>VENCE</small>{t.vencimiento ? t.vencimiento.substring(0, 7) : '12/29'}</span>
                    </footer>
                    <button
                      className="btn-card-toggle"
                      onClick={() => toggleCardStatus(t.id_tarjeta, t.estado)}
                    >
                      {t.estado === 'ACTIVA' ? <Lock size={14} /> : <Unlock size={14} />}
                      {t.estado === 'ACTIVA' ? 'Bloquear Tarjeta' : 'Desbloquear Tarjeta'}
                    </button>
                  </div>
                  )) : <p>No hay tarjetas asignadas de este tipo.</p>}
                </section>
              })}
            </article>
          </div>
          </>}
        </section>
      </main>

      {/* MODAL TRANSFERENCIA (CU-02, RF-11) */}
      {showTransferModal && (
        <div className="modal-backdrop">
          <div className="modal-content">
            <div className="modal-header">
              <h3><ArrowLeftRight size={20} /> Transferencia Bancaria Simulada</h3>
              <button className="btn-close" onClick={() => setShowTransferModal(false)}><X size={18} /></button>
            </div>
            <form onSubmit={handleTransferSubmit}>
              <div className="form-group">
                <label>Cuenta de Origen</label>
                <select value={transferOrigen} onChange={(e) => setTransferOrigen(e.target.value)} required>
                  {cuentas.map(c => (
                    <option key={c.id_cuenta} value={c.id_cuenta}>
                      {c.numero_cuenta} ({c.tipo}) - Saldo: ${c.saldo}
                    </option>
                  ))}
                  {cuentas.length === 0 && <option value="1">Cuenta Principal 2200000001 ($12,480.50)</option>}
                </select>
              </div>

              <div className="form-group">
                <label>Cuenta de Destino (ID o Número)</label>
                <input
                  type="text"
                  placeholder="Ej. 2 o 2200000002 (Melany Zambrano)"
                  value={transferDestino}
                  onChange={(e) => setTransferDestino(e.target.value)}
                  required
                />
              </div>

              <div className="form-group">
                <label>Monto a Transferir ($ USD)</label>
                <input
                  type="number"
                  step="0.01"
                  min="0.01"
                  placeholder="0.00"
                  value={transferMonto}
                  onChange={(e) => setTransferMonto(e.target.value)}
                  required
                />
              </div>

              <div className="form-group">
                <label>Descripción / Concepto</label>
                <input
                  type="text"
                  placeholder="Ej. Pago de servicios, transferencia personal"
                  value={transferDesc}
                  onChange={(e) => setTransferDesc(e.target.value)}
                />
              </div>

              <div className="modal-actions">
                <button type="button" className="btn-cancel" onClick={() => setShowTransferModal(false)}>Cancelar</button>
                <button type="submit" className="primary">Confirmar Transferencia</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL DEPÓSITO */}
      {showDepositModal && (
        <div className="modal-backdrop">
          <div className="modal-content">
            <div className="modal-header">
              <h3><ArrowDownLeft size={20} /> Realizar Depósito de Fondos</h3>
              <button className="btn-close" onClick={() => setShowDepositModal(false)}><X size={18} /></button>
            </div>
            <form onSubmit={handleDepositSubmit}>
              <div className="form-group">
                <label>Cuenta de Destino</label>
                <select value={depositCuenta} onChange={(e) => setDepositCuenta(e.target.value)} required>
                  {cuentas.map(c => (
                    <option key={c.id_cuenta} value={c.id_cuenta}>
                      {c.numero_cuenta} ({c.tipo}) - Saldo actual: ${c.saldo}
                    </option>
                  ))}
                  {cuentas.length === 0 && <option value="1">Cuenta Principal 2200000001</option>}
                </select>
              </div>

              <div className="form-group">
                <label>Monto a Depositar ($ USD)</label>
                <input
                  type="number"
                  step="0.01"
                  min="0.01"
                  placeholder="Ej. 100.00"
                  value={depositMonto}
                  onChange={(e) => setDepositMonto(e.target.value)}
                  required
                />
              </div>

              <div className="form-group">
                <label>Concepto / Detalle</label>
                <input
                  type="text"
                  placeholder="Ej. Depósito en efectivo, abono de nómina"
                  value={depositDesc}
                  onChange={(e) => setDepositDesc(e.target.value)}
                />
              </div>

              <div className="modal-actions">
                <button type="button" className="btn-cancel" onClick={() => setShowDepositModal(false)}>Cancelar</button>
                <button type="submit" className="primary" style={{ background: '#10b981', borderColor: '#10b981' }}>Confirmar Depósito</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL APERTURA NUEVA CUENTA */}
      {showCreateAccountModal && (
        <div className="modal-backdrop">
          <div className="modal-content">
            <div className="modal-header">
              <h3><Landmark size={20} /> Apertura de Nueva Cuenta Bancaria</h3>
              <button className="btn-close" onClick={() => setShowCreateAccountModal(false)}><X size={18} /></button>
            </div>
            <form onSubmit={handleCreateAccountSubmit}>
              <div className="form-group">
                <label>Tipo de Cuenta</label>
                <select value={newAccTipo} onChange={(e) => setNewAccTipo(e.target.value)}>
                  <option value="AHORROS">Cuenta de Ahorros</option>
                  <option value="CORRIENTE">Cuenta Corriente</option>
                </select>
              </div>

              <p>Tu cuenta se abrirá con un saldo de $0,00.</p>

              <div className="modal-actions">
                <button type="button" className="btn-cancel" onClick={() => setShowCreateAccountModal(false)}>Cancelar</button>
                <button type="submit" className="primary">Abrir Cuenta</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL SOLICITUD PRÉSTAMO (CU-03, RF-14) */}
      {showLoanModal && (
        <div className="modal-backdrop">
          <div className="modal-content">
            <div className="modal-header">
              <h3><HandCoins size={20} /> Solicitud de Préstamo Simulado</h3>
              <button className="btn-close" onClick={() => setShowLoanModal(false)}><X size={18} /></button>
            </div>
            <form onSubmit={handleLoanSubmit}>
              <div className="form-group">
                <label>Monto Solicitado ($ USD)</label>
                <input
                  type="number"
                  step="50"
                  min="100"
                  placeholder="Ej. 1500.00"
                  value={loanMonto}
                  onChange={(e) => setLoanMonto(e.target.value)}
                  required
                />
              </div>

              <div className="form-group">
                <label>Plazo en Meses</label>
                <select value={loanPlazo} onChange={(e) => setLoanPlazo(e.target.value)}>
                  <option value="6">6 meses</option>
                  <option value="12">12 meses (1 año)</option>
                  <option value="24">24 meses (2 años)</option>
                  <option value="36">36 meses (3 años)</option>
                </select>
              </div>

              <div className="loan-preview">
                <small>Tasa referencial anual: <strong>14.5%</strong> (Simulación académica)</small>
              </div>

              <div className="modal-actions">
                <button type="button" className="btn-cancel" onClick={() => setShowLoanModal(false)}>Cancelar</button>
                <button type="submit" className="primary">Enviar Solicitud</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {toast && <div className="toast">{toast}</div>}
    </div>
  )
}

