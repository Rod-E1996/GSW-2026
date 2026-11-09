import { useEffect, useState } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { Avatar, EstadoVacio } from '../../../components/ui'
import { descargar, fechaCorta, formatoUSD } from '../../../utils'
import { COLOR_RESERVA } from './ReservasList'
import '../../../styles/admin-reservas.css'

function fechaHora(iso) {
  return iso ? new Date(iso).toLocaleString('es-SV', { dateStyle: 'short', timeStyle: 'short' }) : '—'
}

// "2026-10-19" -> "lunes, 19 de octubre de 2026"
function fechaLarga(fecha) {
  if (!fecha) return '—'
  const [a, m, d] = fecha.split('-').map(Number)
  const texto = new Date(Date.UTC(a, m - 1, d)).toLocaleDateString('es-SV', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' })
  return texto.charAt(0).toUpperCase() + texto.slice(1)
}

function primerError(err, porDefecto) {
  const e = err.response?.data?.errors
  return e ? Object.values(e)[0][0] : err.response?.data?.message || porDefecto
}

const PAGO_VACIO = { monto: '', metodo_pago: 'efectivo', referencia: '' }

// Recorrido normal de una reserva
const PASOS = [
  [1, 'Pendiente'],
  [2, 'Confirmada'],
  [3, 'En estadía'],
  [4, 'Finalizada'],
]

const TIPO_CORREO = {
  registro: 'Reserva recibida',
  confirmacion: 'Confirmación',
  modificacion: 'Modificación',
  cancelacion: 'Cancelación',
}

// Ficha de la reserva en recepcion: datos, importes, pagos y las acciones que permiten el estado y el rol.
export default function ReservaAdmin() {
  const { codigo } = useParams()
  const location = useLocation()
  const { can } = useAuth()
  const [r, setR] = useState(null)
  const [metodos, setMetodos] = useState([])
  const [catalogo, setCatalogo] = useState([])
  const [mensaje, setMensaje] = useState(location.state?.mensaje || null)
  const [error, setError] = useState(null)
  const [accion, setAccion] = useState(null)
  const [enviando, setEnviando] = useState(false)
  const [pago, setPago] = useState(PAGO_VACIO)
  const [motivo, setMotivo] = useState('')
  const [checkin, setCheckin] = useState({ habitacion_id: '', identidad_verificada: false })
  const [habitaciones, setHabitaciones] = useState([])
  const [consumo, setConsumo] = useState({ servicio_id: '', cantidad: 1, notas: '' })

  useEffect(() => {
    api.get(`/admin/reservas/${codigo}`).then((res) => setR(res.data.data)).catch((err) => setError(primerError(err, 'No se pudo cargar la reserva.')))
    api.get('/admin/reservas/catalogos').then((res) => setMetodos(res.data.metodos_pago))
    api.get('/servicios').then((res) => setCatalogo(res.data.data))
  }, [codigo])

  function abrir(nombre) {
    setAccion(nombre)
    setError(null)
    setMensaje(null)
    if (nombre === 'confirmar') setPago({ ...PAGO_VACIO, monto: r.anticipo_requerido.toFixed(2) })
    if (nombre === 'checkout') setPago({ ...PAGO_VACIO, monto: r.saldo.toFixed(2) })
    if (nombre === 'checkin') {
      setCheckin({ habitacion_id: r.habitacion_id || '', identidad_verificada: false })
      api
        .get(`/admin/reservas/${codigo}/habitaciones`, { params: { llegada: r.fecha_llegada, salida: r.fecha_salida, huespedes: r.personas } })
        .then((res) => setHabitaciones(res.data.data))
        .catch(() => setHabitaciones([]))
    }
  }

  async function ejecutar(e, ruta, cuerpo, metodo = 'post') {
    e?.preventDefault()
    setEnviando(true)
    setError(null)
    try {
      const res = await api[metodo](`/admin/reservas/${codigo}${ruta}`, cuerpo)
      setR(res.data.data)
      setMensaje(res.data.message)
      setAccion(null)
      setMotivo('')
      setConsumo({ servicio_id: '', cantidad: 1, notas: '' })
    } catch (err) {
      setError(primerError(err, 'No se pudo completar la acción.'))
    } finally {
      setEnviando(false)
    }
  }

  async function bajarPdf() {
    try {
      await descargar(api, `/admin/comprobantes/${r.comprobante.qr_token}/pdf`, `${r.comprobante.numero}.pdf`)
    } catch {
      setError('No se pudo descargar el comprobante.')
    }
  }

  if (!r) {
    return error ? (
      <div className="tarjeta-simple">
        <EstadoVacio
          icono="reservas"
          titulo="No encontramos la reserva"
          texto={error}
          accion={<Link to="/panel/reservas" className="btn btn--ghost">Volver a reservas</Link>}
        />
      </div>
    ) : <p className="estado">Cargando…</p>
  }

  const a = r.acciones
  const cancelada = r.estado === 5
  const porcentajePagado = r.total > 0 ? Math.min(100, Math.round((r.pagado / r.total) * 100)) : 0

  const formPago = (titulo, boton, ruta, nota) => (
    <form className="form accion-form" onSubmit={(e) => ejecutar(e, ruta, { ...pago, referencia: pago.referencia || undefined })}>
      <div className="accion-form__cabecera">
        <h3>{titulo}</h3>
        {nota && <p className="form-nota">{nota}</p>}
      </div>
      <div className="form-row form-row--3">
        <label>
          Monto recibido
          <input type="number" step="0.01" min="0.01" value={pago.monto} onChange={(e) => setPago({ ...pago, monto: e.target.value })} />
        </label>
        <label>
          Forma de pago
          <select value={pago.metodo_pago} onChange={(e) => setPago({ ...pago, metodo_pago: e.target.value })}>
            {metodos.map((m) => <option key={m.valor} value={m.valor}>{m.nombre}</option>)}
          </select>
        </label>
        <label>
          Referencia (opcional)
          <input placeholder="N.º de transferencia o voucher" value={pago.referencia} onChange={(e) => setPago({ ...pago, referencia: e.target.value })} />
        </label>
      </div>
      <div className="form-acciones">
        <button type="button" className="btn btn--ghost" onClick={() => setAccion(null)}>Cancelar</button>
        <button className="btn" disabled={enviando}>{enviando ? 'Guardando…' : boton}</button>
      </div>
    </form>
  )

  return (
    <div className="detalle-reserva reserva-admin ra">
      <Link to="/panel/reservas" className="reserva__volver"><Icono nombre="volver" size={15} /> Reservas</Link>

      {mensaje && <div className="alerta alerta--ok">{mensaje}</div>}
      {error && <div className="alerta alerta--error">{error}</div>}

      {/* Encabezado: codigo, estado y acciones principales */}
      <header className="ra-cabecera">
        <div className="ra-cabecera__titulo">
          <div className="ra-cabecera__linea">
            <h1 className="detalle-reserva__codigo">{r.codigo}</h1>
            <span className={`badge ${r.vencida ? '' : COLOR_RESERVA[r.estado] || ''}`}>{r.estado_nombre}</span>
          </div>
          <p className="form-nota">
            {[
              r.canal_nombre !== '—' && r.canal_nombre,
              r.creada_por && `registrada por ${r.creada_por}`,
              r.creada_at && `creada ${fechaHora(r.creada_at)}`,
            ].filter(Boolean).join(' · ')}
          </p>
        </div>
        <div className="ra-cabecera__acciones">
          {a.modificar && can('reserva_update') && (
            <Link className="btn btn--ghost" to={`/panel/reservas/${r.codigo}/editar`}><Icono nombre="editar" size={16} /> Modificar</Link>
          )}
          {a.consumos && can('reserva_update') && (
            <button className="btn btn--ghost" onClick={() => abrir('consumo')}><Icono nombre="mas" size={16} /> Cargar consumo</button>
          )}
          {a.cancelar && can('reserva_cancelar') && (
            <button className="btn btn--danger" onClick={() => abrir('cancelar')}>Cancelar reserva</button>
          )}
          {a.confirmar && can('reserva_confirmar') && <button className="btn" onClick={() => abrir('confirmar')}><Icono nombre="check" size={16} /> Confirmar</button>}
          {a.checkin && can('reserva_checkin') && <button className="btn" onClick={() => abrir('checkin')}><Icono nombre="entrada" size={16} /> Registrar entrada</button>}
          {a.checkout && can('reserva_checkout') && <button className="btn" onClick={() => abrir('checkout')}><Icono nombre="salir" size={16} /> Registrar salida</button>}
        </div>
      </header>

      {/* Recorrido de la reserva */}
      {cancelada ? (
        <div className="alerta alerta--error">
          Reserva cancelada{r.cancelada_at ? ` el ${fechaHora(r.cancelada_at)}` : ''}{r.motivo_cancelacion ? `. Motivo: ${r.motivo_cancelacion}` : '.'}
        </div>
      ) : (
        <ol className="ra-pasos" aria-label="Recorrido de la reserva">
          {PASOS.map(([valor, nombre]) => (
            <li key={valor} className={r.estado > valor ? 'is-hecho' : r.estado === valor ? 'is-actual' : ''}>
              <span className="ra-pasos__punto">{r.estado > valor ? <Icono nombre="check" size={13} /> : valor}</span>
              <span>{nombre}</span>
            </li>
          ))}
        </ol>
      )}

      {r.vencida && a.confirmar && (
        <div className="alerta alerta--aviso">Venció sin anticipo y ya no aparta la habitación. Se puede confirmar si la habitación sigue libre.</div>
      )}
      {r.estado === 2 && !a.checkin && (
        <div className="alerta alerta--info">La entrada se registra a partir del {fechaCorta(r.fecha_llegada)}.</div>
      )}

      {/* Formulario de la accion elegida */}
      {accion === 'confirmar' && formPago('Confirmar con el anticipo recibido', 'Confirmar reserva', '/confirmar',
        `Anticipo mínimo: ${formatoUSD(r.anticipo_requerido)}. Al confirmar se emite el comprobante y se envía al huésped.`)}

      {accion === 'checkout' && (r.saldo > 0
        ? formPago('Registrar salida', 'Cobrar y registrar salida', '/checkout', `Saldo pendiente: ${formatoUSD(r.saldo)}. Revisa los consumos antes de cobrar.`)
        : (
          <form className="form accion-form" onSubmit={(e) => ejecutar(e, '/checkout', {})}>
            <div className="accion-form__cabecera">
              <h3>Registrar salida</h3>
              <p className="form-nota">No hay saldo pendiente. La habitación {r.habitacion} pasará a limpieza.</p>
            </div>
            <div className="form-acciones">
              <button type="button" className="btn btn--ghost" onClick={() => setAccion(null)}>Cancelar</button>
              <button className="btn" disabled={enviando}>{enviando ? 'Guardando…' : 'Registrar salida'}</button>
            </div>
          </form>
        ))}

      {accion === 'checkin' && (
        <form className="form accion-form" onSubmit={(e) => ejecutar(e, '/checkin', { habitacion_id: checkin.habitacion_id || undefined, identidad_verificada: checkin.identidad_verificada })}>
          <div className="accion-form__cabecera">
            <h3>Registrar entrada</h3>
            <p className="form-nota">Confirma la habitación y verifica el documento del titular antes de entregar la llave.</p>
          </div>
          <label>
            Habitación
            <select value={checkin.habitacion_id} onChange={(e) => setCheckin({ ...checkin, habitacion_id: Number(e.target.value) || '' })}>
              {!r.habitacion_id && <option value="">Elige una habitación</option>}
              {habitaciones.map((h) => (
                <option key={h.id} value={h.id}>
                  {h.numero} · {h.tipo}{h.actual ? ' (reservada)' : ''}{h.estado_habitacion !== 'disponible' ? ` — ${h.estado_nombre}` : ''}
                </option>
              ))}
            </select>
          </label>
          <label className="form-check ra-verificacion">
            <input type="checkbox" checked={checkin.identidad_verificada} onChange={(e) => setCheckin({ ...checkin, identidad_verificada: e.target.checked })} />
            <span>Verifiqué el documento del titular: <strong>{r.huesped.documento ? [r.huesped.tipo_documento, r.huesped.documento].filter(Boolean).join(' ') : '(sin documento registrado)'}</strong></span>
          </label>
          <div className="form-acciones">
            <button type="button" className="btn btn--ghost" onClick={() => setAccion(null)}>Cancelar</button>
            <button className="btn" disabled={enviando}>{enviando ? 'Guardando…' : 'Registrar entrada'}</button>
          </div>
        </form>
      )}

      {accion === 'consumo' && (
        <form className="form accion-form" onSubmit={(e) => ejecutar(e, '/consumos', consumo)}>
          <div className="accion-form__cabecera">
            <h3>Cargar consumo</h3>
            <p className="form-nota">Se suma a la cuenta de la reserva y se cobra al registrar la salida.</p>
          </div>
          <div className="form-row form-row--3">
            <label>
              Servicio
              <select value={consumo.servicio_id} onChange={(e) => setConsumo({ ...consumo, servicio_id: Number(e.target.value) || '' })}>
                <option value="">Elige un servicio</option>
                {catalogo.map((s) => <option key={s.id} value={s.id}>{s.nombre} · {formatoUSD(s.precio)}</option>)}
              </select>
            </label>
            <label>
              Cantidad
              <input type="number" min="1" max="100" value={consumo.cantidad} onChange={(e) => setConsumo({ ...consumo, cantidad: e.target.value })} />
            </label>
            <label>
              Nota (opcional)
              <input maxLength="255" value={consumo.notas} onChange={(e) => setConsumo({ ...consumo, notas: e.target.value })} />
            </label>
          </div>
          <div className="form-acciones">
            <button type="button" className="btn btn--ghost" onClick={() => setAccion(null)}>Cancelar</button>
            <button className="btn" disabled={enviando || !consumo.servicio_id}>{enviando ? 'Guardando…' : 'Cargar'}</button>
          </div>
        </form>
      )}

      {accion === 'cancelar' && (
        <form className="form accion-form accion-form--peligro" onSubmit={(e) => ejecutar(e, '/cancelar', { motivo })}>
          <div className="accion-form__cabecera">
            <h3>Cancelar la reserva</h3>
            <p className="form-nota">
              {r.cancelacion.anticipo_pagado > 0
                ? `Pagado: ${formatoUSD(r.cancelacion.anticipo_pagado)}. ${r.cancelacion.en_plazo ? 'Está en plazo' : 'Ya pasó el plazo'}: corresponde reembolsar ${formatoUSD(r.cancelacion.reembolso)} (${r.cancelacion.porcentaje_reembolso} %).`
                : 'No hay pagos: no hay nada que reembolsar.'}
            </p>
          </div>
          <label>
            Motivo
            <input maxLength="255" placeholder="Ej.: el huésped no se presentó" value={motivo} onChange={(e) => setMotivo(e.target.value)} />
          </label>
          <div className="form-acciones">
            <button type="button" className="btn btn--ghost" onClick={() => setAccion(null)}>No cancelar</button>
            <button className="btn btn--danger" disabled={enviando}>{enviando ? 'Cancelando…' : 'Cancelar reserva'}</button>
          </div>
        </form>
      )}

      <div className="reserva-admin__grid">
        {/* Columna principal */}
        <div className="reserva-admin__columna">
          <section className="tarjeta-simple">
            <h3>Estadía</h3>
            <div className="ra-fechas">
              <div>
                <span className="ra-etiqueta">Llegada</span>
                <strong>{fechaLarga(r.fecha_llegada)}</strong>
                <small>{r.check_in_at ? `Entró ${fechaHora(r.check_in_at)}` : r.hora_estimada_llegada ? `Hora estimada ${r.hora_estimada_llegada}` : 'Sin hora estimada'}</small>
              </div>
              <div className="ra-fechas__noches">
                <Icono nombre="flecha" size={16} />
                <span>{r.noches} {r.noches === 1 ? 'noche' : 'noches'}</span>
              </div>
              <div>
                <span className="ra-etiqueta">Salida</span>
                <strong>{fechaLarga(r.fecha_salida)}</strong>
                <small>{r.check_out_at ? `Salió ${fechaHora(r.check_out_at)}` : 'Pendiente'}</small>
              </div>
            </div>
            <dl className="ra-datos">
              <div>
                <dt>Habitación</dt>
                <dd>{r.habitacion ? `${r.habitacion} · ${r.tipo}` : `${r.tipo} · sin asignar`}</dd>
              </div>
              <div>
                <dt>Huéspedes</dt>
                <dd>{r.adultos} {r.adultos === 1 ? 'adulto' : 'adultos'}{r.ninos ? ` y ${r.ninos} ${r.ninos === 1 ? 'niño' : 'niños'}` : ''}</dd>
              </div>
              <div>
                <dt>Canal</dt>
                <dd>{r.canal_nombre !== '—' ? r.canal_nombre : 'Sin registrar'}</dd>
              </div>
            </dl>
            {r.notas && (
              <div className="ra-nota"><Icono nombre="info" size={16} /><p>{r.notas}</p></div>
            )}
          </section>

          <section className="tarjeta-simple">
            <h3>Huésped titular</h3>
            <div className="ra-huesped">
              <Avatar nombre={`${r.huesped.nombres} ${r.huesped.apellidos}`} size="lg" />
              <div className="ra-huesped__datos">
                <strong>{r.huesped.nombres} {r.huesped.apellidos}</strong>
                <span className="form-nota">
                  {r.huesped.documento ? [r.huesped.tipo_documento, r.huesped.documento].filter(Boolean).join(' ') : 'Sin documento registrado'}
                </span>
                {r.huesped.tiene_cuenta && <span className="badge badge--info">Tiene cuenta en el portal</span>}
              </div>
              <div className="ra-huesped__contacto">
                {r.huesped.telefono && <a href={`tel:${r.huesped.telefono}`}><Icono nombre="telefono" size={15} /> {r.huesped.telefono}</a>}
                {r.huesped.email && <a href={`mailto:${r.huesped.email}`}><Icono nombre="correo" size={15} /> {r.huesped.email}</a>}
                {!r.huesped.telefono && !r.huesped.email && <span className="form-nota">Sin datos de contacto</span>}
              </div>
            </div>
          </section>

          <section className="tarjeta-simple">
            <h3>Alojamiento y servicios</h3>
            <div className="tabla-wrap tabla-wrap--plana">
              <table className="tabla">
                <thead>
                  <tr>
                    <th>Concepto</th>
                    <th className="tabla__num">Cantidad</th>
                    <th className="tabla__num">Importe</th>
                    <th aria-label="Acciones" />
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>
                      <strong>Alojamiento</strong>
                      <small className="form-nota tabla__sub">{r.tipo} · {r.noches} {r.noches === 1 ? 'noche' : 'noches'}</small>
                    </td>
                    <td className="tabla__num">{r.noches}</td>
                    <td className="tabla__num">{formatoUSD(r.subtotal_alojamiento)}</td>
                    <td />
                  </tr>
                  {r.servicios.map((s) => (
                    <tr key={s.linea}>
                      <td>
                        <strong>{s.nombre}</strong>
                        <small className="form-nota tabla__sub">
                          {s.consumo ? `Consumo del ${fechaCorta(s.fecha_servicio)}` : 'Contratado con la reserva'}
                          {s.fecha_servicio && !s.consumo ? ` · ${fechaCorta(s.fecha_servicio)}${s.hora_servicio ? ` ${s.hora_servicio}` : ''}` : ''}
                          {s.notas ? ` · ${s.notas}` : ''}
                        </small>
                      </td>
                      <td className="tabla__num">{s.cantidad}</td>
                      <td className="tabla__num">{formatoUSD(s.subtotal)}</td>
                      <td className="tabla__acciones">
                        {s.consumo && a.consumos && can('reserva_update') && (
                          <button type="button" className="btn btn--sm btn--danger" onClick={(e) => ejecutar(e, `/consumos/${s.linea}`, undefined, 'delete')}>Quitar</button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          <section className="tarjeta-simple">
            <h3>Historial</h3>
            <ol className="linea-tiempo">
              {[...r.historial].reverse().map((h, i) => (
                <li key={i} className={h.cambio_estado ? 'is-estado' : ''}>
                  <div className="linea-tiempo__cabecera">
                    <strong>{h.cambio_estado ? h.estado : 'Cambio'}</strong>
                    <span className="form-nota">{fechaHora(h.fecha)}{h.usuario ? ` · ${h.usuario}` : ''}</span>
                  </div>
                  {h.comentario && <p>{h.comentario}</p>}
                </li>
              ))}
            </ol>
          </section>
        </div>

        {/* Columna lateral: cuenta, comprobante y correos */}
        <aside className="reserva-admin__columna ra-lateral">
          <section className="tarjeta-simple ra-cuenta">
            <h3>Cuenta</h3>
            <div className="ra-cuenta__total">
              <span>Total</span>
              <strong>{formatoUSD(r.total)}</strong>
            </div>
            <div className="ra-progreso" role="progressbar" aria-valuenow={porcentajePagado} aria-valuemin="0" aria-valuemax="100" aria-label="Pagado">
              <span style={{ width: `${porcentajePagado}%` }} />
            </div>
            <dl className="importes">
              <dt>Pagado</dt><dd>{formatoUSD(r.pagado)}</dd>
              <dt className="importes__anticipo">Saldo</dt><dd className="importes__anticipo">{formatoUSD(r.saldo)}</dd>
            </dl>
            <dl className="importes ra-cuenta__desglose">
              <dt>Alojamiento</dt><dd>{formatoUSD(r.subtotal_alojamiento)}</dd>
              <dt>Servicios</dt><dd>{formatoUSD(r.subtotal_servicios)}</dd>
              {r.descuento > 0 && (<><dt>Descuento</dt><dd>−{formatoUSD(r.descuento)}</dd></>)}
              <dt>Impuestos</dt><dd>{formatoUSD(r.impuesto)}</dd>
              <dt>Anticipo requerido</dt><dd>{formatoUSD(r.anticipo_requerido)}</dd>
            </dl>
          </section>

          <section className="tarjeta-simple">
            <h3>Pagos</h3>
            {r.pagos.length === 0 ? (
              <p className="vacio-mini">Todavía no hay pagos.</p>
            ) : (
              <ul className="ra-pagos">
                {r.pagos.map((p) => (
                  <li key={p.id}>
                    <div className="ra-pagos__linea">
                      <strong>{p.tipo === 'saldo' ? 'Saldo' : 'Anticipo'} · {formatoUSD(p.monto)}</strong>
                      <span className={`badge ${{ 1: 'badge--aviso', 2: 'badge--ok', 3: 'badge--danger' }[p.estado] || ''}`}>{p.estado_nombre}</span>
                    </div>
                    <span className="form-nota">
                      {fechaHora(p.fecha)} · {p.medio || p.metodo}
                      {p.pasarela === 'sandbox' ? ' (pasarela de pruebas)' : ''}
                      {p.referencia ? ` · ref. ${p.referencia}` : ''}
                      {p.registrado_por ? ` · ${p.registrado_por}` : ''}
                    </span>
                    {p.motivo && <span className="form-nota">{p.motivo}</span>}
                    {p.requiere_reembolso && <span className="ra-pagos__reembolso">Reembolsar al huésped</span>}
                  </li>
                ))}
              </ul>
            )}
          </section>

          {r.comprobante && (
            <section className="tarjeta-simple">
              <h3>Comprobante</h3>
              <div className="ra-comprobante">
                <span className="ra-comprobante__icono"><Icono nombre="qr" size={20} /></span>
                <div>
                  <strong>{r.comprobante.numero}</strong>
                  <span className="form-nota">Versión {r.comprobante.version} · {r.comprobante.estado}</span>
                  <span className="form-nota">{r.comprobante.verificado_at ? `Verificado ${fechaHora(r.comprobante.verificado_at)}` : 'Sin verificar en recepción'}</span>
                </div>
              </div>
              <div className="ra-comprobante__acciones">
                <button type="button" className="btn btn--ghost btn--sm" onClick={bajarPdf}><Icono nombre="descargar" size={15} /> PDF</button>
                {can('comprobante_verificar') && (
                  <Link className="btn btn--ghost btn--sm" to={`/panel/verificar/${r.comprobante.qr_token}`}><Icono nombre="qr" size={15} /> Verificar</Link>
                )}
              </div>
            </section>
          )}

          <section className="tarjeta-simple">
            <h3>Correos al huésped</h3>
            {r.notificaciones.length === 0 ? (
              <p className="vacio-mini">{r.huesped.email ? 'Aún no se envió ninguno.' : 'El huésped no tiene correo registrado.'}</p>
            ) : (
              <ul className="ra-pagos">
                {r.notificaciones.map((n, i) => (
                  <li key={i}>
                    <div className="ra-pagos__linea">
                      <strong>{TIPO_CORREO[n.tipo] || n.tipo}</strong>
                      <span className={`badge ${n.estado === 'Enviado' ? 'badge--ok' : n.estado === 'Fallido' ? 'badge--danger' : 'badge--aviso'}`}>{n.estado}</span>
                    </div>
                    <span className="form-nota">{fechaHora(n.fecha)}{n.destinatario ? ` · ${n.destinatario}` : ''}</span>
                    {n.error && <span className="form-nota" title={n.error}>{n.error.slice(0, 90)}</span>}
                  </li>
                ))}
              </ul>
            )}
          </section>
        </aside>
      </div>
    </div>
  )
}
