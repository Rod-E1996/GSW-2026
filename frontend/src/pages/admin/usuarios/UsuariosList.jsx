import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { Avatar, FilaVacia, FilasCargando } from '../../../components/ui'
import { Paginacion } from '../accesos/comun'
import '../../../styles/admin-accesos.css'

const PESTANAS = [
  { valor: '', nombre: 'Todos', clave: 'total' },
  { valor: '1', nombre: 'Activos', clave: 'activos' },
  { valor: '0', nombre: 'Inactivos', clave: 'inactivos' },
]

// Lista de usuarios con estado, roles, sesiones activas y acciones.
export default function UsuariosList() {
  const { can } = useAuth()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [resumen, setResumen] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [search, setSearch] = useState('')
  const [estado, setEstado] = useState('')
  const [page, setPage] = useState(1)
  const [error, setError] = useState(null)
  const [aviso, setAviso] = useState(null)

  function cargar() {
    setCargando(true)
    api
      .get('/admin/usuarios', { params: { search: search || undefined, estado: estado || undefined, page } })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
        setResumen(res.data.resumen || null)
      })
      .catch(() => setError('No se pudieron cargar los usuarios.'))
      .finally(() => setCargando(false))
  }

  useEffect(() => {
    cargar()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, estado])

  function buscar(e) {
    e.preventDefault()
    setPage(1)
    cargar()
  }

  function elegirEstado(valor) {
    setEstado(valor)
    setPage(1)
  }

  async function cambiarEstado(u) {
    const accion = u.estado === 1 ? 'desactivar' : 'activar'
    const detalle = u.estado === 1 ? ' No podrá iniciar sesión hasta que lo actives de nuevo.' : ''
    if (!window.confirm(`¿Seguro que deseas ${accion} a "${u.name}"?${detalle}`)) return
    setError(null)
    setAviso(null)
    try {
      await api.put(`/admin/usuarios/${u.id}/estado`)
      setAviso(`Usuario ${accion === 'activar' ? 'activado' : 'desactivado'} con éxito.`)
      cargar()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo cambiar el estado.')
    }
  }

  const filtrando = Boolean(search) || estado !== ''

  return (
    <div>
      <div className="page-head">
        <div>
          <h1 className="page-title">Usuarios</h1>
          <p className="page-sub">Cuentas del personal y de los huéspedes, con sus roles y sesiones abiertas.</p>
        </div>
        {can('usuario_create') && (
          <Link to="/panel/usuarios/nuevo" className="btn"><Icono nombre="mas" size={16} /> Nuevo usuario</Link>
        )}
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

      <div className="ac-barra">
        <div className="pestanas" role="tablist" aria-label="Estado">
          {PESTANAS.map((p) => (
            <button
              key={p.valor}
              type="button"
              role="tab"
              aria-selected={estado === p.valor}
              className={estado === p.valor ? 'is-activa' : ''}
              onClick={() => elegirEstado(p.valor)}
            >
              {p.nombre}
              {resumen && <span className="pestanas__conteo">{resumen[p.clave]}</span>}
            </button>
          ))}
        </div>

        <form className="ac-buscar" onSubmit={buscar}>
          <input
            className="filtros__busqueda"
            placeholder="Buscar por nombre o correo…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            aria-label="Buscar usuario"
          />
          <button className="btn btn--ghost">Buscar</button>
        </form>
      </div>

      <div className="tabla-wrap">
        <table className="tabla">
          <thead>
            <tr>
              <th>Usuario</th>
              <th>Roles</th>
              <th>Estado</th>
              <th>Sesiones abiertas</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {cargando && <FilasCargando columnas={5} />}
            {!cargando && items.length === 0 && (
              <FilaVacia
                columnas={5}
                icono="usuarios"
                titulo={filtrando ? 'Ningún usuario coincide' : 'Aún no hay usuarios'}
                texto={filtrando ? 'Prueba con otro nombre, otro correo u otra pestaña.' : 'Crea la primera cuenta del personal.'}
                accion={!filtrando && can('usuario_create') && <Link to="/panel/usuarios/nuevo" className="btn">Nuevo usuario</Link>}
              />
            )}
            {!cargando &&
              items.map((u) => (
                <tr key={u.id} className={u.estado === 1 ? '' : 'ac-fila-inactiva'}>
                  <td>
                    <div className="celda-entidad">
                      <Avatar nombre={u.name} />
                      <div>
                        <strong>{u.name}</strong>
                        <small>{u.email}</small>
                      </div>
                    </div>
                  </td>
                  <td>
                    {u.roles.length ? (
                      <div className="ac-chips">
                        {u.roles.map((r) => <span key={r} className="ac-chip">{r}</span>)}
                      </div>
                    ) : (
                      <span className="form-nota">Sin rol</span>
                    )}
                  </td>
                  <td>
                    <span className={`badge ${u.estado === 1 ? 'badge--ok' : 'badge--danger'}`}>
                      {u.estado === 1 ? 'Activo' : 'Inactivo'}
                    </span>
                  </td>
                  <td>
                    {can('usuario_sessiones') ? (
                      <Link to={`/panel/usuarios/${u.id}/sesiones`} className="ac-sesiones">
                        <Icono nombre="sesiones" size={15} />
                        {u.sesiones_activas ?? 0} {(u.sesiones_activas ?? 0) === 1 ? 'sesión' : 'sesiones'}
                      </Link>
                    ) : (
                      <span className="ac-sesiones ac-sesiones--texto">
                        <Icono nombre="sesiones" size={15} />{u.sesiones_activas ?? 0}
                      </span>
                    )}
                  </td>
                  <td className="tabla__acciones">
                    {can('usuario_update') && (
                      <Link to={`/panel/usuarios/${u.id}/editar`} className="btn btn--sm btn--ghost">
                        <Icono nombre="editar" size={14} /> Editar
                      </Link>
                    )}
                    {can('usuario_estado') && (
                      <button
                        className={`btn btn--sm ${u.estado === 1 ? 'btn--danger' : 'btn--ghost'}`}
                        onClick={() => cambiarEstado(u)}
                      >
                        {u.estado === 1 ? 'Desactivar' : 'Activar'}
                      </button>
                    )}
                  </td>
                </tr>
              ))}
          </tbody>
        </table>
      </div>

      <Paginacion meta={meta} page={page} setPage={setPage} nombre="usuarios" />
    </div>
  )
}
