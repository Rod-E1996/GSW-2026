import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'
import { fechaCorta, formatoUSD, hoyEnHotel, sumarDias } from '../../../utils'

const DOCUMENTOS = ['DUI', 'Pasaporte', 'Carné de residente', 'Otro']
const HUESPED_VACIO = { nombres: '', apellidos: '', tipo_documento: 'DUI', documento: '', email: '', telefono: '' }

function primerError(err, porDefecto) {
  const e = err.response?.data?.errors
  return e ? Object.values(e)[0][0] : err.response?.data?.message || porDefecto
}

// Reserva telefonica o presencial (sin codigo) y modificacion de una reserva (con codigo).
export default function ReservaForm() {
  const { codigo } = useParams()
  const editando = Boolean(codigo)
  const navigate = useNavigate()
  const hoy = hoyEnHotel()

  const [estadia, setEstadia] = useState({ fecha_llegada: hoy, fecha_salida: sumarDias(hoy, 1), adultos: 1, ninos: 0 })
  const [extra, setExtra] = useState({ hora_estimada_llegada: '', notas: '', canal: 'telefono' })
  const [habitaciones, setHabitaciones] = useState(null)
  const [habitacionId, setHabitacionId] = useState(null)
  const [buscandoHab, setBuscandoHab] = useState(false)
  const [catalogo, setCatalogo] = useState([])
  const [elegidos, setElegidos] = useState({})
  const [resumen, setResumen] = useState(null)
  const [errorResumen, setErrorResumen] = useState(null)
  const [huesped, setHuesped] = useState(null)
  const [busqueda, setBusqueda] = useState('')
  const [encontrados, setEncontrados] = useState(null)
  const [nuevoHuesped, setNuevoHuesped] = useState(null)
  const [errores, setErrores] = useState({})
  const [error, setError] = useState(null)
  const [cargando, setCargando] = useState(editando)
  const [guardando, setGuardando] = useState(false)
  const pedido = useRef(0)

  useEffect(() => {
    api.get('/servicios').then((res) => setCatalogo(res.data.data))
    if (!editando) return
    api
      .get(`/admin/reservas/${codigo}`)
      .then((res) => {
        const r = res.data.data
        if (!r.acciones.modificar) {
          setError(`La reserva está ${r.estado_nombre.toLowerCase()}: ya no se puede modificar.`)
          return
        }
        setEstadia({ fecha_llegada: r.fecha_llegada < hoy ? hoy : r.fecha_llegada, fecha_salida: r.fecha_salida, adultos: r.adultos, ninos: r.ninos || 0 })
        setExtra({ hora_estimada_llegada: r.hora_estimada_llegada || '', notas: r.notas || '', canal: r.canal })
        setHabitacionId(r.habitacion_id)
        setHuesped({ ...r.huesped, nombre_completo: `${r.huesped.nombres} ${r.huesped.apellidos}` })
        setElegidos(Object.fromEntries(r.servicios.filter((s) => !s.consumo).map((s) => [
          s.servicio_id, { cantidad: s.cantidad, fecha_servicio: s.fecha_servicio || '', hora_servicio: s.hora_servicio || '' },
        ])))
        buscarHabitaciones({ fecha_llegada: r.fecha_llegada < hoy ? hoy : r.fecha_llegada, fecha_salida: r.fecha_salida, adultos: r.adultos, ninos: r.ninos || 0 })
      })
      .catch((err) => setError(primerError(err, 'No se pudo cargar la reserva.')))
      .finally(() => setCargando(false))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [codigo])

  async function buscarHabitaciones(e = estadia) {
    setBuscandoHab(true)
    setErrores({})
    const huespedes = Number(e.adultos) + Number(e.ninos || 0)
    try {
      if (editando) {
        const res = await api.get(`/admin/reservas/${codigo}/habitaciones`, { params: { llegada: e.fecha_llegada, salida: e.fecha_salida, huespedes } })
        setHabitaciones(res.data.data)
      } else {
        const res = await api.get('/disponibilidad', { params: { llegada: e.fecha_llegada, salida: e.fecha_salida, huespedes } })
        setHabitaciones(res.data.data.map((h) => ({ id: h.id, numero: h.numero, tipo: h.tipo.nombre, capacidad: h.tipo.capacidad, precio_noche: h.precio_noche })))
      }
    } catch (err) {
      setHabitaciones([])
      setErrores(err.response?.data?.errors || {})
      setError(primerError(err, 'No se pudo consultar la disponibilidad.'))
    } finally {
      setBuscandoHab(false)
    }
  }

  function servicios() {
    return Object.entries(elegidos).map(([id, s]) => ({
      servicio_id: Number(id),
      cantidad: s.cantidad || undefined,
      fecha_servicio: s.fecha_servicio || undefined,
      hora_servicio: s.hora_servicio || undefined,
    }))
  }

  // El resumen lo calcula el servidor con las tarifas vigentes
  useEffect(() => {
    if (!habitacionId) {
      setResumen(null)
      return
    }
    const numero = ++pedido.current
    const espera = setTimeout(() => {
      api
        .post('/reservas/cotizar', { habitacion_id: habitacionId, ...estadia, servicios: servicios() })
        .then((res) => {
          if (numero !== pedido.current) return
          setResumen(res.data.data)
          setErrorResumen(null)
        })
        .catch((err) => {
          if (numero !== pedido.current) return
          setResumen(null)
          setErrorResumen(primerError(err, 'No se pudo calcular el resumen.'))
        })
    }, 250)
    return () => clearTimeout(espera)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [habitacionId, estadia, elegidos])

  function cambiarEstadia(campo, valor) {
    const nueva = { ...estadia, [campo]: valor }
    if (campo === 'fecha_llegada' && nueva.fecha_salida <= valor) nueva.fecha_salida = sumarDias(valor, 1)
    setEstadia(nueva)
    // Otras fechas u otro numero de personas: hay que volver a elegir entre lo disponible
    setHabitaciones(null)
    if (!editando) setHabitacionId(null)
  }

  function alternar(s) {
    setElegidos((actual) => {
      const copia = { ...actual }
      if (copia[s.id]) delete copia[s.id]
      else copia[s.id] = { cantidad: s.forma_cobro === 'fijo' ? 1 : null, fecha_servicio: '', hora_servicio: '' }
      return copia
    })
  }

  const cambiarServicio = (id, campo, valor) => setElegidos((a) => ({ ...a, [id]: { ...a[id], [campo]: valor } }))

  async function buscarHuesped(e) {
    e.preventDefault()
    if (busqueda.trim().length < 2) return
    try {
      const res = await api.get('/admin/huespedes', { params: { q: busqueda.trim() } })
      setEncontrados(res.data.data)
    } catch (err) {
      setError(primerError(err, 'No se pudo buscar.'))
    }
  }

  async function registrarHuesped(e) {
    e.preventDefault()
    setErrores({})
    try {
      const res = await api.post('/admin/huespedes', nuevoHuesped)
      setHuesped(res.data.data)
      setNuevoHuesped(null)
      setEncontrados(null)
    } catch (err) {
      const data = err.response?.data
      if (data?.existente) {
        setHuesped(data.existente)
        setNuevoHuesped(null)
        setError(`${data.message} La elegimos como titular.`)
        return
      }
      setErrores(data?.errors || {})
    }
  }

  async function guardar(e) {
    e.preventDefault()
    setGuardando(true)
    setError(null)
    setErrores({})
    const cuerpo = {
      habitacion_id: habitacionId,
      ...estadia,
      hora_estimada_llegada: extra.hora_estimada_llegada || undefined,
      notas: extra.notas || undefined,
      servicios: servicios(),
    }
    try {
      const res = editando
        ? await api.put(`/admin/reservas/${codigo}`, cuerpo)
        : await api.post('/admin/reservas', { ...cuerpo, huesped_id: huesped?.id, canal: extra.canal })
      navigate(`/panel/reservas/${res.data.data.codigo}`, { state: { mensaje: res.data.message } })
    } catch (err) {
      const data = err.response?.data
      if (err.response?.status === 409) {
        setError(`${data.message} Elige otra habitación.`)
        buscarHabitaciones()
      } else {
        setErrores(data?.errors || {})
        setError(primerError(err, 'No se pudo guardar.'))
      }
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } finally {
      setGuardando(false)
    }
  }

  const campoError = (campo) => errores[campo] && <small className="form-error">{errores[campo][0]}</small>
  const volver = editando ? `/panel/reservas/${codigo}` : '/panel/reservas'

  if (cargando) return <p className="estado">Cargando…</p>

  return (
    <div className="form-page reserva-admin">
      <Link to={volver} className="reserva__volver">← {editando ? `Reserva ${codigo}` : 'Reservas'}</Link>
      <h1 className="page-title">{editando ? `Modificar ${codigo}` : 'Nueva reserva'}</h1>
      <p className="page-sub">
        {editando
          ? 'Al guardar se revalida la disponibilidad, se recalculan los importes y se avisa al huésped por correo.'
          : 'Reserva tomada por teléfono o en recepción. Queda pendiente hasta registrar el anticipo.'}
      </p>

      {error && <div className="alerta alerta--error">{error}</div>}

      {(!editando || habitaciones !== null || habitacionId) && (
        <form className="reserva-admin__grid" onSubmit={guardar}>
          <div className="reserva-admin__columna">
            <section className="form form--seccion">
              <h2 className="seccion-title">1. Estadía</h2>
              <div className="form-row">
                <label>
                  Llegada
                  <input type="date" min={hoy} value={estadia.fecha_llegada} onChange={(e) => cambiarEstadia('fecha_llegada', e.target.value)} />
                  {campoError('fecha_llegada')}
                </label>
                <label>
                  Salida
                  <input type="date" min={sumarDias(estadia.fecha_llegada, 1)} value={estadia.fecha_salida} onChange={(e) => cambiarEstadia('fecha_salida', e.target.value)} />
                  {campoError('fecha_salida') || campoError('salida')}
                </label>
                <label>
                  Adultos
                  <input type="number" min="1" max="20" value={estadia.adultos} onChange={(e) => cambiarEstadia('adultos', e.target.value)} />
                  {campoError('adultos')}
                </label>
                <label>
                  Niños
                  <input type="number" min="0" max="20" value={estadia.ninos} onChange={(e) => cambiarEstadia('ninos', e.target.value)} />
                </label>
              </div>
              <div className="form-acciones form-acciones--izq">
                <button type="button" className="btn btn--ghost" onClick={() => buscarHabitaciones()} disabled={buscandoHab}>
                  {buscandoHab ? 'Buscando…' : habitaciones ? 'Volver a buscar' : 'Ver habitaciones disponibles'}
                </button>
              </div>

              {habitaciones && (
                habitaciones.length === 0 ? (
                  <p className="form-nota">No hay habitaciones libres para esas fechas y personas.</p>
                ) : (
                  <div className="habitaciones-elegibles" role="radiogroup" aria-label="Habitación">
                    {habitaciones.map((h) => (
                      <label key={h.id} className={`habitacion-opcion ${habitacionId === h.id ? 'is-elegida' : ''}`}>
                        <input type="radio" name="habitacion" checked={habitacionId === h.id} onChange={() => setHabitacionId(h.id)} />
                        <strong>{h.numero}</strong>
                        <span>{h.tipo} · hasta {h.capacidad}</span>
                        {h.precio_noche !== undefined && <span className="form-nota">{formatoUSD(h.precio_noche)} / noche</span>}
                        {h.actual && <span className="badge badge--info">Actual</span>}
                      </label>
                    ))}
                  </div>
                )
              )}
              {campoError('habitacion_id')}
            </section>

            <section className="form form--seccion">
              <h2 className="seccion-title">2. Huésped titular</h2>
              {huesped ? (
                <div className="huesped-elegido">
                  <div>
                    <strong>{huesped.nombre_completo}</strong>
                    <span className="form-nota">
                      {[huesped.tipo_documento && `${huesped.tipo_documento} ${huesped.documento || ''}`, huesped.email, huesped.telefono].filter(Boolean).join(' · ')}
                    </span>
                    {huesped.tiene_cuenta && <span className="badge badge--info">Tiene cuenta en el portal</span>}
                  </div>
                  {!editando && <button type="button" className="btn btn--sm btn--ghost" onClick={() => setHuesped(null)}>Cambiar</button>}
                </div>
              ) : nuevoHuesped ? (
                <div className="form-subform">
                  <div className="form-row">
                    <label>Nombres<input value={nuevoHuesped.nombres} onChange={(e) => setNuevoHuesped({ ...nuevoHuesped, nombres: e.target.value })} />{campoError('nombres')}</label>
                    <label>Apellidos<input value={nuevoHuesped.apellidos} onChange={(e) => setNuevoHuesped({ ...nuevoHuesped, apellidos: e.target.value })} />{campoError('apellidos')}</label>
                  </div>
                  <div className="form-row">
                    <label>
                      Tipo de documento
                      <select value={nuevoHuesped.tipo_documento} onChange={(e) => setNuevoHuesped({ ...nuevoHuesped, tipo_documento: e.target.value })}>
                        {DOCUMENTOS.map((d) => <option key={d}>{d}</option>)}
                      </select>
                    </label>
                    <label>Número de documento<input value={nuevoHuesped.documento} onChange={(e) => setNuevoHuesped({ ...nuevoHuesped, documento: e.target.value })} />{campoError('documento')}</label>
                  </div>
                  <div className="form-row">
                    <label>Correo<input type="email" value={nuevoHuesped.email} onChange={(e) => setNuevoHuesped({ ...nuevoHuesped, email: e.target.value })} />{campoError('email')}</label>
                    <label>Teléfono<input value={nuevoHuesped.telefono} onChange={(e) => setNuevoHuesped({ ...nuevoHuesped, telefono: e.target.value })} />{campoError('telefono')}</label>
                  </div>
                  <small className="form-nota">Con correo, el huésped recibe el aviso de la reserva y el comprobante.</small>
                  <div className="form-acciones form-acciones--izq">
                    <button type="button" className="btn btn--ghost" onClick={() => setNuevoHuesped(null)}>Cancelar</button>
                    <button type="button" className="btn" onClick={registrarHuesped}>Registrar huésped</button>
                  </div>
                </div>
              ) : (
                <>
                  <div className="buscador-huesped">
                    <input
                      placeholder="Documento, correo, teléfono o nombre"
                      value={busqueda}
                      onChange={(e) => setBusqueda(e.target.value)}
                      onKeyDown={(e) => e.key === 'Enter' && buscarHuesped(e)}
                      aria-label="Buscar huésped"
                    />
                    <button type="button" className="btn btn--ghost" onClick={buscarHuesped}>Buscar</button>
                    <button type="button" className="btn btn--ghost" onClick={() => setNuevoHuesped({ ...HUESPED_VACIO })}>+ Nuevo huésped</button>
                  </div>
                  {encontrados && (
                    encontrados.length === 0 ? (
                      <p className="form-nota">Nadie coincide. Registra al huésped como nuevo.</p>
                    ) : (
                      <ul className="lista-huespedes">
                        {encontrados.map((h) => (
                          <li key={h.id}>
                            <span>
                              <strong>{h.nombre_completo}</strong>
                              <small className="form-nota"> {[h.documento && `${h.tipo_documento} ${h.documento}`, h.email, h.telefono].filter(Boolean).join(' · ')} · {h.reservas} reserva(s)</small>
                            </span>
                            <button type="button" className="btn btn--sm" onClick={() => setHuesped(h)}>Elegir</button>
                          </li>
                        ))}
                      </ul>
                    )
                  )}
                  {campoError('huesped_id')}
                </>
              )}
              {!editando && (
                <label>
                  Canal
                  <select value={extra.canal} onChange={(e) => setExtra({ ...extra, canal: e.target.value })}>
                    <option value="telefono">Teléfono</option>
                    <option value="presencial">Presencial</option>
                  </select>
                </label>
              )}
            </section>

            <section className="form form--seccion">
              <h2 className="seccion-title">3. Servicios y notas</h2>
              <ul className="servicios-elegibles">
                {catalogo.map((s) => {
                  const elegido = elegidos[s.id]
                  return (
                    <li key={s.id} className={elegido ? 'is-elegido' : ''}>
                      <label className="form-check">
                        <input type="checkbox" checked={Boolean(elegido)} onChange={() => alternar(s)} />
                        <span><strong>{s.nombre}</strong> · {formatoUSD(s.precio)} {s.forma_cobro_nombre.toLowerCase()}</span>
                      </label>
                      {elegido && (s.forma_cobro === 'fijo' || s.requiere_fecha) && (
                        <div className="servicios-elegibles__detalle">
                          {s.forma_cobro === 'fijo' && (
                            <label>Cantidad<input type="number" min="1" max="20" value={elegido.cantidad ?? ''} onChange={(e) => cambiarServicio(s.id, 'cantidad', e.target.value)} /></label>
                          )}
                          {s.requiere_fecha && (
                            <>
                              <label>Fecha<input type="date" min={estadia.fecha_llegada} max={estadia.fecha_salida} value={elegido.fecha_servicio} onChange={(e) => cambiarServicio(s.id, 'fecha_servicio', e.target.value)} /></label>
                              <label>Hora<input type="time" value={elegido.hora_servicio} onChange={(e) => cambiarServicio(s.id, 'hora_servicio', e.target.value)} /></label>
                            </>
                          )}
                        </div>
                      )}
                    </li>
                  )
                })}
              </ul>
              <div className="form-row">
                <label>
                  Hora estimada de llegada
                  <input type="time" value={extra.hora_estimada_llegada} onChange={(e) => setExtra({ ...extra, hora_estimada_llegada: e.target.value })} />
                </label>
              </div>
              <label>
                Notas
                <textarea rows="2" maxLength="1000" value={extra.notas} onChange={(e) => setExtra({ ...extra, notas: e.target.value })} />
              </label>
            </section>
          </div>

          <aside className="tarjeta-simple reserva-admin__resumen">
            <h3>Resumen</h3>
            {!habitacionId && <p className="form-nota">Elige una habitación para ver los importes.</p>}
            {errorResumen && <div className="alerta alerta--error">{errorResumen}</div>}
            {resumen && (
              <>
              <p className="reserva-admin__estadia">
                <strong>{resumen.habitacion.tipo} · {resumen.habitacion.numero}</strong>
                <span className="form-nota">{fechaCorta(estadia.fecha_llegada)} → {fechaCorta(estadia.fecha_salida)} · {resumen.noches.length} {resumen.noches.length === 1 ? 'noche' : 'noches'}</span>
              </p>
              <dl className="importes">
                <dt>Alojamiento</dt><dd>{formatoUSD(resumen.subtotal_alojamiento)}</dd>
                {resumen.servicios.map((s) => (
                  <div key={s.servicio_id} className="importes__linea"><dt>{s.nombre} × {s.cantidad}</dt><dd>{formatoUSD(s.subtotal)}</dd></div>
                ))}
                <dt>Impuestos ({resumen.impuesto_porcentaje} %)</dt><dd>{formatoUSD(resumen.impuesto)}</dd>
                <dt className="importes__total">Total</dt><dd className="importes__total">{formatoUSD(resumen.total)}</dd>
                <dt className="importes__anticipo">Anticipo ({resumen.anticipo_porcentaje} %)</dt><dd className="importes__anticipo">{formatoUSD(resumen.anticipo_requerido)}</dd>
              </dl>
              </>
            )}
            <button className="btn btn--block" disabled={guardando || !habitacionId || !resumen || (!editando && !huesped)}>
              {guardando ? 'Guardando…' : editando ? 'Guardar cambios' : 'Registrar reserva'}
            </button>
            {!editando && !huesped && <small className="form-nota">Falta elegir al huésped titular.</small>}
          </aside>
        </form>
      )}
    </div>
  )
}
