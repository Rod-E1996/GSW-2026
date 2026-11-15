import { useEffect, useState } from 'react'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'

const ESTADOS = { pendiente: 'Pendiente', aprobada: 'Aprobada', rechazada: 'Rechazada' }
const COLOR = { pendiente: 'badge--aviso', aprobada: 'badge--ok', rechazada: 'badge--danger' }

// Moderacion de las valoraciones de los huespedes: aprobar (se publica en el portal),
// rechazar y responder.
export default function ValoracionesList() {
  const { can } = useAuth()
  const [estado, setEstado] = useState('pendiente')
  const [valoraciones, setValoraciones] = useState(null)
  const [respuestas, setRespuestas] = useState({})
  const [error, setError] = useState(null)

  function cargar() {
    api.get('/admin/valoraciones', { params: estado ? { estado } : {} })
      .then((res) => setValoraciones(res.data.data))
      .catch(() => setError('No se pudieron cargar las valoraciones.'))
  }

  useEffect(cargar, [estado])

  async function moderar(v, nuevoEstado) {
    setError(null)
    try {
      await api.put(`/admin/valoraciones/${v.id}`, { estado: nuevoEstado, respuesta: respuestas[v.id] ?? v.respuesta ?? null })
      cargar()
    } catch (e) {
      setError(e.response?.data?.message || 'No se pudo guardar.')
    }
  }

  return (
    <div>
      <div className="page-head">
        <div>
          <h1 className="page-title">Valoraciones</h1>
          <p className="page-sub">Las aprobadas se publican en el portal con su respuesta.</p>
        </div>
      </div>

      <div className="filtros">
        <select value={estado} onChange={(e) => setEstado(e.target.value)} aria-label="Estado">
          <option value="">Todas</option>
          {Object.entries(ESTADOS).map(([valor, nombre]) => <option key={valor} value={valor}>{nombre}</option>)}
        </select>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}
      {valoraciones === null && <p className="estado">Cargando…</p>}
      {valoraciones?.length === 0 && <p className="estado estado--vacio">No hay valoraciones {estado ? ESTADOS[estado].toLowerCase() + 's' : ''}.</p>}

      <div className="valoraciones-lista">
        {valoraciones?.map((v) => (
          <article key={v.id} className="tarjeta-simple valoracion-item">
            <div className="valoracion-item__cabecera">
              <span className="valorar__estrellas" aria-label={`${v.puntuacion} de 5`}>{'★'.repeat(v.puntuacion)}{'☆'.repeat(5 - v.puntuacion)}</span>
              <span className={`badge ${COLOR[v.estado] || ''}`}>{ESTADOS[v.estado]}</span>
            </div>
            <p>{v.comentario || <em>Sin comentario</em>}</p>
            <p className="form-nota">{v.huesped} · reserva {v.reserva} · {new Date(v.fecha).toLocaleDateString('es-SV')}</p>
            {can('valoracion_moderar') && (
              <>
                <textarea
                  rows="2"
                  maxLength="1000"
                  placeholder="Respuesta del hotel (opcional)"
                  value={respuestas[v.id] ?? v.respuesta ?? ''}
                  onChange={(e) => setRespuestas({ ...respuestas, [v.id]: e.target.value })}
                />
                <div className="valoracion-item__acciones">
                  <button type="button" className="btn btn--sm" onClick={() => moderar(v, 'aprobada')}>Aprobar</button>
                  <button type="button" className="btn btn--ghost btn--sm" onClick={() => moderar(v, 'rechazada')}>Rechazar</button>
                </div>
              </>
            )}
          </article>
        ))}
      </div>
    </div>
  )
}
