import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { Avatar, FilasCargando, FilaVacia } from '../../../components/ui'
import { fechaCorta, formatoUSD } from '../../../utils'
import '../../../styles/admin-reservas.css'

export const COLOR_RESERVA = { 1: 'badge--aviso', 2: 'badge--ok', 3: 'badge--info', 4: '', 5: 'badge--danger' }

// Pestanas de estado: valor del filtro "estado" de la API y su nombre
const PESTANAS = [
  ['', 'Todas', 'todas'],
  ['1', 'Pendientes', '1'],
  ['2', 'Confirmadas', '2'],
  ['3', 'En estadía', '3'],
  ['4', 'Finalizadas', '4'],
  ['5', 'Canceladas', '5'],
  ['vencida', 'Vencidas', 'vencida'],
]

const FILTROS_VACIOS = { q: '', estado: '', canal: '', desde: '', hasta: '' }

// "12/10/2026" -> "12 oct"
function diaMes(fecha) {
  if (!fecha) return '—'
  const [a, m, d] = fecha.split('-').map(Number)
  return new Date(Date.UTC(a, m - 1, d)).toLocaleDateString('es-SV', { day: 'numeric', month: 'short', timeZone: 'UTC' }).replace('.', '')
}

export default function ReservasList() {
  const { can } = useAuth()
  const navigate = useNavigate()
  const [params, setParams] = useSearchParams()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [catalogos, setCatalogos] = useState({ estados: [], canales: [] })
  const [filtros, setFiltros] = useState(() => Object.fromEntries(Object.keys(FILTROS_VACIOS).map((k) => [k, params.get(k) || ''])))
  const [page, setPage] = useState(1)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(null)

  const hayFiltros = Boolean(filtros.q || filtros.canal || filtros.desde || filtros.hasta)

  function cargar(f = filtros, p = page) {
    setCargando(true)
    setError(null)
    const consulta = Object.fromEntries(Object.entries(f).filter(([, v]) => v))
    api
      .get('/admin/reservas', { params: { ...consulta, page: p } })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
      })
      .catch((err) => {
        const e = err.response?.data?.errors
        setError(e ? Object.values(e)[0][0] : 'No se pudieron cargar las reservas.')
      })
      .finally(() => setCargando(false))
  }

  useEffect(() => {
    api.get('/admin/reservas/catalogos').then((res) => setCatalogos(res.data))
  }, [])

  useEffect(() => {
    cargar()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page])

  function aplicar(nuevos) {
    setFiltros(nuevos)
    setParams(Object.fromEntries(Object.entries(nuevos).filter(([, v]) => v)), { replace: true })
    if (page === 1) cargar(nuevos, 1)
    else setPage(1)
  }

  function buscar(e) {
    e.preventDefault()
    aplicar(filtros)
  }

  const setFiltro = (campo, valor) => setFiltros((f) => ({ ...f, [campo]: valor }))
  const conteos = meta?.conteos

  return (
    <div className="reservas-lista">
      <div className="page-head">
        <div>
          <h1 className="page-title">Reservas</h1>
          <p className="page-sub">
            {meta ? `${meta.total} ${meta.total === 1 ? 'reserva' : 'reservas'} ${filtros.estado || hayFiltros ? 'con estos filtros' : 'en total'}` : 'Buscando…'}
          </p>
        </div>
        <div className="page-head__acciones">
          {can('reserva_index') && (
            <Link to="/panel/calendario" className="btn btn--ghost"><Icono nombre="calendario" size={16} /> Calendario</Link>
          )}
          {can('reserva_store') && (
            <Link to="/panel/reservas/nueva" className="btn"><Icono nombre="mas" size={16} /> Nueva reserva</Link>
          )}
        </div>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}

      <div className="pestanas" role="tablist" aria-label="Estado de la reserva">
        {PESTANAS.map(([valor, nombre, clave]) => (
          <button
            key={clave}
            type="button"
            role="tab"
            aria-selected={filtros.estado === valor}
            className={filtros.estado === valor ? 'is-activa' : ''}
            onClick={() => aplicar({ ...filtros, estado: valor })}
          >
            {nombre}
            {conteos && <span className="pestanas__conteo">{conteos[clave] ?? 0}</span>}
          </button>
        ))}
      </div>

      <form className="filtros" onSubmit={buscar}>
        <input
          className="filtros__busqueda"
          placeholder="Código, documento, correo, teléfono, nombre o QR…"
          value={filtros.q}
          onChange={(e) => setFiltro('q', e.target.value)}
          aria-label="Buscar reserva"
        />
        <select value={filtros.canal} onChange={(e) => setFiltro('canal', e.target.value)} aria-label="Canal">
          <option value="">Todos los canales</option>
          {catalogos.canales.map((c) => <option key={c.valor} value={c.valor}>{c.nombre}</option>)}
        </select>
        <label className="filtros__fecha">Llegada del <input type="date" value={filtros.desde} onChange={(e) => setFiltro('desde', e.target.value)} /></label>
        <label className="filtros__fecha">al <input type="date" value={filtros.hasta} onChange={(e) => setFiltro('hasta', e.target.value)} /></label>
        <button className="btn"><Icono nombre="buscar" size={16} /> Buscar</button>
        {(hayFiltros || filtros.estado) && (
          <button type="button" className="btn btn--ghost" onClick={() => aplicar(FILTROS_VACIOS)}>Limpiar</button>
        )}
      </form>

      <div className="tabla-wrap">
        <table className="tabla tabla--reservas">
          <thead>
            <tr>
              <th>Reserva</th>
              <th>Huésped</th>
              <th>Habitación</th>
              <th>Estadía</th>
              <th className="tabla__num">Total</th>
              <th>Estado</th>
              <th aria-label="Acciones" />
            </tr>
          </thead>
          <tbody>
            {cargando && <FilasCargando columnas={7} filas={6} />}
            {!cargando && items.length === 0 && (
              <FilaVacia
                columnas={7}
                icono="reservas"
                titulo={hayFiltros || filtros.estado ? 'Ninguna reserva coincide' : 'Todavía no hay reservas'}
                texto={hayFiltros || filtros.estado ? 'Prueba con otro estado o quita algunos filtros.' : 'Las reservas del portal y las de recepción aparecerán aquí.'}
                accion={hayFiltros || filtros.estado
                  ? <button type="button" className="btn btn--ghost" onClick={() => aplicar(FILTROS_VACIOS)}>Quitar filtros</button>
                  : can('reserva_store') && <Link to="/panel/reservas/nueva" className="btn">Registrar una reserva</Link>}
              />
            )}
            {!cargando && items.map((r) => {
              const abrir = can('reserva_show') ? () => navigate(`/panel/reservas/${r.codigo}`) : undefined
              return (
                <tr key={r.codigo} className={abrir ? 'tabla__fila-clic' : ''} onClick={abrir}>
                  <td>
                    <div className="celda-entidad">
                      <div>
                        <strong className="codigo-reserva">{r.codigo}</strong>
                        <small>{r.canal_nombre !== '—' ? r.canal_nombre : 'Sin canal'}</small>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div className="celda-entidad">
                      <Avatar nombre={r.titular} size="sm" />
                      <div>
                        <strong>{r.titular || '—'}</strong>
                        <small>{r.documento || 'Sin documento'}</small>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div className="celda-entidad">
                      <div>
                        <strong>{r.habitacion ? `Hab. ${r.habitacion}` : 'Sin asignar'}</strong>
                        <small>{r.tipo}</small>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div className="celda-entidad">
                      <div>
                        <strong className="estadia-rango">{diaMes(r.fecha_llegada)} <Icono nombre="flecha" size={13} /> {diaMes(r.fecha_salida)}</strong>
                        <small>
                          {r.noches} {r.noches === 1 ? 'noche' : 'noches'} · {r.personas} {r.personas === 1 ? 'persona' : 'personas'} · {fechaCorta(r.fecha_llegada).slice(-4)}
                        </small>
                      </div>
                    </div>
                  </td>
                  <td className="tabla__num"><strong>{formatoUSD(r.total)}</strong></td>
                  <td><span className={`badge ${r.vencida ? '' : COLOR_RESERVA[r.estado] || ''}`}>{r.estado_nombre}</span></td>
                  <td className="tabla__acciones">
                    {abrir && (
                      <Link to={`/panel/reservas/${r.codigo}`} className="btn btn--sm btn--ghost" onClick={(e) => e.stopPropagation()}>
                        Abrir
                      </Link>
                    )}
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>

      {meta && meta.last_page > 1 && (
        <div className="paginacion">
          <button className="btn btn--ghost" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>← Anterior</button>
          <span>Página {meta.current_page} de {meta.last_page} · {meta.total} reservas</span>
          <button className="btn btn--ghost" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>Siguiente →</button>
        </div>
      )}
    </div>
  )
}
