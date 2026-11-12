import { keycloak, initKeycloak } from '../keycloak'
import API_URL from '../api'
import React, { useState } from 'react'
import { Landmark, Lock, User, ArrowRight, ShieldCheck, KeyRound, ExternalLink, Mail, Phone, IdCard, UserPlus } from 'lucide-react'

export default function LoginView({ onLoginSuccess, ssoError }) {
  const [tab, setTab] = useState('login') // 'login' | 'register'

  // Login state
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')

  // Register state
  const [regNombres, setRegNombres] = useState('')
  const [regApellidos, setRegApellidos] = useState('')
  const [regIdentificacion, setRegIdentificacion] = useState('')
  const [regCorreo, setRegCorreo] = useState('')
  const [regTelefono, setRegTelefono] = useState('')
  const [regUsername, setRegUsername] = useState('')
  const [regPassword, setRegPassword] = useState('')

  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [successMsg, setSuccessMsg] = useState('')

  const handleLogin = async (e, u = username, p = password) => {
    if (e) e.preventDefault()
    setError('')
    setSuccessMsg('')
    setLoading(true)

    try {
      const res = await fetch(`${API_URL}/api/v1/auth/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: u, password: p }),
      })

      const text = await res.text()
      let data
      try {
        data = JSON.parse(text)
      } catch {
        throw new Error('El backend PHP aún no responde JSON. Asegúrate de que el backend esté en ejecución.')
      }

      if (!res.ok || !data.success) {
        throw new Error(data.message || 'Error al iniciar sesión')
      }

      localStorage.setItem('nexo_token', data.data.token)
      localStorage.setItem('nexo_user', JSON.stringify(data.data.user))

      if (onLoginSuccess) {
        onLoginSuccess(data.data.user, data.data.token)
      }
    } catch (err) {
      setError(err.message || 'Error de conexión con el servidor')
    } finally {
      setLoading(false)
    }
  }

  const handleRegister = async (e) => {
    e.preventDefault()
    setError('')
    setSuccessMsg('')
    setLoading(true)

    try {
      const res = await fetch(`${API_URL}/api/v1/auth/registro`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          nombres: regNombres,
          apellidos: regApellidos,
          identificacion: regIdentificacion,
          correo: regCorreo,
          telefono: regTelefono,
          username: regUsername,
          password: regPassword
        }),
      })

      const text = await res.text()
      let data
      try {
        data = JSON.parse(text)
      } catch {
        throw new Error('El backend PHP no devolvió una respuesta válida.')
      }

      if (!res.ok || !data.success) {
        throw new Error(data.message || 'Error al registrar el usuario')
      }

      localStorage.setItem('nexo_token', data.data.token)
      localStorage.setItem('nexo_user', JSON.stringify(data.data.user))

      setSuccessMsg('¡Usuario y cuenta bancaria creados con éxito! Ingresando...')
      setTimeout(() => {
        if (onLoginSuccess) {
          onLoginSuccess(data.data.user, data.data.token)
        }
      }, 1000)
    } catch (err) {
      setError(err.message || 'Error al procesar el registro')
    } finally {
      setLoading(false)
    }
  }

  const loginWithKeycloakSSO = async () => {
    try {
      await initKeycloak()
      await keycloak.login({ redirectUri: window.location.origin + '/' })
    } catch {
      setError('No se pudo conectar con el servidor de autenticación Keycloak.')
    }
  }

  const quickLogin = (user) => {
    setUsername(user)
    setPassword('12345')
    handleLogin(null, user, '12345')
  }

  return (
    <div className="login-page">
      <div className="login-card">
        <div className="login-header">
          <div className="brand-mark large">
            <Landmark size={26} />
          </div>
          <h1>Nexo<span>Bank</span></h1>
          <p>Sistema Bancario Digital · UTLVTE Grupo #4</p>
        </div>

        {/* Pestañas Iniciar Sesión / Crear Usuario */}
        <div className="auth-tabs">
          <button
            type="button"
            className={`auth-tab ${tab === 'login' ? 'active' : ''}`}
            onClick={() => { setTab('login'); setError(''); setSuccessMsg(''); }}
          >
            Iniciar Sesión
          </button>
          <button
            type="button"
            className={`auth-tab ${tab === 'register' ? 'active' : ''}`}
            onClick={() => { setTab('register'); setError(''); setSuccessMsg(''); }}
          >
            <UserPlus size={16} /> Crear Usuario
          </button>
        </div>

        {error && <div className="login-alert error">{error}</div>}
        {successMsg && <div className="login-alert success">{successMsg}</div>}

        {tab === 'login' ? (
          <>
            {ssoError && <p role="alert" className="login-alert error">No se pudo validar el acceso con Keycloak.</p>}
            {keycloak && (
            <button
              type="button"
              onClick={loginWithKeycloakSSO}
              className="btn-keycloak-sso"
              title="Iniciar sesión mediante Keycloak IAM / OIDC"
            >
              <KeyRound size={18} />
              <span>Iniciar con <strong>Keycloak SSO</strong></span>
              <ExternalLink size={15} />
            </button>
            )}

            <div className="divider-text">
              <span>o con credenciales locales</span>
            </div>

            <form onSubmit={handleLogin} className="login-form">
              <div className="form-group">
                <label>Usuario</label>
                <div className="input-with-icon">
                  <User size={18} />
                  <input
                    type="text"
                    placeholder="Ej. admin, asesor1, axel.rosero"
                    value={username}
                    onChange={(e) => setUsername(e.target.value)}
                    required
                  />
                </div>
              </div>

              <div className="form-group">
                <label>Contraseña</label>
                <div className="input-with-icon">
                  <Lock size={18} />
                  <input
                    type="password"
                    placeholder="Contraseña (predeterminada: 12345)"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                  />
                </div>
              </div>

              <button type="submit" className="login-submit" disabled={loading}>
                {loading ? 'Verificando...' : 'Ingresar a la plataforma'}
                {!loading && <ArrowRight size={18} />}
              </button>
            </form>

            <div className="demo-accounts">
              <span className="demo-title">
                <ShieldCheck size={14} /> Acceso rápido (Demostración de Roles):
              </span>
              <div className="demo-buttons">
                <button type="button" onClick={() => quickLogin('admin')} className="badge-btn admin">
                  👔 Administrador
                </button>
                <button type="button" onClick={() => quickLogin('asesor1')} className="badge-btn asesor">
                  💼 Asesor 1
                </button>
                <button type="button" onClick={() => quickLogin('axel.rosero')} className="badge-btn cliente">
                  👤 Axel (Cliente)
                </button>
                <button type="button" onClick={() => quickLogin('melany.zambrano')} className="badge-btn cliente">
                  👤 Melany (Cliente)
                </button>
              </div>
            </div>
          </>
        ) : (
          /* FORMULARIO DE CREAR USUARIO / REGISTRO */
          <form onSubmit={handleRegister} className="login-form register-form">
            <div className="form-row">
              <div className="form-group">
                <label>Nombres</label>
                <input
                  type="text"
                  placeholder="Ej. Juan Carlos"
                  value={regNombres}
                  onChange={(e) => setRegNombres(e.target.value)}
                  required
                />
              </div>
              <div className="form-group">
                <label>Apellidos</label>
                <input
                  type="text"
                  placeholder="Ej. Perez Gomez"
                  value={regApellidos}
                  onChange={(e) => setRegApellidos(e.target.value)}
                  required
                />
              </div>
            </div>

            <div className="form-row">
              <div className="form-group">
                <label>Cédula / Identificación</label>
                <input
                  type="text"
                  placeholder="Ej. 0801234567"
                  maxLength={13}
                  value={regIdentificacion}
                  onChange={(e) => setRegIdentificacion(e.target.value)}
                  required
                />
              </div>
              <div className="form-group">
                <label>Teléfono</label>
                <input
                  type="text"
                  placeholder="0991234567"
                  value={regTelefono}
                  onChange={(e) => setRegTelefono(e.target.value)}
                />
              </div>
            </div>

            <div className="form-group">
              <label>Correo Electrónico</label>
              <input
                type="email"
                placeholder="juan.perez@ejemplo.test"
                value={regCorreo}
                onChange={(e) => setRegCorreo(e.target.value)}
                required
              />
            </div>

            <div className="form-row">
              <div className="form-group">
                <label>Usuario para Ingresar</label>
                <input
                  type="text"
                  placeholder="juan.perez"
                  value={regUsername}
                  onChange={(e) => setRegUsername(e.target.value)}
                  required
                />
              </div>
              <div className="form-group">
                <label>Contraseña</label>
                <input
                  type="password"
                  placeholder="Clave (ej. 12345)"
                  value={regPassword}
                  onChange={(e) => setRegPassword(e.target.value)}
                  required
                />
              </div>
            </div>

            <button type="submit" className="login-submit" disabled={loading}>
              <UserPlus size={18} />
              {loading ? 'Creando cuenta...' : 'Crear Usuario y Abrir Cuenta'}
            </button>
          </form>
        )}

        <div className="login-footer">
          <small>PHP · JavaScript / React · MySQL · Nginx · Keycloak</small>
        </div>
      </div>
    </div>
  )
}
