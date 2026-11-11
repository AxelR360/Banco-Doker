import { keycloak, initKeycloak } from './keycloak'
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

  useEffect(() => {
    if (API_URL) fetch(API_URL + '/api/v1/auth/me').catch(() => {})
    if (keycloak && import.meta.env.VITE_KEYCLOAK_URL) {
      const discovery = import.meta.env.VITE_KEYCLOAK_URL.replace(/\/$/, '') +
        '/realms/banco-digital/.well-known/openid-configuration'
      fetch(discovery, { mode: 'no-cors', credentials: 'omit' }).catch(() => {})
    }
  }, [])

  const [ssoError, setSsoError] = useState(false)
  useEffect(() => {
    if (!keycloak) return
    initKeycloak().then(async authenticated => {
      if (!authenticated) return
      const response = await fetch(API_URL + '/api/v1/auth/me', {
        headers: { Authorization: 'Bearer ' + keycloak.token },
      })
      const result = await response.json()
      const user = result.data
      if (!response.ok || !user?.id_usuario) throw new Error('SSO rejected')
      user.nombre = [user.nombres, user.apellidos].filter(Boolean).join(' ') || user.username
      localStorage.setItem('nexo_token', keycloak.token)
      localStorage.setItem('nexo_user', JSON.stringify(user))
      localStorage.setItem('nexo_sso', '1')
      setCurrentUser(user)
    }).catch(() => setSsoError(true))
  }, [])

  const handleLoginSuccess = (user) => {
    localStorage.removeItem('nexo_sso')
    setCurrentUser(user)
  }

  const handleLogout = () => {
    localStorage.removeItem('nexo_token')
    localStorage.removeItem('nexo_user')
    setCurrentUser(null)

    const usedSso = localStorage.getItem('nexo_sso')
    localStorage.removeItem('nexo_sso')
    if (usedSso && keycloak) {
      initKeycloak().then(() => keycloak.logout({ redirectUri: window.location.origin + '/' }))
        .catch(() => setSsoError(true))
    }
  }

  if (!currentUser) {
    return <LoginView onLoginSuccess={handleLoginSuccess} ssoError={ssoError} />
  }

  return <AuthenticatedApp user={currentUser} onLogout={handleLogout} />
}

function AuthenticatedApp({ user, onLogout }) {
  const controller = useDashboardController(user, onLogout)
  return <DashboardView controller={controller} />
}



