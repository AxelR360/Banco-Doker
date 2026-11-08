import React, { useState } from 'react'
import { createRoot } from 'react-dom/client'
import {
  ArrowDownLeft, ArrowLeftRight, ArrowUpRight, Bell, ChevronDown,
  ChevronRight, CircleHelp, CreditCard, HandCoins, Home, Landmark,
  Menu, Search, Settings, ShieldCheck, WalletCards, X
} from 'lucide-react'
import './styles.css'

const movements = [
  { name: 'Ana Rodríguez', note: 'Transferencia recibida', date: 'Hoy, 09:41', amount: '+ $450.00', type: 'in' },
  { name: 'Supermercado Central', note: 'Compra con tarjeta', date: 'Ayer, 18:22', amount: '- $86.50', type: 'out' },
  { name: 'Nómina · TechAndes', note: 'Depósito recibido', date: '03 Nov, 08:00', amount: '+ $1.850,00', type: 'in' },
  { name: 'Netflix', note: 'Suscripción mensual', date: '02 Nov, 12:15', amount: '- $18.99', type: 'out' },
]

function App() {
  const [active, setActive] = useState('Inicio')
  const [menuOpen, setMenuOpen] = useState(false)
  const [toast, setToast] = useState('')

  const notify = (message) => {
    setToast(message)
    window.setTimeout(() => setToast(''), 2400)
  }

  const nav = [
    { label: 'Inicio', icon: Home },
    { label: 'Mis cuentas', icon: WalletCards },
    { label: 'Transferencias', icon: ArrowLeftRight },
    { label: 'Tarjetas', icon: CreditCard },
    { label: 'Préstamos', icon: HandCoins },
  ]

  return (
    <div className="app-shell">
      <aside className={`sidebar ${menuOpen ? 'open' : ''}`}>
        <div className="brand"><span className="brand-mark"><Landmark size={22} /></span><span>Nexo<span>Bank</span></span></div>
        <nav>
          <p className="nav-label">Banco</p>
          {nav.map(({ label, icon: Icon }) => (
            <button key={label} className={active === label ? 'active' : ''} onClick={() => { setActive(label); setMenuOpen(false) }}>
              <Icon size={20} /> {label}
            </button>
          ))}
          <p className="nav-label secondary">General</p>
          <button><ShieldCheck size={20} /> Seguridad</button>
          <button><Settings size={20} /> Ajustes</button>
        </nav>
        <div className="support"><CircleHelp size={19} /><div><strong>¿Necesitas ayuda?</strong><span>Habla con nosotros</span></div></div>
      </aside>

      {menuOpen && <button className="backdrop" aria-label="Cerrar menú" onClick={() => setMenuOpen(false)} />}

      <main>
        <header>
          <button className="menu-button" onClick={() => setMenuOpen(true)}><Menu /></button>
          <div className="search"><Search size={18} /><input placeholder="Buscar movimientos, cuentas..." /></div>
          <div className="header-actions">
            <button className="icon-button" aria-label="Notificaciones"><Bell size={20} /><span /></button>
            <button className="profile"><span className="avatar">MG</span><div><strong>María García</strong><small>Cliente</small></div><ChevronDown size={16} /></button>
          </div>
        </header>

        <section className="content">
          <div className="welcome"><div><p>Buenos días, María</p><h1>Tu resumen financiero</h1></div><button className="primary" onClick={() => notify('Nueva solicitud iniciada')}><span>＋</span> Nueva operación</button></div>

          <div className="balance-grid">
            <article className="balance-card dark-card">
              <div className="card-top"><span>Saldo disponible</span><button aria-label="Más opciones">•••</button></div>
              <strong>$ 12,480.50</strong>
              <p>Cuenta de ahorro ···· 4582</p>
              <div className="mini-chart" aria-hidden="true"><i /><i /><i /><i /><i /><i /><i /><i /><i /><i /></div>
            </article>
            <article className="balance-card light-card">
              <div className="card-top"><span>Ingresos este mes</span><span className="trend">+12.5%</span></div>
              <strong>$ 4,320.00</strong>
              <div className="bar-chart" aria-hidden="true"><i /><i /><i /><i /><i /><i /><i /></div>
            </article>
            <article className="balance-card light-card">
              <div className="card-top"><span>Gastos este mes</span><span className="trend down">-3.8%</span></div>
              <strong>$ 1,245.80</strong>
              <div className="bar-chart expenses" aria-hidden="true"><i /><i /><i /><i /><i /><i /><i /></div>
            </article>
          </div>

          <div className="quick-section"><div className="section-title"><h2>Acciones rápidas</h2></div><div className="quick-grid">
            <button onClick={() => notify('Transferencia abierta')}><span className="quick-icon purple"><ArrowUpRight /></span><div><strong>Transferir</strong><small>Enviar dinero</small></div><ChevronRight /></button>
            <button onClick={() => notify('Depósito iniciado')}><span className="quick-icon green"><ArrowDownLeft /></span><div><strong>Depositar</strong><small>Añadir fondos</small></div><ChevronRight /></button>
            <button onClick={() => notify('Solicitud de préstamo abierta')}><span className="quick-icon orange"><HandCoins /></span><div><strong>Solicitar préstamo</strong><small>Simulación rápida</small></div><ChevronRight /></button>
          </div></div>

          <div className="bottom-grid">
            <article className="panel movements">
              <div className="panel-heading"><div><h2>Movimientos recientes</h2><p>Actividad de tus cuentas</p></div><button onClick={() => notify('Mostrando todos los movimientos')}>Ver todos <ChevronRight size={16} /></button></div>
              <div className="movement-list">
                {movements.map((item) => <div className="movement" key={item.name}><span className={`movement-icon ${item.type}`}>{item.type === 'in' ? <ArrowDownLeft /> : <ArrowUpRight />}</span><div><strong>{item.name}</strong><small>{item.note}</small></div><time>{item.date}</time><b className={item.type === 'in' ? 'positive' : ''}>{item.amount}</b></div>)}
              </div>
            </article>

            <article className="panel card-panel">
              <div className="panel-heading"><div><h2>Mis tarjetas</h2><p>2 tarjetas activas</p></div><button aria-label="Más opciones">•••</button></div>
              <div className="bank-card"><div><span>NEXO BANK</span><span className="contactless">)))</span></div><div className="chip" /><strong>••••  &nbsp; ••••  &nbsp; ••••  &nbsp; 4582</strong><footer><span><small>TITULAR</small>MARÍA GARCÍA</span><span><small>VENCE</small>08/29</span></footer></div>
              <button className="outline-button" onClick={() => notify('Gestor de tarjetas abierto')}>Administrar tarjetas</button>
            </article>
          </div>
        </section>
      </main>
      {toast && <div className="toast">{toast}</div>}
    </div>
  )
}

createRoot(document.getElementById('root')).render(<App />)
