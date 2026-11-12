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

  const [ssoError, setSsoError] = useState(false)
  const directSso = new URLSearchParams(window.location.search).get('sso') === '1'
  useEffect(() => {
    if (!keycloak) {
      if (directSso) setSsoError(true)
      return
    }
    let refreshTimer
    let cancelled = false
    const endSsoSession = () => {
      if (localStorage.getItem('nexo_sso') === '1') {
        localStorage.removeItem('nexo_sso')
        localStorage.removeItem('nexo_user')
        localStorage.removeItem('nexo_token')
        setCurrentUser(null)
      }
    }
    initKeycloak().then(async authenticated => {
      if (cancelled) return
      if (!authenticated) {
        endSsoSession()
        return
      }
      const response = await fetch(API_URL + '/api/v1/auth/me', {
        headers: { Authorization: 'Bearer ' + keycloak.token },
      })
      const result = await response.json()
      const user = result.data
      if (cancelled) return
      if (!response.ok || !user?.id_usuario) throw new Error('SSO rejected')
      user.nombre = [user.nombres, user.apellidos].filter(Boolean).join(' ') || user.username
      localStorage.setItem('nexo_token', keycloak.token)
      localStorage.setItem('nexo_user', JSON.stringify(user))
      localStorage.setItem('nexo_sso', '1')
      setCurrentUser(user)
      refreshTimer = window.setInterval(async () => {
        if (localStorage.getItem('nexo_sso') !== '1') {
          window.clearInterval(refreshTimer)
          return
        }
        try {
          await keycloak.updateToken(45)
          if (localStorage.getItem('nexo_sso') === '1') {
            localStorage.setItem('nexo_token', keycloak.token)
          }
        } catch {
          endSsoSession()
          setSsoError(true)
        }
      }, 15000)
    }).catch(() => {
      if (cancelled) return
      endSsoSession()
      setSsoError(true)
    })
    return () => {
      cancelled = true
      window.clearInterval(refreshTimer)
    }
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
      keycloak.logout({ redirectUri: window.location.origin + '/' })
        .catch(() => setSsoError(true))
    }
  }

  if (!currentUser) {
    if (directSso && !ssoError) {
      return <main className="login-page"><div className="login-card" role="status">Abriendo el inicio de sesión de Keycloak…</div></main>
    }
    return <LoginView onLoginSuccess={handleLoginSuccess} ssoError={ssoError} />
  }

  return <AuthenticatedApp user={currentUser} onLogout={handleLogout} />
}

function AuthenticatedApp({ user, onLogout }) {
  const controller = useDashboardController(user, onLogout)
  return <DashboardView controller={controller} />
}



