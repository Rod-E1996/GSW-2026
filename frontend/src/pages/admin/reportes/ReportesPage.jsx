import { useEffect, useState } from 'react'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { descargar, fechaCorta, formatoUSD, hoyEnHotel } from '../../../utils'

// Del primer al ultimo dia del mes actual
function mesActual() {
  const [a, m] = hoyEnHotel().split('-').map(Number)
  const ultimo = new Date(a, m, 0).getDate()
  const mm = String(m).padStart(2, '0')
  return { desde: `${a}-${mm}-01`, hasta: `${a}-${mm}-${ultimo}` }
}

export default function ReportesPage() {
  const { can } = useAuth()
  const [filtros, setFiltros] = useState({ ...mesActual(), tipo_habitacion_id: '' })
  const [reporte, setReporte] = useState(null)
  const [tipos, setTipos] = useState([])
  const [error, setError] = useState(null)

  const params = Object.fromEntries(Object.entries(filtros).filter(([, v]) => v !== ''))

  useEffect(() => {
    if (!filtros.desde || !filtros.hasta) return
    setError(null)
    api
      .get('/admin/reportes', { params })
      .then((res) => {
        setReporte(res.data.data)
        setTipos(res.data.tipos)
      })
      .catch((e) => setError(Object.values(e.response?.data?.errors || {})[0]?.[0] || 'No se pudo generar el reporte.'))
  }, [filtros])

  function exportar() {
    const query = new URLSearchParams(params).toString()
    descargar(api, `/admin/reportes/pdf?${query}`, `reporte-${filtros.desde}-al-${filtros.hasta}.pdf`)
      .catch(() => setError('No se pudo exportar el PDF.'))
  }

  const r = reporte?.resumen

  return (
    <div>
      <div className="page-head">
        <div>
          <h1 className="page-title">Reportes</h1>
          <p className="page-sub">Ocupación e ingresos del período.</p>
        </div>
        {can('reporte_exportar') && reporte && (
          <div className="page-head__acciones">
            <button type="button" className="btn btn--ghost" onClick={exportar}>
              <Icono nombre="descargar" size={16} /> Exportar PDF
            </button>
          </div>
        )}
      </div>

      <div className="filtros">
        <input type="date" value={filtros.desde} aria-label="Desde" onChange={(e) => setFiltros({ ...filtros, desde: e.target.value })} />
        <input type="date" value={filtros.hasta} aria-label="Hasta" onChange={(e) => setFiltros({ ...filtros, hasta: e.target.value })} />
        <select value={filtros.tipo_habitacion_id} aria-label="Tipo de habitación" onChange={(e) => setFiltros({ ...filtros, tipo_habitacion_id: e.target.value })}>
          <option value="">Todos los tipos</option>
          {tipos.map((t) => <option key={t.id} value={t.id}>{t.nombre}</option>)}
        </select>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}
      {!reporte && !error && <p className="estado">Generando el reporte…</p>}

      {reporte && (
        <>
          <div className="cards-info">
            <div className="info-card">
              <span className="info-card__icono"><Icono nombre="ocupacion" size={20} /></span>
              <span className="info-card__label">Ocupación</span>
              <span className="info-card__value">{r.ocupacion} %</span>
              <span className="form-nota">{r.noches_vendidas} de {r.noches_disponibles} noches</span>
            </div>
            <div className="info-card">
              <span className="info-card__icono"><Icono nombre="cama" size={20} /></span>
              <span className="info-card__label">ADR</span>
              <span className="info-card__value">{formatoUSD(r.adr)}</span>
              <span className="form-nota">tarifa media por noche vendida</span>
            </div>
            <div className="info-card">
              <span className="info-card__icono"><Icono nombre="tarifa" size={20} /></span>
              <span className="info-card__label">RevPAR</span>
              <span className="info-card__value">{formatoUSD(r.revpar)}</span>
              <span className="form-nota">ingreso por noche disponible</span>
            </div>
            <div className="info-card">
              <span className="info-card__icono"><Icono nombre="dinero" size={20} /></span>
              <span className="info-card__label">Ingreso por alojamiento</span>
              <span className="info-card__value">{formatoUSD(r.ingreso_alojamiento)}</span>
              <span className="form-nota">servicios {formatoUSD(r.ingreso_servicios)} · cobrado {formatoUSD(r.ingresos_cobrados)}</span>
            </div>
          </div>

          <p className="form-nota">
            Valoración promedio del período: {r.valoraciones ? `★ ${r.valoracion_promedio} de 5 (${r.valoraciones} aprobadas)` : 'sin valoraciones aprobadas'}.
          </p>

          <section className="tarjeta-simple">
            <h2 className="seccion-title">Ocupación por día</h2>
            <div className="grafico-ocupacion" role="img" aria-label="Ocupación por día del período">
              {reporte.serie.map((d) => (
                <div key={d.fecha} className="grafico-ocupacion__dia" title={`${fechaCorta(d.fecha)}: ${d.ocupacion} % (${d.noches_vendidas} de ${d.noches_disponibles})`}>
                  <span style={{ height: `${d.ocupacion}%` }} />
                </div>
              ))}
            </div>
            <div className="grafico-ocupacion__eje">
              <span>{fechaCorta(reporte.periodo.desde)}</span>
              <span>{fechaCorta(reporte.periodo.hasta)}</span>
            </div>
          </section>

          <section className="tarjeta-simple">
            <h2 className="seccion-title">Servicios más solicitados</h2>
            {reporte.servicios.length === 0 ? (
              <p className="estado estado--vacio">No se contrataron servicios en el período.</p>
            ) : (
              <div className="tabla-wrap">
                <table className="tabla">
                  <thead>
                    <tr><th>Servicio</th><th>Cantidad</th><th>Ingreso</th></tr>
                  </thead>
                  <tbody>
                    {reporte.servicios.map((s) => (
                      <tr key={s.id}><td>{s.nombre}</td><td>{s.cantidad}</td><td>{formatoUSD(s.ingreso)}</td></tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </>
      )}
    </div>
  )
}
