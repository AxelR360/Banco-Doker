import Keycloak from 'keycloak-js'

const url = import.meta.env.VITE_KEYCLOAK_URL
export const keycloak = url ? new Keycloak({ url, realm: 'banco-digital', clientId: 'banco-frontend' }) : null
let initialization

export function initKeycloak() {
  initialization ??= keycloak.init({ pkceMethod: 'S256', checkLoginIframe: false })
  return initialization
}
