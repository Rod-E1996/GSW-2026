import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { EstadoVacio, FilaVacia, FilasCargando } from '../../../components/ui'
import { fechaCorta, hoyEnHotel } from '../../../utils'
import '../../../styles/admin-hotel.css'

const COLOR_ESTADO = { disponible: 'badge--ok', limpieza: 'badge--aviso', mantenimiento: 'badge--danger' }
const NOMBRE_CORTO = { disponible: 'Disponible', limpieza: 'Limpieza', mantenimiento: 'Mant.' }
const CLAVE_VISTA = 'hotellink.habitaciones.vista'

function vistaGuardada() {
  try {
    return localStorage.getItem(CLAVE_VISTA) === 'tabla' ? 'tabla' : 'tablero'
  } catch {
    return 'tablero'
  }
}

/**
 * Inventario de habitaciones. Vista de tablero por piso (estado de un vistazo y cambio
 * rapido de limpieza o mantenimiento) o vista de tabla. Carga todas las habitaciones y
 * filtra en el navegador: el inventario de un hotel cabe en una sola pagina.
 */
export default function HabitacionesList() {
  const { can } = useAuth()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [catalogos, setCatalogos] = useState({ tipos: [], estados: [] })
  const [bloqueos, setBloqueos] = useState([])
  const [busqueda, setBusqueda] = useState('')
  const [tipo, setTipo] = useState('')
  const [estado, setEstado] = useState('')
  const [vista, setVista] = useState(vistaGuardada)
  const [page, setPage] = useState(1)
  const [cargando, setCargando] = useState(true)
  const [cambiando, setCambiando] = useState(null)
  const [mensaje, setMensaje] = useState(null)
  const [error, setError] = useState(null)

  function cargar() {
    setCargando(true)
    api
      .get('/admin/habitaciones', { params: { per_page: 100, page } })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
      })
      .catch(() => setError('No se pudieron cargar las habitaciones.'))
      .finally(() => setCargando(false))
  }

  useEffect(() => {
    cargar()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page])

  useEffect(() => {
    api.get('/admin/habitaciones/catalogos').then((res) => setCatalogos(res.data))
    // Bloqueos vigentes, para marcar las habitaciones que hoy no se pueden vender
    if (can('bloqueo_index')) {
      api.get('/admin/bloqueos').then((res) => setBloqueos(res.data.data)).catch(() => {})
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  function cambiarVista(v) {
    setVista(v)
    try {
      localStorage.setItem(CLAVE_VISTA, v)
    } catch {
      // sin almacenamiento local: la vista se elige en cada visita
    }
  }

  const nombreEstado = (valor) => catalogos.estados.find((s) => s.valor === valor)?.nombre || valor

  async function cambiarEstado(h, nuevo) {
    if (nuevo === h.estado_habitacion) return
    setMensaje(null)
    setError(null)
    setCambiando(h.id)
    try {
      await api.put(`/admin/habitaciones/${h.id}/estado`, { estado_habitacion: nuevo })
      setItems((lista) => lista.map((x) => (x.id === h.id ? { ...x, estado_habitacion: nuevo, estado_nombre: nombreEstado(nuevo) } : x)))
      setMensaje(`La habitación ${h.numero} quedó en ${nombreEstado(nuevo).toLowerCase()}.`)
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo cambiar el estado.')
    } finally {
      setCambiando(null)
    }
  }

  async function eliminar(h) {
    if (!confirm(`¿Eliminar la habitación ${h.numero}?`)) return
    setMensaje(null)
    setError(null)
    try {
      const res = await api.delete(`/admin/habitaciones/${h.id}`)
      setMensaje(res.data.message)
      cargar()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo eliminar.')
    }
  }

  // Bloqueo que cubre hoy, por habitacion
  const bloqueoHoy = useMemo(() => {
    const hoy = hoyEnHotel()
    const mapa = {}
    bloqueos.forEach((b) => {
      if (b.fecha_inicio <= hoy && hoy <= b.fecha_fin) mapa[b.habitacion_id] = b
    })
    return mapa
  }, [bloqueos])

  const conteo = useMemo(() => {
    const c = { '': items.length }
    items.forEach((h) => { c[h.estado_habitacion] = (c[h.estado_habitacion] || 0) + 1 })
    return c
  }, [items])

  const visibles = useMemo(() => {
    const q = busqueda.trim().toLowerCase()
    return items.filter((h) =>
      (!estado || h.estado_habitacion === estado)
      && (!tipo || String(h.tipo_habitacion_id) === String(tipo))
      && (!q || String(h.numero).toLowerCase().includes(q) || (h.descripcion || '').toLowerCase().includes(q))
    )
  }, [items, busqueda, tipo, estado])

  const pisos = useMemo(() => {
    const grupos = new Map()
    visibles.forEach((h) => {
      if (!grupos.has(h.piso)) grupos.set(h.piso, [])
      grupos.get(h.piso).push(h)
    })
    return [...grupos.entries()]
  }, [visibles])

  const filtrando = busqueda || tipo || estado
  const bloqueadasHoy = Object.keys(bloqueoHoy).length

  function limpiarFiltros() {
    setBusqueda('')
    setTipo('')
    setEstado('')
  }

  function acciones(h, compacto) {
    return (
      <>
        {can('bloqueo_store') && (
          <Link to={`/panel/bloqueos?habitacion=${h.id}`} className="btn btn--sm btn--ghost" title="Bloquear fechas">
            {compacto ? <Icono nombre="candado" size={15} /> : 'Bloquear'}
          </Link>
        )}
        {can('habitacion_update') && (
          <Link to={`/panel/habitaciones/${h.id}/editar`} className="btn btn--sm btn--ghost" title="Editar">
            {compacto ? <Icono nombre="editar" size={15} /> : 'Editar'}
          </Link>
        )}
        {can('habitacion_destroy') && (
          <button className="btn btn--sm btn--danger" onClick={() => eliminar(h)} title="Eliminar">
            {compacto ? <Icono nombre="eliminar" size={15} /> : 'Eliminar'}
          </button>
        )}
      </>
    )
  }

  return (
    <div>
      <div className="page-head">
        <div>
          <h1 className="page-title">Habitaciones</h1>
          <p className="page-sub">
            {cargando && items.length === 0
              ? 'Cargando inventario…'
              : `${items.length} habitaciones · ${conteo.disponible || 0} disponibles${bloqueadasHoy ? ` · ${bloqueadasHoy} bloqueadas hoy` : ''}`}
          </p>
        </div>
        <div className="page-head__acciones">
          {can('bloqueo_index') && (
            <Link to="/panel/bloqueos" className="btn btn--ghost">
              <Icono nombre="candado" size={16} /> Bloqueos
            </Link>
          )}
          {can('habitacion_store') && (
            <Link to="/panel/habitaciones/nueva" className="btn">
              <Icono nombre="mas" size={16} /> Nueva habitación
            </Link>
          )}
        </div>
      </div>

      {mensaje && <div className="alerta alerta--ok">{mensaje}</div>}
      {error && <div className="alerta alerta--error">{error}</div>}

      <div className="pestanas" role="tablist" aria-label="Estado de la habitación">
        {[{ valor: '', nombre: 'Todas' }, ...catalogos.estados].map((s) => (
          <button
            key={s.valor || 'todas'}
            type="button"
            role="tab"
            aria-selected={estado === s.valor}
            className={estado === s.valor ? 'is-activa' : ''}
            onClick={() => setEstado(s.valor)}
          >
            {s.valor && <i className={`hab-punto hab-punto--${s.valor}`} />}
            {s.nombre}
            <span className="pestanas__conteo">{conteo[s.valor] || 0}</span>
          </button>
        ))}
      </div>

      <div className="filtros hab-filtros">
        <input
          className="filtros__busqueda"
          placeholder="Buscar por número u observación…"
          value={busqueda}
          onChange={(e) => setBusqueda(e.target.value)}
          aria-label="Buscar habitación"
        />
        <select value={tipo} onChange={(e) => setTipo(e.target.value)} aria-label="Tipo de habitación">
          <option value="">Todos los tipos</option>
          {catalogos.tipos.map((t) => (
            <option key={t.id} value={t.id}>{t.nombre}</option>
          ))}
        </select>
        {filtrando && (
          <button type="button" className="btn btn--ghost" onClick={limpiarFiltros}>Limpiar</button>
        )}
        <div className="vista-toggle" role="group" aria-label="Vista">
          <button type="button" className={vista === 'tablero' ? 'is-activa' : ''} onClick={() => cambiarVista('tablero')} title="Tablero por piso" aria-pressed={vista === 'tablero'}>
            <Icono nombre="cuadricula" size={16} /> Tablero
          </button>
          <button type="button" className={vista === 'tabla' ? 'is-activa' : ''} onClick={() => cambiarVista('tabla')} title="Tabla" aria-pressed={vista === 'tabla'}>
            <Icono nombre="lista" size={16} /> Tabla
          </button>
        </div>
      </div>

      {vista === 'tablero' && (
        <>
          {cargando && items.length === 0 && (
            <div className="hab-grid">
              {Array.from({ length: 8 }, (_, i) => (
                <div key={i} className="hab-card hab-card--cargando"><span className="esqueleto" /><span className="esqueleto" /></div>
              ))}
            </div>
          )}

          {!cargando && visibles.length === 0 && (
            <div className="tarjeta-simple">
              {items.length === 0 ? (
                <EstadoVacio
                  icono="puerta"
                  titulo="Aún no hay habitaciones"
                  texto="Registra las habitaciones del hotel para poder asignarlas a las reservas."
                  accion={can('habitacion_store') && <Link to="/panel/habitaciones/nueva" className="btn">Nueva habitación</Link>}
                />
              ) : (
                <EstadoVacio
                  icono="buscar"
                  titulo="Ninguna habitación coincide"
                  texto="Prueba con otro número, tipo o estado."
                  accion={<button type="button" className="btn btn--ghost" onClick={limpiarFiltros}>Quitar filtros</button>}
                />
              )}
            </div>
          )}

          {pisos.map(([piso, lista]) => (
            <section key={piso} className="hab-piso">
              <h2 className="hab-piso__titulo">
                Piso {piso}
                <span>{lista.length} {lista.length === 1 ? 'habitación' : 'habitaciones'}</span>
              </h2>
              <div className="hab-grid">
                {lista.map((h) => {
                  const bloqueo = bloqueoHoy[h.id]
                  return (
                    <article key={h.id} className={`hab-card hab-card--${h.estado_habitacion} ${cambiando === h.id ? 'is-cambiando' : ''}`}>
                      <header className="hab-card__cabecera">
                        <div>
                          <strong className="hab-card__numero">{h.numero}</strong>
                          <span className="hab-card__tipo">{h.tipo}</span>
                        </div>
                        <div className="hab-card__acciones">
                          {acciones(h, true)}
                        </div>
                      </header>

                      {bloqueo && (
                        <p className="hab-card__bloqueo">
                          <Icono nombre="candado" size={14} />
                          {bloqueo.motivo_nombre} hasta el {fechaCorta(bloqueo.fecha_fin)}
                        </p>
                      )}
                      {h.descripcion && <p className="hab-card__nota" title={h.descripcion}>{h.descripcion}</p>}

                      <footer className="hab-card__pie">
                        {can('habitacion_estado') ? (
                          <div className="hab-estados" role="group" aria-label={`Estado de la habitación ${h.numero}`}>
                            {catalogos.estados.map((s) => (
                              <button
                                key={s.valor}
                                type="button"
                                className={`hab-estados__opcion hab-estados__opcion--${s.valor} ${h.estado_habitacion === s.valor ? 'is-activa' : ''}`}
                                aria-pressed={h.estado_habitacion === s.valor}
                                disabled={cambiando === h.id}
                                onClick={() => cambiarEstado(h, s.valor)}
                                title={s.nombre}
                              >
                                {NOMBRE_CORTO[s.valor] || s.nombre}
                              </button>
                            ))}
                          </div>
                        ) : (
                          <span className={`badge ${COLOR_ESTADO[h.estado_habitacion] || ''}`}>{h.estado_nombre}</span>
                        )}
                      </footer>
                    </article>
                  )
                })}
              </div>
            </section>
          ))}
        </>
      )}

      {vista === 'tabla' && (
        <div className="tabla-wrap">
          <table className="tabla">
            <thead>
              <tr>
                <th>Habitación</th>
                <th>Piso</th>
                <th>Estado</th>
                <th>Observaciones</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {cargando && items.length === 0 && <FilasCargando columnas={5} />}
              {!cargando && visibles.length === 0 && (
                <FilaVacia
                  columnas={5}
                  icono={items.length === 0 ? 'puerta' : 'buscar'}
                  titulo={items.length === 0 ? 'Aún no hay habitaciones' : 'Ninguna habitación coincide'}
                  texto={items.length === 0 ? 'Registra las habitaciones del hotel.' : 'Prueba con otro número, tipo o estado.'}
                />
              )}
              {visibles.map((h) => (
                <tr key={h.id}>
                  <td>
                    <div className="celda-entidad">
                      <span className={`hab-numero hab-numero--${h.estado_habitacion}`}>{h.numero}</span>
                      <div>
                        <strong>{h.tipo}</strong>
                        {bloqueoHoy[h.id] && <small>Bloqueada hasta el {fechaCorta(bloqueoHoy[h.id].fecha_fin)}</small>}
                      </div>
                    </div>
                  </td>
                  <td>{h.piso}</td>
                  <td>
                    {can('habitacion_estado') ? (
                      <select
                        className="tabla__select"
                        aria-label={`Estado de la habitación ${h.numero}`}
                        value={h.estado_habitacion}
                        disabled={cambiando === h.id}
                        onChange={(e) => cambiarEstado(h, e.target.value)}
                      >
                        {catalogos.estados.map((s) => (
                          <option key={s.valor} value={s.valor}>{s.nombre}</option>
                        ))}
                      </select>
                    ) : (
                      <span className={`badge ${COLOR_ESTADO[h.estado_habitacion] || ''}`}>{h.estado_nombre}</span>
                    )}
                  </td>
                  <td className="celda-corta">{h.descripcion || <span className="form-nota">—</span>}</td>
                  <td className="tabla__acciones">{acciones(h, false)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {meta && meta.last_page > 1 && (
        <div className="paginacion">
          <button className="btn btn--ghost" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            ← Anterior
          </button>
          <span>
            Página {meta.current_page} de {meta.last_page}
          </span>
          <button className="btn btn--ghost" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>
            Siguiente →
          </button>
        </div>
      )}
    </div>
  )
}
