import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { api } from '../../api'
import { useAuth } from '../../auth/AuthContext'
import AvisoVerificacion from '../../components/AvisoVerificacion'
import BarraPublica from '../../components/BarraPublica'
import { fechaCorta, formatoUSD, irAPasarela, rutaImagen } from '../../utils'

const DOCUMENTOS = ['DUI', 'Pasaporte', 'Carné de residente', 'Otro']

// Pantalla de reserva: datos del titular, servicios y resumen en una sola vista.
export default function Reservar() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const { user } = useAuth()

  const estadia = {
    habitacion_id: params.get('habitacion'),
    fecha_llegada: params.get('llegada'),
    fecha_salida: params.get('salida'),
  }
  const huespedesBuscados = Number(params.get('huespedes')) || 1

  const [titular, setTitular] = useState({ nombres: '', apellidos: '', tipo_documento: 'DUI', documento: '', telefono: '' })
  const [personas, setPersonas] = useState({ adultos: huespedesBuscados, ninos: 0 })
  const [extra, setExtra] = useState({ hora_estimada_llegada: '', notas: '' })
  const [catalogo, setCatalogo] = useState([])
  const [elegidos, setElegidos] = useState({})
  const [resumen, setResumen] = useState(null)
  const [erroresResumen, setErroresResumen] = useState(null)
  const [errores, setErrores] = useState({})
  const [error, setError] = useState(null)
  const [conflicto, setConflicto] = useState(null)
  const [enviando, setEnviando] = useState(false)
  const pedido = useRef(0)

  const faltanDatos = !estadia.habitacion_id || !estadia.fecha_llegada || !estadia.fecha_salida

  useEffect(() => {
    if (faltanDatos) return
    api.get('/servicios').then((res) => setCatalogo(res.data.data))
    api.get('/mi-cuenta/huesped').then((res) => {
      const f = res.data.data
      setTitular({
        nombres: f.nombres || '',
        apellidos: f.apellidos || '',
        tipo_documento: f.tipo_documento || 'DUI',
        documento: f.documento || '',
        telefono: f.telefono || '',
      })
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  function servicios() {
    return Object.entries(elegidos).map(([id, s]) => ({
      servicio_id: Number(id),
      cantidad: s.cantidad || undefined,
      fecha_servicio: s.fecha_servicio || undefined,
      hora_servicio: s.hora_servicio || undefined,
    }))
  }

  // El resumen se recalcula en el servidor cada vez que cambia algo que afecta el precio
  useEffect(() => {
    if (faltanDatos) return
    const numero = ++pedido.current
    const espera = setTimeout(() => {
      api
        .post('/reservas/cotizar', { ...estadia, ...personas, servicios: servicios() })
        .then((res) => {
          if (numero !== pedido.current) return
          setResumen(res.data.data)
          setErroresResumen(null)
        })
        .catch((err) => {
          if (numero !== pedido.current) return
          const e = err.response?.data?.errors
          setErroresResumen(e ? Object.values(e)[0][0] : 'No se pudo calcular el resumen.')
        })
    }, 250)
    return () => clearTimeout(espera)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [personas, elegidos])

  function alternar(servicio) {
    setElegidos((actual) => {
      const copia = { ...actual }
      if (copia[servicio.id]) delete copia[servicio.id]
      else copia[servicio.id] = { cantidad: servicio.forma_cobro === 'fijo' ? 1 : null, fecha_servicio: '', hora_servicio: '' }
      return copia
    })
  }

  function cambiarServicio(id, campo, valor) {
    setElegidos((actual) => ({ ...actual, [id]: { ...actual[id], [campo]: valor } }))
  }

  async function confirmar(e) {
    e.preventDefault()
    setEnviando(true)
    setErrores({})
    setError(null)
    setConflicto(null)
    try {
      const res = await api.post('/reservas', {
        ...estadia,
        ...personas,
        ...titular,
        ...extra,
        hora_estimada_llegada: extra.hora_estimada_llegada || undefined,
        servicios: servicios(),
      })
      const codigo = res.data.data.codigo
      // Paso 5 del caso de uso: el anticipo se paga en la pasarela
      try {
        const pago = await api.post(`/mi-cuenta/reservas/${codigo}/pagar`)
        irAPasarela(pago.data.data.url, navigate)
      } catch {
        // La reserva ya quedo registrada: se paga desde su detalle
        navigate(`/mi-cuenta/reservas/${codigo}?nueva=1`)
      }
    } catch (err) {
      const data = err.response?.data
      if (err.response?.status === 409) {
        setConflicto(data)
      } else if (err.response?.status === 422 && data?.errors) {
        setErrores(data.errors)
        setError('Revisa los datos marcados.')
      } else {
        setError(data?.message || 'No se pudo registrar la reserva.')
      }
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } finally {
      setEnviando(false)
    }
  }

  const campoError = (campo) => errores[campo] && <small className="campo-error">{errores[campo][0]}</small>
  const volverAResultados = `/disponibilidad?llegada=${estadia.fecha_llegada}&salida=${estadia.fecha_salida}&huespedes=${personas.adultos + Number(personas.ninos || 0)}`

  if (faltanDatos) {
    return (
      <div className="app">
        <BarraPublica />
        <main className="pagina-simple">
          <div className="tarjeta-simple">
            <h1>Faltan datos de la reserva</h1>
            <p>Elige primero las fechas y la habitación.</p>
            <Link to="/" className="btn">Buscar disponibilidad</Link>
          </div>
        </main>
      </div>
    )
  }

  const capacidad = resumen?.habitacion.capacidad
  const servicioError = (i, campo) => errores[`servicios.${i}.${campo}`]?.[0]
  const indice = (id) => Object.keys(elegidos).indexOf(String(id))

  return (
    <div className="app">
      <BarraPublica />
      <main className="reserva">
        <div className="reserva__encabezado">
          <Link to={volverAResultados} className="reserva__volver">← Volver a los resultados</Link>
          <h1>Completa tu reserva</h1>
        </div>

        <AvisoVerificacion />
        {error && <div className="alerta alerta--error">{error}</div>}
        {conflicto && (
          <div className="alerta alerta--error">
            <p>{conflicto.message}</p>
            {conflicto.alternativas?.tipos?.length > 0 && (
              <p>
                Aún hay disponibilidad en las mismas fechas:{' '}
                {conflicto.alternativas.tipos.map((t) => `${t.nombre} (${formatoUSD(t.precio_noche)} / noche)`).join(', ')}.
              </p>
            )}
            <Link to={volverAResultados} className="btn btn--sm">Ver habitaciones disponibles</Link>
          </div>
        )}
        {resumen && !resumen.disponible && !conflicto && (
          <div className="alerta alerta--error">
            Esta habitación ya no está disponible para esas fechas. <Link to={volverAResultados}>Elige otra</Link>.
          </div>
        )}

        <form className="reserva__grid" onSubmit={confirmar}>
          <div className="reserva__formulario">
            <section className="form form--seccion">
              <h2 className="seccion-title">Datos del huésped</h2>
              <div className="form-row">
                <label>
                  Nombres
                  <input value={titular.nombres} onChange={(e) => setTitular({ ...titular, nombres: e.target.value })} autoComplete="given-name" />
                  {campoError('nombres')}
                </label>
                <label>
                  Apellidos
                  <input value={titular.apellidos} onChange={(e) => setTitular({ ...titular, apellidos: e.target.value })} autoComplete="family-name" />
                  {campoError('apellidos')}
                </label>
              </div>
              <div className="form-row">
                <label>
                  Tipo de documento
                  <select value={titular.tipo_documento} onChange={(e) => setTitular({ ...titular, tipo_documento: e.target.value })}>
                    {DOCUMENTOS.map((d) => <option key={d}>{d}</option>)}
                  </select>
                  {campoError('tipo_documento')}
                </label>
                <label>
                  Número de documento
                  <input value={titular.documento} onChange={(e) => setTitular({ ...titular, documento: e.target.value })} />
                  {campoError('documento')}
                </label>
              </div>
              <div className="form-row">
                <label>
                  Correo
                  <input value={user?.email || ''} disabled />
                  <small className="form-nota">Es el de tu cuenta; cámbialo en Mi cuenta.</small>
                </label>
                <label>
                  Teléfono
                  <input type="tel" value={titular.telefono} onChange={(e) => setTitular({ ...titular, telefono: e.target.value })} autoComplete="tel" />
                  {campoError('telefono')}
                </label>
              </div>
              <div className="form-row">
                <label>
                  Adultos
                  <input
                    type="number"
                    min="1"
                    max={capacidad || 20}
                    value={personas.adultos}
                    onChange={(e) => setPersonas({ ...personas, adultos: Number(e.target.value) || 1 })}
                  />
                  {campoError('adultos')}
                </label>
                <label>
                  Niños
                  <input
                    type="number"
                    min="0"
                    max={capacidad || 20}
                    value={personas.ninos}
                    onChange={(e) => setPersonas({ ...personas, ninos: Number(e.target.value) || 0 })}
                  />
                </label>
              </div>
              <div className="form-row">
                <label>
                  Hora estimada de llegada (opcional)
                  <input type="time" value={extra.hora_estimada_llegada} onChange={(e) => setExtra({ ...extra, hora_estimada_llegada: e.target.value })} />
                  {campoError('hora_estimada_llegada')}
                </label>
                <label>
                  Notas para el hotel (opcional)
                  <input value={extra.notas} maxLength="1000" onChange={(e) => setExtra({ ...extra, notas: e.target.value })} />
                </label>
              </div>
            </section>

            <section className="form form--seccion">
              <h2 className="seccion-title">Servicios complementarios</h2>
              {catalogo.length === 0 && <p className="estado">No hay servicios disponibles.</p>}
              <ul className="servicios-elegibles">
                {catalogo.map((s) => {
                  const elegido = elegidos[s.id]
                  const i = indice(s.id)
                  return (
                    <li key={s.id} className={elegido ? 'is-elegido' : ''}>
                      <label className="form-check">
                        <input type="checkbox" checked={Boolean(elegido)} onChange={() => alternar(s)} />
                        <span>
                          <strong>{s.nombre}</strong> · {formatoUSD(s.precio)} {s.forma_cobro_nombre.toLowerCase()}
                          {s.descripcion && <small className="form-nota"> — {s.descripcion}</small>}
                        </span>
                      </label>
                      {elegido && (s.forma_cobro === 'fijo' || s.requiere_fecha) && (
                        <div className="servicios-elegibles__detalle">
                          {s.forma_cobro === 'fijo' && (
                            <label>
                              Cantidad
                              <input
                                type="number"
                                min="1"
                                max="20"
                                value={elegido.cantidad || 1}
                                onChange={(e) => cambiarServicio(s.id, 'cantidad', Number(e.target.value) || 1)}
                              />
                            </label>
                          )}
                          {s.requiere_fecha && (
                            <>
                              <label>
                                Fecha
                                <input
                                  type="date"
                                  min={estadia.fecha_llegada}
                                  max={estadia.fecha_salida}
                                  value={elegido.fecha_servicio}
                                  onChange={(e) => cambiarServicio(s.id, 'fecha_servicio', e.target.value)}
                                />
                              </label>
                              <label>
                                Hora (opcional)
                                <input type="time" value={elegido.hora_servicio} onChange={(e) => cambiarServicio(s.id, 'hora_servicio', e.target.value)} />
                              </label>
                            </>
                          )}
                          {i >= 0 && (servicioError(i, 'fecha_servicio') || servicioError(i, 'cantidad')) && (
                            <small className="campo-error">{servicioError(i, 'fecha_servicio') || servicioError(i, 'cantidad')}</small>
                          )}
                        </div>
                      )}
                    </li>
                  )
                })}
              </ul>
            </section>
          </div>

          <aside className="reserva__resumen">
            <h2 className="seccion-title">Resumen</h2>
            {!resumen && !erroresResumen && <p className="estado">Calculando…</p>}
            {erroresResumen && <p className="campo-error">{erroresResumen}</p>}
            {resumen && (
              <>
                {rutaImagen(resumen.habitacion.imagen_principal) && (
                  <img className="reserva__foto" src={rutaImagen(resumen.habitacion.imagen_principal)} alt={resumen.habitacion.tipo} />
                )}
                <p className="reserva__habitacion">
                  <strong>{resumen.habitacion.tipo}</strong> · Habitación {resumen.habitacion.numero}
                </p>
                <p className="form-nota">
                  {fechaCorta(estadia.fecha_llegada)} → {fechaCorta(estadia.fecha_salida)} · {resumen.noches.length}{' '}
                  {resumen.noches.length === 1 ? 'noche' : 'noches'} · hasta {capacidad} {capacidad === 1 ? 'persona' : 'personas'}
                </p>
                <dl className="importes">
                  <dt>Alojamiento</dt>
                  <dd>{formatoUSD(resumen.subtotal_alojamiento)}</dd>
                  {resumen.servicios.map((s) => (
                    <div key={s.servicio_id} className="importes__linea">
                      <dt>{s.nombre} × {s.cantidad}</dt>
                      <dd>{formatoUSD(s.subtotal)}</dd>
                    </div>
                  ))}
                  <dt>Impuestos ({resumen.impuesto_porcentaje} %)</dt>
                  <dd>{formatoUSD(resumen.impuesto)}</dd>
                  <dt className="importes__total">Total</dt>
                  <dd className="importes__total">{formatoUSD(resumen.total)}</dd>
                  <dt className="importes__anticipo">Anticipo ({resumen.anticipo_porcentaje} %)</dt>
                  <dd className="importes__anticipo">{formatoUSD(resumen.anticipo_requerido)}</dd>
                </dl>
                <p className="form-nota">El saldo se paga en el hotel. La reserva se confirma cuando se aprueba el anticipo.</p>
              </>
            )}
            <button
              className="btn btn--block"
              disabled={enviando || !resumen || !resumen.disponible || !user?.email_verificado}
            >
              {enviando ? 'Registrando…' : 'Reservar y pagar el anticipo'}
            </button>
            {user && !user.email_verificado && <small className="form-nota">Verifica tu correo para confirmar.</small>}
          </aside>
        </form>
      </main>
    </div>
  )
}
