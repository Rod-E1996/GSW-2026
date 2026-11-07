import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import { fechaCorta, formatoUSD } from '../../../utils'

export const COLOR_RESERVA = { 1: 'badge--aviso', 2: 'badge--ok', 3: 'badge--info', 4: '', 5: 'badge--danger' }

export default function ReservasList() {
  const { can } = useAuth()
  const [params, setParams] = useSearchParams()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [catalogos, setCatalogos] = useState({ estados: [], canales: [] })
  const [filtros, setFiltros] = useState({
    q: params.get('q') || '',
    estado: params.get('estado') || '',
    canal: params.get('canal') || '',
    desde: params.get('desde') || '',
    hasta: params.get('hasta') || '',
  })
  const [page, setPage] = useState(1)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(null)

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

  function buscar(e) {
    e.preventDefault()
    setParams(Object.fromEntries(Object.entries(filtros).filter(([, v]) => v)), { replace: true })
    if (page === 1) cargar()
    else setPage(1)
  }

  function limpiar() {
    const vacios = { q: '', estado: '', canal: '', desde: '', hasta: '' }
    setFiltros(vacios)
    setParams({}, { replace: true })
    setPage(1)
    cargar(vacios, 1)
  }

  const setFiltro = (campo, valor) => setFiltros((f) => ({ ...f, [campo]: valor }))

  return (
    <div>
      <div className="page-head">
        <h1 className="page-title">Reservas</h1>
        <div className="page-head__acciones">
          {can('reserva_index') && <Link to="/panel/calendario" className="btn btn--ghost">Calendario</Link>}
          {can('reserva_store') && <Link to="/panel/reservas/nueva" className="btn">+ Nueva reserva</Link>}
        </div>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}

      <form className="filtros" onSubmit={buscar}>
        <input
          className="filtros__busqueda"
          placeholder="Código, documento, correo, nombre o QR…"
          value={filtros.q}
          onChange={(e) => setFiltro('q', e.target.value)}
          aria-label="Buscar reserva"
        />
        <select value={filtros.estado} onChange={(e) => setFiltro('estado', e.target.value)} aria-label="Estado">
          <option value="">Todos los estados</option>
          {catalogos.estados.map((s) => <option key={s.valor} value={s.valor}>{s.nombre}</option>)}
        </select>
        <select value={filtros.canal} onChange={(e) => setFiltro('canal', e.target.value)} aria-label="Canal">
          <option value="">Todos los canales</option>
          {catalogos.canales.map((c) => <option key={c.valor} value={c.valor}>{c.nombre}</option>)}
        </select>
        <label className="filtros__fecha">Llegada desde <input type="date" value={filtros.desde} onChange={(e) => setFiltro('desde', e.target.value)} /></label>
        <label className="filtros__fecha">hasta <input type="date" value={filtros.hasta} onChange={(e) => setFiltro('hasta', e.target.value)} /></label>
        <button className="btn btn--ghost">Buscar</button>
        <button type="button" className="btn btn--ghost" onClick={limpiar}>Limpiar</button>
      </form>

      <div className="tabla-wrap">
        <table className="tabla">
          <thead>
            <tr>
              <th>Código</th>
              <th>Titular</th>
              <th>Habitación</th>
              <th>Llegada</th>
              <th>Salida</th>
              <th>Canal</th>
              <th>Total</th>
              <th>Estado</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {cargando && <tr><td colSpan="9" className="tabla__vacio">Cargando…</td></tr>}
            {!cargando && items.length === 0 && <tr><td colSpan="9" className="tabla__vacio">No hay reservas con esos filtros.</td></tr>}
            {!cargando && items.map((r) => (
              <tr key={r.codigo}>
                <td><strong>{r.codigo}</strong></td>
                <td>
                  {r.titular}
                  {r.documento && <small className="form-nota tabla__sub">{r.documento}</small>}
                </td>
                <td>{r.tipo}{r.habitacion ? ` · ${r.habitacion}` : ''}</td>
                <td>{fechaCorta(r.fecha_llegada)}</td>
                <td>{fechaCorta(r.fecha_salida)}</td>
                <td>{r.canal_nombre}</td>
                <td>{formatoUSD(r.total)}</td>
                <td><span className={`badge ${r.vencida ? '' : COLOR_RESERVA[r.estado] || ''}`}>{r.estado_nombre}</span></td>
                <td className="tabla__acciones">
                  {can('reserva_show') && <Link to={`/panel/reservas/${r.codigo}`} className="btn btn--sm btn--ghost">Abrir</Link>}
                </td>
              </tr>
            ))}
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
