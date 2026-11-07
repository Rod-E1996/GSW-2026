import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api } from '../../../api'
import { fechaCorta, hoyEnHotel } from '../../../utils'

const CLASE_ESTADO = { 1: 'cal--pendiente', 2: 'cal--confirmada', 3: 'cal--estadia', 4: 'cal--finalizada' }

function mesVecino(mes, delta) {
  const [a, m] = mes.split('-').map(Number)
  const d = new Date(Date.UTC(a, m - 1 + delta, 1))
  return d.toISOString().slice(0, 7)
}

// Vista mensual por habitacion: estadias (con su llegada y salida), bloqueos y dias libres.
export default function Calendario() {
  const navigate = useNavigate()
  const [mes, setMes] = useState(hoyEnHotel().slice(0, 7))
  const [datos, setDatos] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    setDatos(null)
    api.get('/admin/calendario', { params: { mes } }).then((res) => setDatos(res.data)).catch(() => setError('No se pudo cargar el calendario.'))
  }, [mes])

  const dias = useMemo(() => {
    if (!datos) return []
    return Array.from({ length: datos.dias }, (_, i) => {
      const fecha = `${datos.mes}-${String(i + 1).padStart(2, '0')}`
      const dia = new Date(`${fecha}T12:00:00`).getDay()
      return { fecha, numero: i + 1, finde: dia === 0 || dia === 6, letra: 'DLMMJVS'[dia] }
    })
  }, [datos])

  // Que hay en cada celda: la noche de una estadia, la manana de su salida o un bloqueo
  function celda(h, fecha) {
    const noche = datos.estadias.find((e) => e.habitacion_id === h.id && e.llegada <= fecha && fecha < e.salida)
    const sale = datos.estadias.find((e) => e.habitacion_id === h.id && e.salida === fecha)
    const bloqueo = datos.bloqueos.find((b) => b.habitacion_id === h.id && b.inicio <= fecha && fecha <= b.fin)
    return { noche, sale, bloqueo }
  }

  return (
    <div className="calendario-page">
      <div className="page-head">
        <h1 className="page-title">Calendario de ocupación</h1>
        <div className="page-head__acciones">
          <button className="btn btn--ghost" onClick={() => setMes(mesVecino(mes, -1))} aria-label="Mes anterior">←</button>
          <strong className="calendario__mes">{datos?.titulo || mes}</strong>
          <button className="btn btn--ghost" onClick={() => setMes(mesVecino(mes, 1))} aria-label="Mes siguiente">→</button>
          <button className="btn btn--ghost" onClick={() => setMes(hoyEnHotel().slice(0, 7))}>Hoy</button>
        </div>
      </div>

      <div className="calendario__leyenda">
        <span><i className="cal--pendiente" /> Pendiente</span>
        <span><i className="cal--confirmada" /> Confirmada</span>
        <span><i className="cal--estadia" /> En estadía</span>
        <span><i className="cal--finalizada" /> Finalizada</span>
        <span><i className="cal--bloqueo" /> Bloqueada</span>
        <span><b className="cal__marca">▶</b> entrada · <b className="cal__marca">◀</b> salida</span>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}
      {!datos && !error && <p className="estado">Cargando…</p>}

      {datos && (
        <>
          <div className="calendario__wrap">
            <table className="calendario">
              <thead>
                <tr>
                  <th className="calendario__hab">Hab.</th>
                  {dias.map((d) => (
                    <th key={d.fecha} className={`${d.finde ? 'is-finde' : ''} ${d.fecha === datos.hoy ? 'is-hoy' : ''}`}>
                      <span>{d.letra}</span>{d.numero}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {datos.habitaciones.map((h) => (
                  <tr key={h.id}>
                    <th className="calendario__hab" title={`${h.tipo} · ${h.estado_nombre}`}>
                      {h.numero}
                      {h.estado_habitacion !== 'disponible' && <small className={`cal__fisico cal__fisico--${h.estado_habitacion}`}>{h.estado_nombre}</small>}
                    </th>
                    {dias.map((d) => {
                      const { noche, sale, bloqueo } = celda(h, d.fecha)
                      const clase = noche ? CLASE_ESTADO[noche.estado] : bloqueo ? 'cal--bloqueo' : ''
                      const titulo = noche
                        ? `${noche.codigo} · ${noche.titular} · ${noche.estado_nombre} (${fechaCorta(noche.llegada)} → ${fechaCorta(noche.salida)})`
                        : bloqueo ? `Bloqueada: ${bloqueo.motivo || 'sin motivo'}` : sale ? `Sale ${sale.codigo}` : 'Libre'
                      return (
                        <td
                          key={d.fecha}
                          className={`${clase} ${d.fecha === datos.hoy ? 'is-hoy' : ''} ${noche ? 'is-clic' : ''}`}
                          title={titulo}
                          onClick={() => noche && navigate(`/panel/reservas/${noche.codigo}`)}
                        >
                          {sale && <b className="cal__marca cal__marca--sale">◀</b>}
                          {noche && noche.llegada === d.fecha && <b className="cal__marca">▶</b>}
                        </td>
                      )
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {datos.sin_asignar.length > 0 && (
            <section className="tarjeta-simple">
              <h3>Reservas sin habitación asignada</h3>
              <ul className="historial">
                {datos.sin_asignar.map((e) => (
                  <li key={e.codigo}>
                    <Link to={`/panel/reservas/${e.codigo}`}>{e.codigo}</Link> · {e.titular} · {fechaCorta(e.llegada)} → {fechaCorta(e.salida)} · {e.estado_nombre}
                  </li>
                ))}
              </ul>
            </section>
          )}
        </>
      )}
    </div>
  )
}
