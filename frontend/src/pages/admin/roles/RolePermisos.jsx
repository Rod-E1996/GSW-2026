import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'

export default function RolePermisos() {
  const { id } = useParams()
  const navigate = useNavigate()

  const [nombre, setNombre] = useState('')
  const [asignados, setAsignados] = useState([])
  const [disponibles, setDisponibles] = useState([])
  const [filtro, setFiltro] = useState('')
  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)
  const [error, setError] = useState(null)
  const [aviso, setAviso] = useState(null)

  function aplicar(data) {
    setNombre(data.name)
    setAsignados(data.permisos_asignados)
    setDisponibles(data.permisos_disponibles)
  }

  useEffect(() => {
    api
      .get(`/admin/roles/${id}`)
      .then((res) => aplicar(res.data.data))
      .catch(() => setError('No se pudo cargar el rol.'))
      .finally(() => setCargando(false))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  function asignar(p) {
    setDisponibles((xs) => xs.filter((x) => x !== p))
    setAsignados((xs) => [...xs, p].sort())
  }

  function quitar(p) {
    setAsignados((xs) => xs.filter((x) => x !== p))
    setDisponibles((xs) => [...xs, p].sort())
  }

  async function guardar() {
    setGuardando(true)
    setError(null)
    setAviso(null)
    try {
      const res = await api.put(`/admin/roles/${id}/permisos`, { permisos: asignados })
      setAviso(res.data.message)
      // Recargamos para reflejar cualquier permiso fijo reinsertado.
      const r = await api.get(`/admin/roles/${id}`)
      aplicar(r.data.data)
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudieron guardar los permisos.')
    } finally {
      setGuardando(false)
    }
  }

  const q = filtro.toLowerCase()
  const disp = disponibles.filter((p) => p.toLowerCase().includes(q))
  const asig = asignados.filter((p) => p.toLowerCase().includes(q))

  if (cargando) return <p className="estado">Cargando…</p>

  return (
    <div>
      <div className="page-head">
        <h1 className="page-title">Permisos de «{nombre}»</h1>
        <button className="btn btn--ghost" onClick={() => navigate('/panel/roles')}>
          ← Volver
        </button>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

      <input
        className="permisos-filtro"
        placeholder="Filtrar permisos…"
        value={filtro}
        onChange={(e) => setFiltro(e.target.value)}
      />

      <div className="permisos-grid">
        <div className="permisos-col">
          <h2 className="seccion-title">Disponibles ({disponibles.length})</h2>
          <ul className="permisos-lista">
            {disp.map((p) => (
              <li key={p}>
                <button type="button" className="permiso-item" onClick={() => asignar(p)}>
                  <span>{p}</span><span className="permiso-item__ico">＋</span>
                </button>
              </li>
            ))}
            {disp.length === 0 && <li className="estado">—</li>}
          </ul>
        </div>

        <div className="permisos-col">
          <h2 className="seccion-title">Asignados ({asignados.length})</h2>
          <ul className="permisos-lista">
            {asig.map((p) => (
              <li key={p}>
                <button type="button" className="permiso-item permiso-item--on" onClick={() => quitar(p)}>
                  <span>{p}</span><span className="permiso-item__ico">×</span>
                </button>
              </li>
            ))}
            {asig.length === 0 && <li className="estado">—</li>}
          </ul>
        </div>
      </div>

      <div className="form-acciones">
        <button className="btn" onClick={guardar} disabled={guardando}>
          {guardando ? 'Guardando…' : 'Guardar permisos'}
        </button>
      </div>
    </div>
  )
}
