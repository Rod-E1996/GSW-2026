import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../api'
import { fechaCorta, formatoUSD } from '../../utils'

export const COLOR_ESTADO = { 1: 'badge--aviso', 2: 'badge--ok', 3: 'badge--ok', 4: '', 5: 'badge--danger' }

export default function MisReservas() {
  const [reservas, setReservas] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    api
      .get('/mi-cuenta/reservas')
      .then((res) => setReservas(res.data.data))
      .catch(() => setError('No se pudieron cargar tus reservas.'))
  }, [])

  if (error) return <div className="alerta alerta--error">{error}</div>
  if (!reservas) return <p className="estado">Cargando…</p>

  if (reservas.length === 0) {
    return (
      <div className="tarjeta-simple">
        <p>Aún no tienes reservas.</p>
        <Link to="/" className="btn">Buscar disponibilidad</Link>
      </div>
    )
  }

  return (
    <div className="tabla-wrap">
      <table className="tabla">
        <thead>
          <tr>
            <th>Código</th>
            <th>Habitación</th>
            <th>Llegada</th>
            <th>Salida</th>
            <th>Total</th>
            <th>Estado</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {reservas.map((r) => (
            <tr key={r.codigo}>
              <td><strong>{r.codigo}</strong></td>
              <td>{r.tipo}{r.habitacion ? ` · ${r.habitacion}` : ''}</td>
              <td>{fechaCorta(r.fecha_llegada)}</td>
              <td>{fechaCorta(r.fecha_salida)}</td>
              <td>{formatoUSD(r.total)}</td>
              <td>
                <span className={`badge ${r.vencida ? '' : COLOR_ESTADO[r.estado] || ''}`}>{r.estado_nombre}</span>
              </td>
              <td className="tabla__acciones">
                <Link to={`/mi-cuenta/reservas/${r.codigo}`} className="btn btn--sm btn--ghost">Ver</Link>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
