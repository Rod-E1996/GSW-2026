import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { EstadoVacio, Indicador } from '../../../components/ui'
import { fechaCorta, hoyEnHotel } from '../../../utils'
import '../../../styles/calendario.css'

const CLASE_ESTADO = { 1: 'pendiente', 2: 'confirmada', 3: 'estadia', 4: 'finalizada' }
const LEYENDA = [
  ['pendiente', 'Pendiente'],
  ['confirmada', 'Confirmada'],
  ['estadia', 'En estadía'],
  ['finalizada', 'Finalizada'],
  ['bloqueo', 'Bloqueada'],
]
const MOTIVOS = { mantenimiento: 'Mantenimiento', uso_interno: 'Uso interno' }

function mesVecino(mes, delta) {
  const [a, m] = mes.split('-').map(Number)
  const d = new Date(Date.UTC(a, m - 1 + delta, 1))
  return d.toISOString().slice(0, 7)
}

/**
 * Linea de tiempo mensual por habitacion. Cada dia ocupa dos columnas (manana y tarde):
 * una estadia empieza la tarde de su llegada y termina la manana de su salida, asi que
 * una salida y una entrada el mismo dia se ven una a continuacion de la otra.
 */
export default function Calendario() {
  const navigate = useNavigate()
  const { can } = useAuth()
  const [mes, setMes] = useState(hoyEnHotel().slice(0, 7))
  const [datos, setDatos] = useState(null)
  const [error, setError] = useState(null)
  const [tipo, setTipo] = useState('')

  useEffect(() => {
    setDatos(null)
    setError(null)
    api.get('/admin/calendario', { params: { mes } })
      .then((res) => setDatos(res.data))
      .catch(() => setError('No se pudo cargar el calendario.'))
  }, [mes])

  const dias = useMemo(() => {
    if (!datos) return []
    return Array.from({ length: datos.dias }, (_, i) => {
      const fecha = `${datos.mes}-${String(i + 1).padStart(2, '0')}`
      const dia = new Date(`${fecha}T12:00:00`).getDay()
      return { fecha, numero: i + 1, finde: dia === 0 || dia === 6, letra: 'DLMMJVS'[dia] }
    })
  }, [datos])

  const tipos = useMemo(() => [...new Set((datos?.habitaciones || []).map((h) => h.tipo).filter(Boolean))], [datos])

  const habitaciones = useMemo(
    () => (datos?.habitaciones || []).filter((h) => !tipo || h.tipo === tipo),
    [datos, tipo]
  )

  // Habitaciones agrupadas por piso
  const pisos = useMemo(() => {
    const grupos = new Map()
    habitaciones.forEach((h) => {
      const piso = h.piso ?? String(h.numero).charAt(0)
      if (!grupos.has(piso)) grupos.set(piso, [])
      grupos.get(piso).push(h)
    })
    return [...grupos.entries()]
  }, [habitaciones])

  const resumen = useMemo(() => {
    if (!datos) return null
    const ids = new Set(habitaciones.map((h) => h.id))
    const estadias = datos.estadias.filter((e) => ids.has(e.habitacion_id))
    const primero = dias[0]?.fecha
    const ultimo = dias[dias.length - 1]?.fecha
    const porDia = dias.map((d) => estadias.filter((e) => e.llegada <= d.fecha && d.fecha < e.salida).length)
    const noches = porDia.reduce((a, b) => a + b, 0)
    const capacidad = habitaciones.length * dias.length
    return {
      porDia,
      noches,
      ocupacion: capacidad ? Math.round((noches / capacidad) * 100) : 0,
      llegadas: [...estadias, ...datos.sin_asignar].filter((e) => e.llegada >= primero && e.llegada <= ultimo).length,
      bloqueos: datos.bloqueos.filter((b) => ids.has(b.habitacion_id)).length,
    }
  }, [datos, habitaciones, dias])

  // Columnas de la barra de una estadia dentro del mes (grid de 2 columnas por dia, base 1)
  function columnasEstadia(e) {
    const n = dias.length
    const primero = dias[0].fecha
    const ultimo = dias[n - 1].fecha
    const indice = (fecha) => Number(fecha.slice(8, 10)) - 1
    const empiezaAntes = e.llegada < primero
    const terminaDespues = e.salida > ultimo
    const inicio = empiezaAntes ? 1 : indice(e.llegada) * 2 + 2
    const fin = terminaDespues ? n * 2 + 1 : indice(e.salida) * 2 + 2
    return { inicio, fin: Math.max(fin, inicio + 1), empiezaAntes, terminaDespues }
  }

  function columnasBloqueo(b) {
    const n = dias.length
    const inicio = b.inicio < dias[0].fecha ? 1 : (Number(b.inicio.slice(8, 10)) - 1) * 2 + 1
    const fin = b.fin > dias[n - 1].fecha ? n * 2 + 1 : (Number(b.fin.slice(8, 10)) - 1) * 2 + 3
    return { inicio, fin }
  }

  const columnas = dias.length * 2
  const pista = { gridTemplateColumns: `repeat(${columnas}, minmax(0, 1fr))` }

  function irANueva(fecha) {
    if (can('reserva_store') && fecha >= hoyEnHotel()) navigate(`/panel/reservas/nueva?llegada=${fecha}`)
  }

  return (
    <div className="calendario-page">
      <div className="page-head">
        <div>
          <h1 className="page-title">Calendario de ocupación</h1>
          <p className="page-sub">Quién ocupa cada habitación, noche por noche.</p>
        </div>
        <div className="page-head__acciones">
          <div className="cal-mes" role="group" aria-label="Mes">
            <button type="button" onClick={() => setMes(mesVecino(mes, -1))} aria-label="Mes anterior">‹</button>
            <strong>{datos?.titulo || '…'}</strong>
            <button type="button" onClick={() => setMes(mesVecino(mes, 1))} aria-label="Mes siguiente">›</button>
          </div>
          <button className="btn btn--ghost" onClick={() => setMes(hoyEnHotel().slice(0, 7))}>Hoy</button>
          {can('reserva_store') && <Link to="/panel/reservas/nueva" className="btn">+ Nueva reserva</Link>}
        </div>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}

      {resumen && (
        <div className="indicadores">
          <Indicador icono="ocupacion" etiqueta="Ocupación del mes" valor={`${resumen.ocupacion} %`} nota={`${habitaciones.length} habitaciones`} />
          <Indicador icono="cama" tono="azul" etiqueta="Noches ocupadas" valor={resumen.noches} nota={`de ${habitaciones.length * dias.length} posibles`} />
          <Indicador icono="entrada" tono="ok" etiqueta="Llegadas del mes" valor={resumen.llegadas} />
          <Indicador icono="alerta" tono="mango" etiqueta="Bloqueos" valor={resumen.bloqueos} nota="mantenimiento o uso interno" />
        </div>
      )}

      <div className="cal-barra">
        <select value={tipo} onChange={(e) => setTipo(e.target.value)} aria-label="Tipo de habitación">
          <option value="">Todos los tipos</option>
          {tipos.map((t) => <option key={t} value={t}>{t}</option>)}
        </select>
        <div className="cal-leyenda">
          {LEYENDA.map(([clase, nombre]) => (
            <span key={clase}><i className={`cal-muestra cal-muestra--${clase}`} />{nombre}</span>
          ))}
        </div>
      </div>

      {!datos && !error && (
        <div className="cal">
          <div className="cal__cargando"><span className="esqueleto" /><span className="esqueleto" /><span className="esqueleto" /></div>
        </div>
      )}

      {datos && habitaciones.length === 0 && (
        <div className="cal">
          <EstadoVacio icono="puerta" titulo="No hay habitaciones" texto="Registra habitaciones o cambia el filtro de tipo." />
        </div>
      )}

      {datos && habitaciones.length > 0 && (
        <div className="cal" style={{ '--cal-dias': dias.length }}>
          <div className="cal__scroll">
            {/* Encabezado de dias */}
            <div className="cal__fila cal__fila--cabecera">
              <div className="cal__etiqueta cal__etiqueta--cabecera">Habitación</div>
              <div className="cal__pista" style={pista}>
                {dias.map((d, i) => (
                  <div key={d.fecha} style={{ gridColumn: `${i * 2 + 1} / span 2` }} className={`cal__dia ${d.finde ? 'is-finde' : ''} ${d.fecha === datos.hoy ? 'is-hoy' : ''}`}>
                    <span>{d.letra}</span>
                    <strong>{d.numero}</strong>
                  </div>
                ))}
              </div>
            </div>

            {pisos.map(([piso, lista]) => (
              <div key={piso} className="cal__grupo">
                <div className="cal__piso">Piso {piso} <span>{lista.length} habitaciones</span></div>
                {lista.map((h) => (
                  <div key={h.id} className="cal__fila">
                    <div className="cal__etiqueta">
                      <strong>{h.numero}</strong>
                      <small>{h.tipo}</small>
                      {h.estado_habitacion !== 'disponible' && (
                        <span className={`cal__fisico cal__fisico--${h.estado_habitacion}`} title={h.estado_nombre}>{h.estado_nombre}</span>
                      )}
                    </div>
                    <div className="cal__pista" style={pista}>
                      {dias.map((d, i) => (
                        <button
                          key={d.fecha}
                          style={{ gridColumn: `${i * 2 + 1} / span 2` }}
                          type="button"
                          tabIndex={-1}
                          className={`cal__celda ${d.finde ? 'is-finde' : ''} ${d.fecha === datos.hoy ? 'is-hoy' : ''}`}
                          title={`${h.numero} · ${fechaCorta(d.fecha)}`}
                          onClick={() => irANueva(d.fecha)}
                        />
                      ))}

                      {datos.bloqueos.filter((b) => b.habitacion_id === h.id).map((b, i) => {
                        const c = columnasBloqueo(b)
                        return (
                          <div
                            key={`b${i}`}
                            className="cal__bloqueo"
                            style={{ gridColumn: `${c.inicio} / ${c.fin}` }}
                            title={`Bloqueada · ${MOTIVOS[b.motivo] || 'sin motivo'} (${fechaCorta(b.inicio)} → ${fechaCorta(b.fin)})`}
                          >
                            <span>{MOTIVOS[b.motivo] || 'Bloqueada'}</span>
                          </div>
                        )
                      })}

                      {datos.estadias.filter((e) => e.habitacion_id === h.id).map((e) => {
                        const c = columnasEstadia(e)
                        return (
                          <Link
                            key={e.codigo}
                            to={`/panel/reservas/${e.codigo}`}
                            className={`cal__estadia cal__estadia--${CLASE_ESTADO[e.estado] || 'finalizada'} ${c.empiezaAntes ? 'is-continua-izq' : ''} ${c.terminaDespues ? 'is-continua-der' : ''}`}
                            style={{ gridColumn: `${c.inicio} / ${c.fin}` }}
                            title={`${e.codigo} · ${e.titular || 'Sin titular'}\n${e.estado_nombre} · ${fechaCorta(e.llegada)} → ${fechaCorta(e.salida)}`}
                          >
                            <strong>{e.titular || e.codigo}</strong>
                            <span>{e.codigo}</span>
                          </Link>
                        )
                      })}
                    </div>
                  </div>
                ))}
              </div>
            ))}

            {/* Ocupacion por noche */}
            <div className="cal__fila cal__fila--pie">
              <div className="cal__etiqueta cal__etiqueta--cabecera">Ocupadas</div>
              <div className="cal__pista" style={pista}>
                {dias.map((d, i) => {
                  const ocupadas = resumen.porDia[i]
                  const nivel = habitaciones.length ? ocupadas / habitaciones.length : 0
                  return (
                    <div
                      key={d.fecha}
                      className={`cal__ocupacion ${d.fecha === datos.hoy ? 'is-hoy' : ''}`}
                      style={{ '--nivel': nivel, gridColumn: `${i * 2 + 1} / span 2` }}
                      title={`${fechaCorta(d.fecha)}: ${ocupadas} de ${habitaciones.length} habitaciones ocupadas`}
                    >
                      {ocupadas}
                    </div>
                  )
                })}
              </div>
            </div>
          </div>
        </div>
      )}

      {datos && datos.sin_asignar.length > 0 && (
        <section className="tarjeta-simple cal-sin-asignar">
          <h3><Icono nombre="alerta" size={18} /> Reservas sin habitación asignada <span className="panel-lista__conteo">{datos.sin_asignar.length}</span></h3>
          <ul>
            {datos.sin_asignar.map((e) => (
              <li key={e.codigo}>
                <Link to={`/panel/reservas/${e.codigo}`}><strong>{e.codigo}</strong> · {e.titular}</Link>
                <span className="form-nota">{fechaCorta(e.llegada)} → {fechaCorta(e.salida)}</span>
                <span className={`cal-muestra cal-muestra--${CLASE_ESTADO[e.estado] || 'finalizada'}`} title={e.estado_nombre} />
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  )
}
