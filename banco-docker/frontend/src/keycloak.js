import Keycloak from 'keycloak-js'

const isLocalhost = typeof window !== 'undefined' && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
const defaultUrl = isLocalhost ? 'http://' + window.location.hostname + ':8085' : null
const url = import.meta.env.VITE_KEYCLOAK_URL || defaultUrl

export const keycloak = url ? new Keycloak({ url, realm: 'banco-digital', clientId: 'banco-frontend' }) : null
let initialization = null

export function initKeycloak() {
  if (!keycloak) return Promise.reject(new Error('Keycloak no configurado'))
  if (!initialization) {
    const options = { pkceMethod: 'S256', checkLoginIframe: false }
    if (localStorage.getItem('nexo_sso') === '1') options.onLoad = 'check-sso'
    if (new URLSearchParams(window.location.search).get('sso') === '1') {
      options.onLoad = 'login-required'
      options.redirectUri = window.location.origin + '/'
    }
    initialization = keycloak.init(options)
  }
  return initialization
}
