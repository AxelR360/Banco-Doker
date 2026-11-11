import Keycloak from 'keycloak-js'

const isLocalhost = typeof window !== 'undefined' && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
const defaultUrl = isLocalhost ? 'http://localhost:8085' : null
const url = import.meta.env.VITE_KEYCLOAK_URL || defaultUrl

export const keycloak = url ? new Keycloak({ url, realm: 'banco-digital', clientId: 'banco-frontend' }) : null
let initialization

export function initKeycloak() {
  if (!keycloak) return Promise.reject(new Error('Keycloak no configurado'))
  initialization ??= keycloak.init({ pkceMethod: 'S256', checkLoginIframe: false })
  return initialization
}
