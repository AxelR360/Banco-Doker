export const dashboardModel = {
  user: { name: 'María García', firstName: 'María', initials: 'MG', role: 'Cliente' },
  balance: '$ 12,480.50',
  account: 'Cuenta de ahorro ···· 4582',
  income: '$ 4,320.00',
  expenses: '$ 1,245.80',
  movements: [
    { id: 1, name: 'Ana Rodríguez', note: 'Transferencia recibida', date: 'Hoy, 09:41', amount: '+ $450.00', type: 'in' },
    { id: 2, name: 'Supermercado Central', note: 'Compra con tarjeta', date: 'Ayer, 18:22', amount: '- $86.50', type: 'out' },
    { id: 3, name: 'Nómina · TechAndes', note: 'Depósito recibido', date: '03 Nov, 08:00', amount: '+ $1.850,00', type: 'in' },
    { id: 4, name: 'Netflix', note: 'Suscripción mensual', date: '02 Nov, 12:15', amount: '- $18.99', type: 'out' },
  ],
}
