import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'

function fecha(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('es-SV')
}

function Json({ data }) {
  if (data === null || data === undefined) return <p className="estado">—</p>
  if (typeof data === 'string') return <pre className="json-box">{data}</pre>
  return <pre className="json-box">{JSON.stringify(data, null, 2)}</pre>
}

export default function ErrorDetalle() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { can } = useAuth()
  const [reg, setReg] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(null)
  const [guardando, setGuardando] = useState(false)

  useEffect(() => {
    api
      .get(`/admin/error-logs/${id}`)
      .then((res) => setReg(res.data.data))
      .catch(() => setError('No se pudo cargar el registro.'))
      .finally(() => setCargando(false))
  }, [id])

  async function resolver() {
    if (!window.confirm('¿Marcar este error como resuelto?')) return
    setGuardando(true)
    try {
      const res = await api.put(`/admin/error-logs/${id}/estado`)
      setReg(res.data.data)
    } catch {
      setError('No se pudo actualizar el estado.')
    } finally {
      setGuardando(false)
    }
  }

  if (cargando) return <p className="estado">Cargando…</p>
  if (error) return <div className="alerta alerta--error">{error}</div>
  if (!reg) return null

  return (
    <div>
      <div className="page-head">
        <h1 className="page-title">Detalle del error</h1>
        <button className="btn btn--ghost" onClick={() => navigate('/panel/error-logs')}>
          ← Volver
        </button>
      </div>

      <div className="cards-info">
        <div className="info-card">
          <span className="info-card__label">Usuario</span>
          <span className="info-card__value info-card__value--sm">{reg.usuario || '(Sin usuario)'}</span>
        </div>
        <div className="info-card">
          <span className="info-card__label">Controller</span>
          <span className="info-card__value info-card__value--sm">{reg.controller || '—'}</span>
        </div>
        <div className="info-card">
          <span className="info-card__label">Fecha</span>
          <span className="info-card__value info-card__value--sm">{fecha(reg.fecha)}</span>
        </div>
        <div className="info-card">
          <span className="info-card__label">Estado</span>
          <span className="info-card__value info-card__value--sm">
            <span className={`badge ${reg.estado === 1 ? 'badge--ok' : 'badge--danger'}`}>
              {reg.estado === 1 ? 'Resuelto' : 'Sin resolver'}
            </span>
          </span>
        </div>
      </div>

      <h2 className="seccion-title">Mensaje</h2>
      <p className="mensaje-error">{reg.mensaje || '—'}</p>

      <h2 className="seccion-title">Parámetros</h2>
      <Json data={reg.parametros_json ?? reg.parametros ?? null} />

      {reg.estado === 0 && can('error_log_estado') && (
        <div className="form-acciones">
          <button className="btn" onClick={resolver} disabled={guardando}>
            {guardando ? 'Guardando…' : 'Marcar como resuelto'}
          </button>
        </div>
      )}
    </div>
  )
}
