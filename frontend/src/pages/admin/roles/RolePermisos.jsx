import { useEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../../../api'
import Icono from '../../../components/Icono'
import { EstadoVacio } from '../../../components/ui'
import { accionDe, agruparPorModulo } from '../accesos/comun'
import '../../../styles/admin-accesos.css'

// Permisos de un rol agrupados por modulo, con casillas y "todo el modulo".
// Usa la misma API: GET /admin/roles/{id} y PUT /admin/roles/{id}/permisos con la lista final.
export default function RolePermisos() {
  const { id } = useParams()

  const [nombre, setNombre] = useState('')
  const [todos, setTodos] = useState([])
  const [elegidos, setElegidos] = useState(new Set())
  const [guardados, setGuardados] = useState(new Set())
  const [filtro, setFiltro] = useState('')
  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)
  const [error, setError] = useState(null)
  const [aviso, setAviso] = useState(null)

  function aplicar(data) {
    setNombre(data.name)
    setTodos([...data.permisos_asignados, ...data.permisos_disponibles].sort())
    setElegidos(new Set(data.permisos_asignados))
    setGuardados(new Set(data.permisos_asignados))
  }

  useEffect(() => {
    api
      .get(`/admin/roles/${id}`)
      .then((res) => aplicar(res.data.data))
      .catch(() => setError('No se pudo cargar el rol.'))
      .finally(() => setCargando(false))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  function alternar(p) {
    setElegidos((s) => {
      const n = new Set(s)
      if (n.has(p)) n.delete(p)
      else n.add(p)
      return n
    })
  }

  function alternarModulo(permisos, marcar) {
    setElegidos((s) => {
      const n = new Set(s)
      permisos.forEach((p) => (marcar ? n.add(p) : n.delete(p)))
      return n
    })
  }

  async function guardar() {
    setGuardando(true)
    setError(null)
    setAviso(null)
    try {
      const res = await api.put(`/admin/roles/${id}/permisos`, { permisos: [...elegidos] })
      setAviso(res.data.message)
      // Se recarga para reflejar cualquier permiso fijo que el servidor reinserto
      const r = await api.get(`/admin/roles/${id}`)
      aplicar(r.data.data)
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudieron guardar los permisos.')
    } finally {
      setGuardando(false)
    }
  }

  const q = filtro.trim().toLowerCase()
  const modulos = useMemo(() => {
    const visibles = todos.filter((p) => !q || p.toLowerCase().includes(q) || accionDe(p).toLowerCase().includes(q))
    return agruparPorModulo(visibles).filter((m) => !q || m.permisos.length > 0)
  }, [todos, q])

  // Cambios sin guardar
  const agregados = [...elegidos].filter((p) => !guardados.has(p)).length
  const quitados = [...guardados].filter((p) => !elegidos.has(p)).length
  const hayCambios = agregados + quitados > 0

  if (cargando) {
    return <div className="tarjeta-simple"><span className="esqueleto" /><br /><span className="esqueleto" /></div>
  }

  return (
    <div className="ac-permisos-pagina">
      <Link to="/panel/roles" className="ac-volver"><Icono nombre="volver" size={15} /> Roles</Link>
      <div className="page-head">
        <div>
          <h1 className="page-title">Permisos de «{nombre}»</h1>
          <p className="page-sub">
            {elegidos.size} de {todos.length} permisos asignados. Marca un módulo completo o solo las acciones que necesite este rol.
          </p>
        </div>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

      <div className="filtros">
        <input
          className="filtros__busqueda"
          placeholder="Filtrar por módulo, acción o código del permiso…"
          value={filtro}
          onChange={(e) => setFiltro(e.target.value)}
          aria-label="Filtrar permisos"
        />
        <button type="button" className="btn btn--ghost" onClick={() => alternarModulo(todos, true)}>Marcar todos</button>
        <button type="button" className="btn btn--ghost" onClick={() => alternarModulo(todos, false)}>Quitar todos</button>
      </div>

      {modulos.length === 0 ? (
        <div className="tarjeta-simple">
          <EstadoVacio icono="permisos" titulo="Ningún permiso coincide" texto="Prueba con otra palabra, por ejemplo «reservas» o «eliminar»." />
        </div>
      ) : (
        <div className="ac-modulos">
          {modulos.map((m) => {
            const marcados = m.permisos.filter((p) => elegidos.has(p)).length
            const todo = marcados === m.permisos.length
            return (
              <section key={m.clave} className={`ac-modulo ${marcados ? 'tiene-marcados' : ''}`}>
                <header className="ac-modulo__cabeza">
                  <label className="ac-modulo__todo">
                    <input
                      type="checkbox"
                      checked={todo}
                      ref={(el) => { if (el) el.indeterminate = marcados > 0 && !todo }}
                      onChange={() => alternarModulo(m.permisos, !todo)}
                    />
                    <strong>{m.nombre}</strong>
                  </label>
                  <span className="ac-modulo__conteo">{marcados}/{m.permisos.length}</span>
                </header>
                <ul>
                  {m.permisos.map((p) => (
                    <li key={p}>
                      <label className={`ac-permiso ${elegidos.has(p) !== guardados.has(p) ? 'is-cambiado' : ''}`}>
                        <input type="checkbox" checked={elegidos.has(p)} onChange={() => alternar(p)} />
                        <span>
                          {accionDe(p)}
                          <code>{p}</code>
                        </span>
                      </label>
                    </li>
                  ))}
                </ul>
              </section>
            )
          })}
        </div>
      )}

      <div className={`ac-guardar ${hayCambios ? 'is-visible' : ''}`}>
        <span>
          {hayCambios
            ? `Cambios sin guardar: ${agregados} para agregar, ${quitados} para quitar.`
            : 'Sin cambios pendientes.'}
        </span>
        <div>
          {hayCambios && (
            <button type="button" className="btn btn--ghost" onClick={() => setElegidos(new Set(guardados))}>Descartar</button>
          )}
          <button className="btn" onClick={guardar} disabled={guardando || !hayCambios}>
            {guardando ? 'Guardando…' : 'Guardar permisos'}
          </button>
        </div>
      </div>
    </div>
  )
}
