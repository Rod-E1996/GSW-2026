import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { api } from '../../api'
import { descargar, fechaCorta, formatoUSD, irAPasarela } from '../../utils'
import { COLOR_ESTADO } from './MisReservas'

function fechaHora(iso) {
  return iso ? new Date(iso).toLocaleString('es-SV', { dateStyle: 'short', timeStyle: 'short' }) : '—'
}

const COLOR_PAGO = { 1: 'badge--aviso', 2: 'badge--ok', 3: 'badge--danger' }

// Mensaje al volver de la pasarela, segun el resultado del cobro
function mensajeDelPago(reserva, referencia) {
  const pago = reserva.pagos.find((p) => p.referencia === referencia)
  if (!pago) return null
  if (pago.estado === 2 && pago.requiere_reembolso) {
    return { tipo: 'aviso', texto: 'Recibimos tu pago, pero la reserva ya no se podía confirmar. El hotel te devolverá el monto completo.' }
  }
  if (pago.estado === 2) {
    return { tipo: 'ok', texto: 'Pago aprobado. Tu reserva está confirmada y te enviamos el comprobante por correo.' }
  }
  if (pago.estado === 3) {
    return { tipo: 'error', texto: `El pago fue rechazado${pago.motivo ? `: ${pago.motivo}` : '.'} Puedes intentarlo de nuevo.` }
  }
  return { tipo: 'aviso', texto: 'El pago no se completó. Puedes intentarlo de nuevo.' }
}

