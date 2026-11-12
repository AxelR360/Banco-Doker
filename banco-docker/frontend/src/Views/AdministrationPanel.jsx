import React, { useEffect, useState } from 'react'
import API_URL from '../api'

export default function AdministrationPanel({ user }) {
  const [clients, setClients] = useState([])
  const [loans, setLoans] = useState([])
  const [reports, setReports] = useState(null)
  const [users, setUsers] = useState([])
  const [message, setMessage] = useState('')
  async function request(path, method = 'GET', body) {
    const response = await fetch(API_URL + '/api/v1' + path, {
      method,
      headers: { Authorization: 'Bearer ' + localStorage.getItem('nexo_token'), 'Content-Type': 'application/json' },
      body: body ? JSON.stringify(body) : undefined,
    })
    const result = await response.json()
    if (!response.ok) throw new Error(result.message || 'No se pudo completar la operación')
    return result.data
  }
  async function reload() {
    try {
      setClients(await request('/clientes'))
      setLoans(await request('/prestamos'))
      if (user.rol === 'ADMIN') {
        setReports(await request('/reportes'))
        setUsers(await request('/usuarios'))
      }
    } catch (error) { setMessage(error.message) }
  }
  useEffect(() => { reload() }, [])
  async function saveClient(event, id) {
    event.preventDefault()
    try {
      await request('/clientes/' + id, 'PATCH', Object.fromEntries(new FormData(event.currentTarget)))
      setMessage('Datos del cliente actualizados')
      await reload()
    } catch (error) { setMessage(error.message) }
  }
  async function decide(id, estado) {
    try {
      await request('/prestamos/' + id + '/estado', 'PATCH', { estado })
      setMessage(estado === 'APROBADO' ? 'Préstamo aprobado y abonado' : 'Solicitud actualizada')
      await reload()
    } catch (error) { setMessage(error.message) }
  }
  async function linkUser(event) {
    event.preventDefault()
    const form = event.currentTarget
    try {
      await request('/usuarios', 'POST', Object.fromEntries(new FormData(form)))
      form.reset()
      setMessage('Usuario vinculado')
      await reload()
    } catch (error) { setMessage(error.message) }
  }
  return <section className="admin-panel">
    <h2>Administración</h2>
    {message && <p role="status">{message}</p>}
    {user.rol === 'ADMIN' && <>
      <h3>Usuarios de Keycloak</h3>
      <p>Vincula el ID de un usuario existente en Keycloak con su perfil del banco.</p>
      <form onSubmit={linkUser}>
        <label>ID de Keycloak<input name="keycloak_id" required minLength={36} maxLength={36} /></label>
        <label>Usuario<input name="username" required maxLength={60} /></label>
        <label>Rol<select name="rol"><option>CLIENTE</option><option>ASESOR</option><option>ADMIN</option></select></label>
        <label>ID del cliente<input name="id_cliente" type="number" min="1" /></label>
        <button type="submit">Vincular usuario</button>
      </form>
      {users.map(item => <p key={item.id_usuario}>{item.username} · {item.rol} · {item.estado}</p>)}
    </>}
    <h3>Solicitudes de préstamos</h3>
    {loans.map(loan => <article key={loan.id_prestamo}>
      <strong>{loan.nombres} {loan.apellidos}</strong>
      <p>$ {Number(loan.monto).toFixed(2)} · {loan.plazo_meses} meses · {loan.estado}</p>
      {['SOLICITADO', 'EN_REVISION'].includes(loan.estado) && <div className="admin-actions">
        <button onClick={() => decide(loan.id_prestamo, 'EN_REVISION')}>En revisión</button>
        <button onClick={() => decide(loan.id_prestamo, 'APROBADO')}>Aprobar y abonar</button>
        <button onClick={() => decide(loan.id_prestamo, 'RECHAZADO')}>Rechazar</button>
      </div>}
    </article>)}
    <h3>Datos de clientes</h3>
    {clients.map(client => <form key={client.id_cliente} onSubmit={event => saveClient(event, client.id_cliente)}>
      <strong>{client.nombres} {client.apellidos}</strong>
      <label>Correo<input name="correo" type="email" defaultValue={client.correo} required maxLength={120} /></label>
      <label>Teléfono<input name="telefono" defaultValue={client.telefono || ''} maxLength={15} /></label>
      <label>Dirección<input name="direccion" defaultValue={client.direccion || ''} maxLength={150} /></label>
      <button type="submit">Guardar datos</button>
    </form>)}
    {reports && <><h3>Reportes</h3>
      {Object.entries(reports).map(([name, rows]) => <div key={name}><h4>{name}</h4>
        {rows.length > 0 && <table><thead><tr>{Object.keys(rows[0]).map(key => <th key={key}>{key}</th>)}</tr></thead>
          <tbody>{rows.map((row, index) => <tr key={index}>{Object.values(row).map((value, column) => <td key={column}>{value}</td>)}</tr>)}</tbody></table>}
      </div>)}
    </>}
  </section>
}
