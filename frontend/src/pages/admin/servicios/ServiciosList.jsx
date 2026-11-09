import { useEffect, useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { EstadoVacio, Indicador } from '../../../components/ui'
import { formatoUSD, rutaImagen } from '../../../utils'
import { IconoServicio, tonoCategoria } from './iconosServicio'
import '../../../styles/admin-catalogo.css'

// Unidad corta que acompana al precio
const UNIDAD = {
  por_persona_noche: 'por persona y noche',
  por_persona: 'por persona',
  por_noche: 'por noche',
  fijo: 'precio fijo',
}

function TarjetaServicio({ s, can, onEliminar }) {
  const tono = tonoCategoria(s.categoria_servicio_id)
  return (
    <article className="servicio-card">
      <div className={`servicio-card__media servicio-card__media--${tono}`}>
        {s.imagen_url ? (
          <img src={rutaImagen(s.imagen_url)} alt={s.nombre} loading="lazy" />
        ) : (
          <IconoServicio icono={s.icono} nombre={s.nombre} tono={tono} size="lg" />
        )}
        {s.requiere_fecha && (
          <span className="servicio-card__agenda" title="El huésped elige fecha y hora al contratarlo">
            <Icono nombre="reloj" size={13} /> Con fecha y hora
          </span>
        )}
      </div>
      <div className="servicio-card__cuerpo">
        <span className="servicio-card__categoria">{s.categoria || 'Sin categoría'}</span>
        <h3>{s.nombre}</h3>
        <p className="servicio-card__desc">{s.descripcion || 'Sin descripción para el portal.'}</p>
      </div>
      <div className="servicio-card__pie">
        <div className="servicio-card__precio">
          <strong>{formatoUSD(s.precio)}</strong>
          <span>{UNIDAD[s.forma_cobro] || s.forma_cobro_nombre}</span>
        </div>
        <div className="servicio-card__acciones">
          {can('servicio_update') && (
            <Link to={`/panel/servicios/${s.id}/editar`} className="btn btn--sm btn--ghost">
              <Icono nombre="editar" size={14} /> Editar
            </Link>
          )}
          {can('servicio_destroy') && (
            <button
              type="button"
              className="btn btn--sm btn--danger btn--icono-sm"
              onClick={() => onEliminar(s)}
              title={`Eliminar ${s.nombre}`}
              aria-label={`Eliminar ${s.nombre}`}
            >
              <Icono nombre="eliminar" size={14} />
            </button>
          )}
        </div>
      </div>
    </article>
  )
}

function TarjetasCargando() {
  return (
    <div className="servicios-grid" aria-hidden="true">
      {Array.from({ length: 6 }, (_, i) => (
        <div key={i} className="servicio-card servicio-card--cargando">
          <div className="servicio-card__media" />
          <div className="servicio-card__cuerpo">
            <span className="esqueleto" style={{ width: '35%' }} />
            <span className="esqueleto" style={{ width: '70%', height: '1rem' }} />
            <span className="esqueleto" style={{ width: '90%' }} />
          </div>
        </div>
      ))}
    </div>
  )
}

export default function ServiciosList() {
  const { can } = useAuth()
  const [params, setParams] = useSearchParams()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [categorias, setCategorias] = useState([])
  const [nombre, setNombre] = useState('')
  const [categoria, setCategoria] = useState(params.get('categoria') || '')
  const [page, setPage] = useState(1)
  const [cargando, setCargando] = useState(true)
  const [mensaje, setMensaje] = useState(null)
  const [error, setError] = useState(null)

  function cargar(opciones = {}) {
    const filtroCategoria = opciones.categoria ?? categoria
    setCargando(true)
    api
      .get('/admin/servicios', {
        params: { nombre: nombre || undefined, categoria_servicio_id: filtroCategoria || undefined, page: opciones.page ?? page },
      })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
      })
      .catch(() => setError('No se pudieron cargar los servicios.'))
      .finally(() => setCargando(false))
  }

  useEffect(() => {
    cargar()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page])

  function cargarCategorias() {
    api.get('/admin/categorias-servicio').then((res) => setCategorias(res.data.data))
  }

  useEffect(cargarCategorias, [])

  function buscar(e) {
    e.preventDefault()
    if (page === 1) cargar()
    else setPage(1)
  }

  function elegirCategoria(id) {
    setCategoria(id)
    setParams(id ? { categoria: id } : {}, { replace: true })
    setPage(1)
    cargar({ categoria: id, page: 1 })
  }

  function limpiar() {
    setNombre('')
    setCategoria('')
    setParams({}, { replace: true })
    setPage(1)
    setCargando(true)
    api.get('/admin/servicios', { params: { page: 1 } })
      .then((res) => { setItems(res.data.data); setMeta(res.data.meta) })
      .finally(() => setCargando(false))
  }

  async function eliminar(s) {
    if (!confirm(`¿Eliminar "${s.nombre}"? Dejará de ofrecerse en el portal.`)) return
    setMensaje(null)
    setError(null)
    try {
      const res = await api.delete(`/admin/servicios/${s.id}`)
      setMensaje(res.data.message)
      cargar()
      cargarCategorias()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo eliminar.')
    }
  }

  // En el orden del catalogo: primero por categoria (segun su orden) y luego por nombre
  const ordenados = useMemo(() => {
    const orden = new Map(categorias.map((c, i) => [c.id, i]))
    return [...items].sort((a, b) =>
      (orden.get(a.categoria_servicio_id) ?? 99) - (orden.get(b.categoria_servicio_id) ?? 99) || a.nombre.localeCompare(b.nombre))
  }, [items, categorias])

  const total = categorias.reduce((suma, c) => suma + (c.servicios_count || 0), 0)
  const conFecha = items.filter((s) => s.requiere_fecha).length
  const filtrando = Boolean(nombre || categoria)

  return (
    <div className="catalogo">
      <div className="page-head">
        <div>
          <h1 className="page-title">Servicios</h1>
          <p className="page-sub">Lo que el huésped puede agregar a su reserva en el portal o en recepción.</p>
        </div>
        <div className="page-head__acciones">
          <Link to="/panel/servicios/categorias" className="btn btn--ghost">Categorías</Link>
          {can('servicio_store') && (
            <Link to="/panel/servicios/nuevo" className="btn">
              <Icono nombre="mas" size={16} /> Nuevo servicio
            </Link>
          )}
        </div>
      </div>

      {mensaje && <div className="alerta alerta--ok">{mensaje}</div>}
      {error && <div className="alerta alerta--error">{error}</div>}

      <div className="indicadores">
        <Indicador icono="servicios" etiqueta="Servicios activos" valor={meta?.total ?? total} nota="en el catálogo" />
        <Indicador icono="lista" tono="azul" etiqueta="Categorías" valor={categorias.length} />
        <Indicador icono="reloj" tono="mango" etiqueta="Se agendan" valor={conFecha} nota={meta?.last_page > 1 ? 'piden fecha y hora (en esta página)' : 'piden fecha y hora al contratarlos'} />
      </div>

      <div className="catalogo__barra">
        <div className="pestanas" role="tablist" aria-label="Categoría">
          <button type="button" role="tab" aria-selected={!categoria} className={!categoria ? 'is-activa' : ''} onClick={() => elegirCategoria('')}>
            Todas <span className="pestanas__conteo">{total}</span>
          </button>
          {categorias.map((c) => (
            <button
              key={c.id}
              type="button"
              role="tab"
              aria-selected={String(categoria) === String(c.id)}
              className={String(categoria) === String(c.id) ? 'is-activa' : ''}
              onClick={() => elegirCategoria(String(c.id))}
            >
              {c.nombre} <span className="pestanas__conteo">{c.servicios_count}</span>
            </button>
          ))}
        </div>
        <form className="catalogo__buscar" onSubmit={buscar} role="search">
          <input className="filtros__busqueda" placeholder="Buscar servicio…" value={nombre} onChange={(e) => setNombre(e.target.value)} aria-label="Buscar servicio" />
          <button className="btn btn--ghost">Buscar</button>
        </form>
      </div>

      {cargando && <TarjetasCargando />}

      {!cargando && items.length === 0 && (
        <div className="tarjeta-simple">
          {filtrando ? (
            <EstadoVacio
              icono="buscar"
              titulo="Ningún servicio coincide"
              texto="Prueba con otro nombre o muestra todas las categorías."
              accion={<button type="button" className="btn btn--ghost" onClick={limpiar}>Quitar filtros</button>}
            />
          ) : (
            <EstadoVacio
              icono="servicios"
              titulo="Todavía no hay servicios"
              texto="Agrega alimentación, traslados o actividades para que el huésped los sume a su reserva."
              accion={can('servicio_store') && <Link to="/panel/servicios/nuevo" className="btn">Crear el primero</Link>}
            />
          )}
        </div>
      )}

      {!cargando && items.length > 0 && (
        <div className="servicios-grid">
          {ordenados.map((s) => <TarjetaServicio key={s.id} s={s} can={can} onEliminar={eliminar} />)}
        </div>
      )}

      {meta && meta.last_page > 1 && (
        <div className="paginacion">
          <button className="btn btn--ghost" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>← Anterior</button>
          <span>Página {meta.current_page} de {meta.last_page} · {meta.total} servicios</span>
          <button className="btn btn--ghost" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>Siguiente →</button>
        </div>
      )}
    </div>
  )
}
