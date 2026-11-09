import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { EstadoVacio } from '../../../components/ui'
import { Paginacion } from '../accesos/comun'
import '../../../styles/admin-accesos.css'

const DESCRIPCION_ROL = {
  'Super Administrador': 'Acceso total al sistema. Sus permisos clave no se pueden quitar.',
  Recepcionista: 'Operación diaria: reservas, entradas, salidas y consulta de catálogos.',
  'Huésped': 'Portal de reservas: sus datos y sus propias reservas.',
}

export default function RolesList() {
  const { can } = useAuth()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [error, setError] = useState(null)
  const [aviso, setAviso] = useState(null)

  function cargar() {
    setCargando(true)
    api
      .get('/admin/roles', { params: { search: search || undefined, page } })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
      })
      .catch(() => setError('No se pudieron cargar los roles.'))
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

  async function eliminar(r) {
    if (!window.confirm(`¿Eliminar el rol "${r.name}"? Los usuarios que lo tengan perderán sus permisos.`)) return
    setError(null)
    setAviso(null)
    try {
      const res = await api.delete(`/admin/roles/${r.id}`)
      setAviso(res.data.message || 'Rol eliminado.')
      cargar()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo eliminar el rol.')
    }
  }

  // El rol con mas permisos marca el 100 % de la barra
  const maximo = Math.max(1, ...items.map((r) => r.permisos_count ?? 0))

  return (
    <div>
      <div className="page-head">
        <div>
          <h1 className="page-title">Roles</h1>
          <p className="page-sub">Cada rol agrupa los permisos de un tipo de usuario. Un usuario puede tener varios.</p>
        </div>
        {can('role_create') && (
          <Link to="/panel/roles/nuevo" className="btn"><Icono nombre="mas" size={16} /> Nuevo rol</Link>
        )}
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

      <form className="filtros" onSubmit={buscar}>
        <input
          className="filtros__busqueda"
          placeholder="Buscar rol por nombre…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          aria-label="Buscar rol"
        />
        <button className="btn btn--ghost">Buscar</button>
      </form>

      {cargando && (
        <div className="ac-tarjetas">
          {[1, 2, 3].map((i) => (
            <div key={i} className="ac-rol-tarjeta"><span className="esqueleto" /><span className="esqueleto" /></div>
          ))}
        </div>
      )}

      {!cargando && items.length === 0 && (
        <div className="tarjeta-simple">
          <EstadoVacio
            icono="roles"
            titulo={search ? 'Ningún rol coincide' : 'Aún no hay roles'}
            texto={search ? 'Prueba con otro nombre.' : 'Crea un rol y asígnale los permisos que necesita.'}
            accion={!search && can('role_create') && <Link to="/panel/roles/nuevo" className="btn">Nuevo rol</Link>}
          />
        </div>
      )}

      {!cargando && items.length > 0 && (
        <div className="ac-tarjetas">
          {items.map((r) => {
            const total = r.permisos_count ?? 0
            return (
              <article key={r.id} className="ac-rol-tarjeta">
                <div className="ac-rol-tarjeta__cabeza">
                  <span className="ac-rol-tarjeta__icono"><Icono nombre="roles" size={18} /></span>
                  <div>
                    <h3>{r.name}</h3>
                    <p>{DESCRIPCION_ROL[r.name] || 'Rol personalizado.'}</p>
                  </div>
                </div>
                <div className="ac-rol-tarjeta__medida">
                  <span><strong>{total}</strong> {total === 1 ? 'permiso' : 'permisos'}</span>
                  <div className="ac-barra-progreso"><span style={{ width: `${(total / maximo) * 100}%` }} /></div>
                </div>
                <div className="ac-rol-tarjeta__acciones">
                  {can('role_move_permiso') && (
                    <Link to={`/panel/roles/${r.id}/permisos`} className="btn btn--sm">
                      <Icono nombre="permisos" size={14} /> Permisos
                    </Link>
                  )}
                  {can('role_update') && (
                    <Link to={`/panel/roles/${r.id}/editar`} className="btn btn--sm btn--ghost">
                      <Icono nombre="editar" size={14} /> Renombrar
                    </Link>
                  )}
                  {can('role_destroy') && (
                    <button className="btn btn--sm btn--danger ac-empujar" onClick={() => eliminar(r)} title="Eliminar rol">
                      <Icono nombre="eliminar" size={14} />
                    </button>
                  )}
                </div>
              </article>
            )
          })}
        </div>
      )}

      <Paginacion meta={meta} page={page} setPage={setPage} nombre="roles" />
    </div>
  )
}