export default function ReservaDetalle() {
  const { codigo } = useParams()
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const [reserva, setReserva] = useState(null)
  const [error, setError] = useState(null)
  const [mensaje, setMensaje] = useState(params.get('nueva') ? { tipo: 'ok', texto: 'Reserva registrada. Guarda tu código de confirmación.' } : null)
  const [cancelando, setCancelando] = useState(false)
  const [motivo, setMotivo] = useState('')
  const [enviando, setEnviando] = useState(false)
  const [pagando, setPagando] = useState(false)
  const [bajando, setBajando] = useState(false)

  useEffect(() => {
    api
      .get(`/mi-cuenta/reservas/${codigo}`)
      .then((res) => {
        setReserva(res.data.data)
        const referencia = params.get('pago')
        if (referencia) setMensaje(mensajeDelPago(res.data.data, referencia))
      })
      .catch((err) => setError(err.response?.data?.message || 'No se pudo cargar la reserva.'))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [codigo])

  function primerError(err, porDefecto) {
    const errores = err.response?.data?.errors
    return errores ? Object.values(errores)[0][0] : err.response?.data?.message || porDefecto
  }

  async function pagar() {
    setPagando(true)
    setError(null)
    try {
      const res = await api.post(`/mi-cuenta/reservas/${codigo}/pagar`)
      irAPasarela(res.data.data.url, navigate)
    } catch (err) {
      setError(primerError(err, 'No se pudo abrir el pago.'))
      setPagando(false)
    }
  }

  async function bajarComprobante() {
    setBajando(true)
    setError(null)
    try {
      await descargar(api, `/mi-cuenta/reservas/${codigo}/comprobante`, `comprobante-${codigo}.pdf`)
    } catch {
      setError('No se pudo descargar el comprobante.')
    } finally {
      setBajando(false)
    }
  }

  async function cancelar(e) {
    e.preventDefault()
    setEnviando(true)
    setError(null)
    try {
      const res = await api.post(`/mi-cuenta/reservas/${codigo}/cancelar`, { motivo: motivo || undefined })
      setReserva(res.data.data)
      setMensaje({ tipo: 'ok', texto: res.data.message })
      setCancelando(false)
    } catch (err) {
      setError(primerError(err, 'No se pudo cancelar.'))
    } finally {
      setEnviando(false)
    }
  }

  if (!reserva) {
    return error ? (
      <div>
        <div className="alerta alerta--error">{error}</div>
        <Link to="/mi-cuenta" className="btn btn--ghost">← Mis reservas</Link>
      </div>
    ) : (
      <p className="estado">Cargando…</p>
    )
  }

  const politica = reserva.cancelacion

  return (
    <div className="detalle-reserva">
      <Link to="/mi-cuenta" className="reserva__volver">← Mis reservas</Link>

      {mensaje && <div className={`alerta alerta--${mensaje.tipo}`}>{mensaje.texto}</div>}
      {error && <div className="alerta alerta--error">{error}</div>}

      <div className="detalle-reserva__cabecera">
        <div>
          <span className="form-nota">Código de confirmación</span>
          <h2 className="detalle-reserva__codigo">{reserva.codigo}</h2>
        </div>
        <span className={`badge ${reserva.vencida ? '' : COLOR_ESTADO[reserva.estado] || ''}`}>{reserva.estado_nombre}</span>
      </div>

      {reserva.puede_pagar && (
        <div className="alerta alerta--aviso aviso-pago">
          <span>
            {reserva.vencida
              ? 'Esta reserva venció sin anticipo. Si la habitación sigue libre, aún puedes pagarlo y confirmarla.'
              : `Tu reserva está pendiente hasta que se apruebe el anticipo de ${formatoUSD(reserva.anticipo_requerido)}.`}
          </span>
          <button type="button" className="btn" onClick={pagar} disabled={pagando}>
            {pagando ? 'Abriendo…' : `Pagar ${formatoUSD(reserva.anticipo_requerido)}`}
          </button>
        </div>
      )}

      {reserva.comprobante && (
        <section className="tarjeta-simple comprobante">
          <img className="comprobante__qr" src={reserva.comprobante.qr} alt="Código QR del comprobante" />
          <div className="comprobante__datos">
            <h3>Comprobante {reserva.comprobante.numero}</h3>
            <p>Preséntalo al llegar, impreso o en tu teléfono. Recepción escanea el código QR para verificar tu reserva.</p>
            <p className="form-nota">Versión {reserva.comprobante.version} · emitido el {fechaHora(reserva.comprobante.emitido_at)}</p>
            <button type="button" className="btn" onClick={bajarComprobante} disabled={bajando}>
              {bajando ? 'Descargando…' : 'Descargar PDF'}
            </button>
          </div>
        </section>
      )}

      <div className="detalle-reserva__grid">
        <section className="tarjeta-simple">
          <h3>Estadía</h3>
          <dl className="importes">
            <dt>Habitación</dt><dd>{reserva.tipo}{reserva.habitacion ? ` · ${reserva.habitacion}` : ''}</dd>
            <dt>Llegada</dt><dd>{fechaCorta(reserva.fecha_llegada)}{reserva.hora_estimada_llegada ? ` · ${reserva.hora_estimada_llegada}` : ''}</dd>
            <dt>Salida</dt><dd>{fechaCorta(reserva.fecha_salida)}</dd>
            <dt>Huéspedes</dt><dd>{reserva.adultos} {reserva.adultos === 1 ? 'adulto' : 'adultos'}{reserva.ninos ? ` y ${reserva.ninos} ${reserva.ninos === 1 ? 'niño' : 'niños'}` : ''}</dd>
            <dt>Titular</dt><dd>{reserva.huesped.nombres} {reserva.huesped.apellidos}</dd>
            {reserva.notas && (<><dt>Notas</dt><dd>{reserva.notas}</dd></>)}
          </dl>
        </section>

        <section className="tarjeta-simple">
          <h3>Importes</h3>
          <dl className="importes">
            {reserva.detalle_noches.map((n) => (
              <div key={n.fecha} className="importes__linea">
                <dt>Noche del {fechaCorta(n.fecha)}{n.temporada ? ` (${n.temporada})` : ''}</dt>
                <dd>{formatoUSD(n.precio_noche)}</dd>
              </div>
            ))}
            {reserva.servicios.map((s) => (
              <div key={s.nombre} className="importes__linea">
                <dt>{s.nombre} × {s.cantidad}{s.fecha_servicio ? ` · ${fechaCorta(s.fecha_servicio.slice(0, 10))}` : ''}{s.hora_servicio ? ` ${s.hora_servicio}` : ''}</dt>
                <dd>{formatoUSD(s.subtotal)}</dd>
              </div>
            ))}
            <dt>Impuestos</dt><dd>{formatoUSD(reserva.impuesto)}</dd>
            <dt className="importes__total">Total</dt><dd className="importes__total">{formatoUSD(reserva.total)}</dd>
            <dt className="importes__anticipo">Anticipo</dt><dd className="importes__anticipo">{formatoUSD(reserva.anticipo_requerido)}</dd>
            {reserva.pagado > 0 && (
              <>
                <dt>Pagado</dt><dd>{formatoUSD(reserva.pagado)}</dd>
                <dt>Saldo a pagar en el hotel</dt><dd>{formatoUSD(reserva.saldo)}</dd>
              </>
            )}
          </dl>
        </section>
      </div>

      {reserva.pagos.length > 0 && (
        <section className="tarjeta-simple">
          <h3>Pagos</h3>
          <ul className="historial">
            {reserva.pagos.map((p) => (
              <li key={p.referencia} className="pago">
                <span>
                  <span className="form-nota">{fechaHora(p.fecha)}</span> · {formatoUSD(p.monto)}
                  {p.medio && ` · ${p.medio}`}
                  {p.motivo && <span> — {p.motivo}</span>}
                  {p.requiere_reembolso && <span> — el hotel te lo devolverá</span>}
                  {p.pasarela === 'sandbox' && <span className="form-nota"> (pasarela de pruebas)</span>}
                </span>
                <span className={`badge ${COLOR_PAGO[p.estado] || ''}`}>{p.estado_nombre}</span>
              </li>
            ))}
          </ul>
        </section>
      )}

      <section className="tarjeta-simple">
        <h3>Historial</h3>
        <ul className="historial">
          {reserva.historial.map((h, i) => (
            <li key={i}>
              <span className="form-nota">{fechaHora(h.fecha)}</span> · <strong>{h.estado}</strong>
              {h.comentario && <span> — {h.comentario}</span>}
            </li>
          ))}
        </ul>
      </section>

      {politica.permitida && (
        <section className="tarjeta-simple">
          <h3>Cancelar la reserva</h3>
          <p>
            {politica.anticipo_pagado > 0
              ? politica.en_plazo
                ? `Si cancelas antes del ${fechaHora(politica.limite_reembolso)} recibes ${politica.porcentaje_reembolso} % del anticipo: ${formatoUSD(politica.reembolso)}.`
                : `Ya pasó el plazo de reembolso (${fechaHora(politica.limite_reembolso)}). Si cancelas ahora se devuelve ${politica.porcentaje_reembolso} % del anticipo: ${formatoUSD(politica.reembolso)}.`
              : 'Aún no hay pagos registrados en esta reserva, así que no hay nada que reembolsar.'}
          </p>
          {!cancelando ? (
            <button type="button" className="btn btn--danger" onClick={() => setCancelando(true)}>
              Solicitar cancelación
            </button>
          ) : (
            <form className="form" onSubmit={cancelar}>
              <label>
                Motivo (opcional)
                <textarea rows="2" maxLength="255" value={motivo} onChange={(e) => setMotivo(e.target.value)} />
              </label>
              <div className="form-acciones">
                <button type="button" className="btn btn--ghost" onClick={() => setCancelando(false)}>No cancelar</button>
                <button className="btn btn--danger" disabled={enviando}>
                  {enviando ? 'Cancelando…' : 'Confirmar cancelación'}
                </button>
              </div>
            </form>
          )}
        </section>
      )}
      {!politica.permitida && reserva.estado !== 5 && politica.motivo_no_permitida && (
        <p className="form-nota">{politica.motivo_no_permitida}</p>
      )}
      {reserva.estado === 5 && reserva.cancelada_at && (
        <p className="form-nota">Cancelada el {fechaHora(reserva.cancelada_at)}{reserva.motivo_cancelacion ? `. Motivo: ${reserva.motivo_cancelacion}` : ''}.</p>
      )}
    </div>
  )
}
