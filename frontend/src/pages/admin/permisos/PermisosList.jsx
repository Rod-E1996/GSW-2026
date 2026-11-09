import { useEffect, useMemo, useState } from 'react'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { EstadoVacio, Indicador } from '../../../components/ui'
import { accionDe, agruparPorModulo } from '../accesos/comun'
import '../../../styles/admin-accesos.css'

// Catalogo completo de permisos agrupado por modulo. La API pagina de 15 en 15,
// asi que se piden todas las paginas para poder agrupar y filtrar sin saltos.
async function cargarTodos() {
  const primera = await api.get('/admin/permisos', { params: { page: 1 } })
  const ultima = primera.data.meta?.last_page || 1
  const resto = await Promise.all(
    Array.from({ length: ultima - 1 }, (_, i) => api.get('/admin/permisos', { params: { page: i + 2 } }))
  )
  return [primera, ...resto].flatMap((r) => r.data.data)
}

export default function PermisosList() {
  const { can } = useAuth()
  const [items, setItems] = useState([])
  const [cargando, setCargando] = useState(true)
  const [filtro, setFiltro] = useState('')
  const [abiertos, setAbiertos] = useState(new Set())
  const [error, setError] = useState(null)
  const [aviso, setAviso] = useState(null)

  const [nuevo, setNuevo] = useState('')
  const [creando, setCreando] = useState(false)

  const puedeGestionar = can('permiso_move')

  function cargar() {
    setCargando(true)
    cargarTodos()
      .then(setItems)
      .catch(() => setError('No se pudieron cargar los permisos.'))
      .finally(() => setCargando(false))
  }

  useEffect(() => {
    cargar()
  }, [])

  async function crear(e) {
    e.preventDefault()
    if (!nuevo.trim()) return
    setError(null)
    setAviso(null)
    setCreando(true)
    try {
      await api.post('/admin/permisos', { name: nuevo.trim() })
      setAviso(`Permiso «${nuevo.trim()}» creado. Asígnalo a un rol desde Roles › Permisos.`)
      setNuevo('')
      cargar()
    } catch (err) {
      const data = err.response?.data
      setError(data?.errors?.name?.[0] || data?.message || 'No se pudo crear el permiso.')
    } finally {
      setCreando(false)
    }
  }

  async function eliminar(p) {
    if (!window.confirm(`¿Eliminar el permiso "${p.name}"?`)) return
    setError(null)
    setAviso(null)
    try {
      const res = await api.delete(`/admin/permisos/${p.id}`)
      setAviso(res.data.message || 'Permiso eliminado.')
      cargar()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo eliminar el permiso.')
    }
  }

  function alternar(clave) {
    setAbiertos((s) => {
      const n = new Set(s)
      if (n.has(clave)) n.delete(clave)
      else n.add(clave)
      return n
    })
  }

  const q = filtro.trim().toLowerCase()
  const modulos = useMemo(() => {
    const visibles = items.filter((p) => !q || p.name.toLowerCase().includes(q) || accionDe(p.name).toLowerCase().includes(q))
    return agruparPorModulo(visibles, (p) => p.name)
  }, [items, q])

  const sinUso = items.filter((p) => (p.roles_count ?? 0) === 0).length

  return (
    <div>
      <div className="page-head">
        <div>
          <h1 className="page-title">Permisos</h1>
          <p className="page-sub">Cada acción del sistema tiene un permiso. Se asignan a los roles, no directamente a las personas.</p>
        </div>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

      {!cargando && (
        <div className="indicadores">
          <Indicador icono="permisos" etiqueta="Permisos" valor={items.length} />
          <Indicador icono="cuadricula" tono="azul" etiqueta="Módulos" valor={agruparPorModulo(items, (p) => p.name).length} />
          <Indicador icono="alerta" tono="mango" etiqueta="Sin asignar a ningún rol" valor={sinUso} nota="se pueden eliminar" />
        </div>
      )}

      <div className={puedeGestionar ? 'ac-permisos-barra' : ''}>
        <div className="filtros">
          <input
            className="filtros__busqueda"
            placeholder="Filtrar por módulo, acción o código…"
            value={filtro}
            onChange={(e) => setFiltro(e.target.value)}
            aria-label="Filtrar permisos"
          />
          <button type="button" className="btn btn--ghost" onClick={() => setAbiertos(new Set(modulos.map((m) => m.clave)))}>Expandir todo</button>
          <button type="button" className="btn btn--ghost" onClick={() => setAbiertos(new Set())}>Contraer</button>
        </div>

        {puedeGestionar && (
          <form className="filtros ac-nuevo-permiso" onSubmit={crear}>
            <input
              placeholder="modulo_accion, p. ej. reporte_exportar"
              value={nuevo}
              onChange={(e) => setNuevo(e.target.value.replace(/\s+/g, '_').toLowerCase())}
              aria-label="Nombre del nuevo permiso"
            />
            <button className="btn" disabled={creando || !nuevo.trim()}>
              <Icono nombre="mas" size={15} /> {creando ? 'Creando…' : 'Crear'}
            </button>
          </form>
        )}
      </div>

      {cargando && (
        <div className="ac-lista-modulos">
          {[1, 2, 3, 4].map((i) => <div key={i} className="ac-modulo-fila"><span className="esqueleto" /></div>)}
        </div>
      )}

      {!cargando && modulos.length === 0 && (
        <div className="tarjeta-simple">
          <EstadoVacio icono="permisos" titulo="Ningún permiso coincide" texto="Prueba con otra palabra, por ejemplo «reserva» o «eliminar»." />
        </div>
      )}

      {!cargando && modulos.length > 0 && (
        <div className="ac-lista-modulos">
          {modulos.map((m) => {
            const abierto = Boolean(q) || abiertos.has(m.clave)
            const libres = m.permisos.filter((p) => (p.roles_count ?? 0) === 0).length
            return (
              <section key={m.clave} className={`ac-modulo-fila ${abierto ? 'is-abierto' : ''}`}>
                <button type="button" className="ac-modulo-fila__cabeza" onClick={() => alternar(m.clave)} aria-expanded={abierto}>
                  <Icono nombre="flecha" size={14} className="ac-modulo-fila__flecha" />
                  <strong>{m.nombre}</strong>
                  <span className="form-nota">{m.permisos.length} {m.permisos.length === 1 ? 'permiso' : 'permisos'}</span>
                  {libres > 0 && <span className="badge badge--aviso">{libres} sin asignar</span>}
                </button>
                {abierto && (
                  <table className="tabla ac-tabla-permisos">
                    <tbody>
                      {m.permisos.map((p) => {
                        const roles = p.roles_count ?? 0
                        return (
                          <tr key={p.id}>
                            <td>{accionDe(p.name)}</td>
                            <td><code className="ac-codigo">{p.name}</code></td>
                            <td>
                              <span className={`badge ${roles ? 'badge--info' : ''}`}>
                                {roles ? `${roles} ${roles === 1 ? 'rol' : 'roles'}` : 'Sin rol'}
                              </span>
                            </td>
                            <td className="tabla__acciones">
                              {puedeGestionar && (
                                <button
                                  className="btn btn--sm btn--danger"
                                  onClick={() => eliminar(p)}
                                  disabled={roles > 0}
                                  title={roles > 0 ? 'Quítalo primero de los roles que lo usan' : 'Eliminar permiso'}
                                >
                                  <Icono nombre="eliminar" size={14} />
                                </button>
                              )}
                            </td>
                          </tr>
                        )
                      })}
                    </tbody>
                  </table>
                )}
              </section>
            )
          })}
        </div>
      )}
    </div>
  )
}
