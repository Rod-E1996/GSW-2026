import { useEffect, useState } from 'react'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import { Avatar, FilaVacia, FilasCargando, Indicador } from '../../../components/ui'
import { Dispositivo, Paginacion, fechaHora, haceCuanto } from '../accesos/comun'
import '../../../styles/admin-accesos.css'

export default function SesionesList() {
  const { can } = useAuth()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [stats, setStats] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [error, setError] = useState(null)
  const [aviso, setAviso] = useState(null)

  function cargar() {
    setCargando(true)
    api
      .get('/admin/sesiones', { params: { search: search || undefined, page } })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
        setStats(res.data.stats)
      })
      .catch(() => setError('No se pudieron cargar las sesiones.'))
      .finally(() => setCargando(false))
  }

  useEffect(() => {
    cargar()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page])

  function buscar(e) {
    e.preventDefault()
    setPage(1)
    cargar()
  }

  async function cerrar(s) {
    if (s.es_actual) return
    if (!window.confirm(`¿Cerrar la sesión de ${s.usuario || 'este usuario'}? Deberá iniciar sesión de nuevo en ese dispositivo.`)) return
    setError(null)
    setAviso(null)
    try {
      await api.delete(`/admin/sesiones/${s.id}`)
      setAviso('Sesión cerrada con éxito.')
      cargar()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo cerrar la sesión.')
    }
  }

  return (
    <div>
      <div className="page-head">
        <div>
          <h1 className="page-title">Sesiones activas</h1>
          <p className="page-sub">Quién tiene la sesión abierta ahora y desde qué dispositivo. Cerrar una sesión obliga a volver a entrar.</p>
        </div>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

      {stats && (
        <div className="indicadores">
          <Indicador icono="sesiones" etiqueta="Sesiones abiertas" valor={stats.total_activas} />
          <Indicador icono="usuarios" tono="azul" etiqueta="Usuarios conectados" valor={stats.usuarios_conectados} />
          <Indicador
            icono="reloj"
            tono="ok"
            etiqueta="Promedio por usuario"
            valor={stats.usuarios_conectados ? (stats.total_activas / stats.usuarios_conectados).toFixed(1) : '0'}
            nota="sesiones abiertas"
          />
        </div>
      )}

      <form className="filtros" onSubmit={buscar}>
        <input
          className="filtros__busqueda"
          placeholder="Buscar por nombre o correo del usuario…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          aria-label="Buscar sesión"
        />
        <button className="btn btn--ghost">Buscar</button>
      </form>

      <div className="tabla-wrap">
        <table className="tabla">
          <thead>
            <tr>
              <th>Usuario</th>
              <th>Dispositivo</th>
              <th>IP</th>
              <th>Inicio</th>
              <th>Última actividad</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {cargando && <FilasCargando columnas={6} />}
            {!cargando && items.length === 0 && (
              <FilaVacia
                columnas={6}
                icono="sesiones"
                titulo={search ? 'Ninguna sesión coincide' : 'No hay sesiones abiertas'}
                texto={search ? 'Prueba con otro nombre o correo.' : 'Cuando alguien inicie sesión aparecerá aquí.'}
              />
            )}
            {!cargando &&
              items.map((s) => (
                <tr key={s.id} className={s.es_actual ? 'ac-fila-actual' : ''}>
                  <td>
                    <div className="celda-entidad">
                      <Avatar nombre={s.usuario} size="sm" />
                      <div><strong>{s.usuario || '—'}</strong></div>
                    </div>
                  </td>
                  <td><Dispositivo s={s} /></td>
                  <td className="ac-mono">{s.ip_address}</td>
                  <td>{fechaHora(s.inicio)}</td>
                  <td title={fechaHora(s.ultima_actividad)}>{haceCuanto(s.ultima_actividad)}</td>
                  <td className="tabla__acciones">
                    {s.es_actual ? (
                      <span className="badge badge--info">Esta sesión</span>
                    ) : (
                      can('session_cerrar') && (
                        <button className="btn btn--sm btn--danger" onClick={() => cerrar(s)}>Cerrar</button>
                      )
                    )}
                  </td>
                </tr>
              ))}
          </tbody>
        </table>
      </div>

      <Paginacion meta={meta} page={page} setPage={setPage} nombre="sesiones" />
    </div>
  )
}
