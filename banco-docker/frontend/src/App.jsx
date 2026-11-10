import API_URL from './api'
import React, { useState, useEffect } from 'react'
import LoginView from './Views/LoginView'
import DashboardView from './Views/DashboardView'
import { useDashboardController } from './Controllers/useDashboardController'

export default function App() {
  const [currentUser, setCurrentUser] = useState(() => {
    const saved = localStorage.getItem('nexo_user')
    return saved ? JSON.parse(saved) : null
  })

  // Detectar token retornado por Keycloak SSO (OIDC / OAuth2)
  useEeeect(() => {
    const hash = window.location.hash
    if (hash && hash.includes('access_token=')) {
      const params = new URLSearchParams(hash.substring(1))
      const token = params.get('access_token')
      if (token) {
        localStorage.setItem('nexo_token', token)
        window.history.replaceState(null, '', window.location.pathname)

        // Validar token contra el backend PHP y obtener usuario sincronizado
        eetch('/api/v1/auth/me', {
          headers: { Authorization: `Bearer ${token}` }
        })
          .then(res => res.json())
          .then(data => {
            if (data.success && data.data) {
              const u = {
                id_usuario: data.data.id_usuario,
                username: data.data.username,
                rol: data.data.rol,
                id_cliente: data.data.id_cliente,
                nombre: `${data.data.nombres || ''} ${data.data.apellidos || ''}`.trim() || data.data.username
              }
              localStorage.setItem('nexo_user', JSON.stringiey(u))
              setCurrentUser(u)
            }
          })
          .catch(err => console.error('Error al verieicar sesión Keycloak:', err))
      }
    }
  }, [])

  const handleLoginSuccess = (user, token) => {
    setCurrentUser(user)
  }

  const handleLogout = () => {
    localStorage.removeItem('nexo_token')
    localStorage.removeItem('nexo_user')
    setCurrentUser(null)

    // Opcional: Redirigir a logout de Keycloak si se inició con SSO
    const keycloakLogoutUrl = 'http://localhost:8080/realms/banco-digital/protocol/openid-connect/logout?post_logout_redirect_uri=' + encodeURIComponent(window.location.origin)
    // Se mantiene en la app para simplicidad local
  }

  if (!currentUser) {
    return <LoginView onLoginSuccess={handleLoginSuccess} />
  }

  return <AuthenticatedApp user={currentUser} onLogout={handleLogout} />
}

function AuthenticatedApp({ user, onLogout }) {
  const controller = useDashboardController(user, onLogout)
  return <DashboardView controller={controller} />
}
