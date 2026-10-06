import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'

function fecha(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('es-SV')
}

function Json({ data }) {
  if (data === null || data === undefined) return <p className="estado">—</p>
  return <pre className="json-box">{JSON.stringify(data, null, 2)}</pre>
}

export default function AuditoriaDetalle() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [reg, setReg] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    api
      .get(`/admin/auditoria/${id}`)
      .then((res) => setReg(res.data.data))
      .catch(() => setError('No se pudo cargar el registro.'))
      .finally(() => setCargando(false))
  }, [id])

  if (cargando) return <p className="estado">Cargando…</p>
  if (error) return <div className="alerta alerta--error">{error}</div>

  return (
    <div>
      <div className="page-head">
        <h1 className="page-title">Detalle de auditoría</h1>
        <button className="btn btn--ghost" onClick={() => navigate('/panel/auditoria')}>
          ← Volver
        </button>
      </div>

      <div className="cards-info">
        <div className="info-card">
          <span className="info-card__label">Usuario</span>
          <span className="info-card__value info-card__value--sm">{reg.usuario || '—'}</span>
        </div>
        <div className="info-card">
          <span className="info-card__label">Acción</span>
          <span className="info-card__value info-card__value--sm">{reg.accion || '—'}</span>
        </div>
        <div className="info-card">
          <span className="info-card__label">Entidad</span>
          <span className="info-card__value info-card__value--sm">{reg.entidad || '—'}</span>
        </div>
        <div className="info-card">
          <span className="info-card__label">Fecha</span>
          <span className="info-card__value info-card__value--sm">{fecha(reg.fecha)}</span>
        </div>
      </div>

      <div className="comparar">
        <div className="comparar__col">
          <h2 className="seccion-title">Antes</h2>
          <Json data={reg.antes} />
        </div>
        <div className="comparar__col">
          <h2 className="seccion-title">Después</h2>
          <Json data={reg.despues} />
        </div>
      </div>
    </div>
  )
}
