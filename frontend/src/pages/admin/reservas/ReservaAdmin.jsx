import { useEffect, useState } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import { descargar, fechaCorta, formatoUSD } from '../../../utils'
import { COLOR_RESERVA } from './ReservasList'

function fechaHora(iso) {
  return iso ? new Date(iso).toLocaleString('es-SV', { dateStyle: 'short', timeStyle: 'short' }) : '—'
}

function primerError(err, porDefecto) {
  const e = err.response?.data?.errors
  return e ? Object.values(e)[0][0] : err.response?.data?.message || porDefecto
}

const PAGO_VACIO = { monto: '', metodo_pago: 'efectivo', referencia: '' }

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
      <div>
        <div className="alerta alerta--error">{error}</div>
        <Link to="/panel/reservas" className="btn btn--ghost">← Reservas</Link>
      </div>
    ) : <p className="estado">Cargando…</p>
  }

  const a = r.acciones
  const formPago = (titulo, boton, ruta, nota) => (
    <form className="form accion-form" onSubmit={(e) => ejecutar(e, ruta, { ...pago, referencia: pago.referencia || undefined })}>
      <h3>{titulo}</h3>
      {nota && <p className="form-nota">{nota}</p>}
      <div className="form-row">
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
    <div className="detalle-reserva reserva-admin">
      <Link to="/panel/reservas" className="reserva__volver">← Reservas</Link>

      {mensaje && <div className="alerta alerta--ok">{mensaje}</div>}
      {error && <div className="alerta alerta--error">{error}</div>}

      <div className="detalle-reserva__cabecera">
        <div>
          <span className="form-nota">Reserva · {r.canal_nombre}{r.creada_por ? ` · registrada por ${r.creada_por}` : ''}</span>
          <h1 className="detalle-reserva__codigo">{r.codigo}</h1>
        </div>
        <span className={`badge ${r.vencida ? '' : COLOR_RESERVA[r.estado] || ''}`}>{r.estado_nombre}</span>
      </div>

      <div className="acciones-reserva">
        {a.confirmar && can('reserva_confirmar') && <button className="btn" onClick={() => abrir('confirmar')}>Confirmar</button>}
        {a.checkin && can('reserva_checkin') && <button className="btn" onClick={() => abrir('checkin')}>Registrar entrada</button>}
        {a.consumos && can('reserva_update') && <button className="btn btn--ghost" onClick={() => abrir('consumo')}>Cargar consumo</button>}
        {a.checkout && can('reserva_checkout') && <button className="btn" onClick={() => abrir('checkout')}>Registrar salida</button>}
        {a.modificar && can('reserva_update') && <Link className="btn btn--ghost" to={`/panel/reservas/${r.codigo}/editar`}>Modificar</Link>}
        {a.cancelar && can('reserva_cancelar') && <button className="btn btn--danger" onClick={() => abrir('cancelar')}>Cancelar reserva</button>}
      </div>

      {r.vencida && a.confirmar && (
        <div className="alerta alerta--aviso">Venció sin anticipo y ya no aparta la habitación. Se puede confirmar si la habitación sigue libre.</div>
      )}
      {r.estado === 2 && !a.checkin && (
        <p className="form-nota">La entrada se registra a partir del {fechaCorta(r.fecha_llegada)}.</p>
      )}

      {accion === 'confirmar' && formPago('Confirmar con el anticipo recibido', 'Confirmar reserva', '/confirmar',
        `Anticipo mínimo: ${formatoUSD(r.anticipo_requerido)}. Al confirmar se emite el comprobante y se envía al huésped.`)}

      {accion === 'checkout' && (r.saldo > 0
        ? formPago('Registrar salida', 'Cobrar y registrar salida', '/checkout', `Saldo pendiente: ${formatoUSD(r.saldo)}. Revisa los consumos antes de cobrar.`)
        : (
          <form className="form accion-form" onSubmit={(e) => ejecutar(e, '/checkout', {})}>
            <h3>Registrar salida</h3>
            <p>No hay saldo pendiente. La habitación {r.habitacion} pasará a limpieza.</p>
            <div className="form-acciones">
              <button type="button" className="btn btn--ghost" onClick={() => setAccion(null)}>Cancelar</button>
              <button className="btn" disabled={enviando}>{enviando ? 'Guardando…' : 'Registrar salida'}</button>
            </div>
          </form>
        ))}

      {accion === 'checkin' && (
        <form className="form accion-form" onSubmit={(e) => ejecutar(e, '/checkin', { habitacion_id: checkin.habitacion_id || undefined, identidad_verificada: checkin.identidad_verificada })}>
          <h3>Registrar entrada</h3>
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
          <label className="form-check">
            <input type="checkbox" checked={checkin.identidad_verificada} onChange={(e) => setCheckin({ ...checkin, identidad_verificada: e.target.checked })} />
            <span>Verifiqué el documento del titular: {r.huesped.tipo_documento} {r.huesped.documento || '(sin documento registrado)'}</span>
          </label>
          <div className="form-acciones">
            <button type="button" className="btn btn--ghost" onClick={() => setAccion(null)}>Cancelar</button>
            <button className="btn" disabled={enviando}>{enviando ? 'Guardando…' : 'Registrar entrada'}</button>
          </div>
        </form>
      )}

      {accion === 'consumo' && (
        <form className="form accion-form" onSubmit={(e) => ejecutar(e, '/consumos', consumo)}>
          <h3>Cargar consumo</h3>
          <div className="form-row">
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
        <form className="form accion-form" onSubmit={(e) => ejecutar(e, '/cancelar', { motivo })}>
          <h3>Cancelar la reserva</h3>
          <p className="form-nota">
            {r.cancelacion.anticipo_pagado > 0
              ? `Pagado: ${formatoUSD(r.cancelacion.anticipo_pagado)}. ${r.cancelacion.en_plazo ? 'Está en plazo' : 'Ya pasó el plazo'}: corresponde reembolsar ${formatoUSD(r.cancelacion.reembolso)} (${r.cancelacion.porcentaje_reembolso} %).`
              : 'No hay pagos: no hay nada que reembolsar.'}
          </p>
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

      <div className="detalle-reserva__grid">
        <section className="tarjeta-simple">
          <h3>Huésped y estadía</h3>
          <dl className="importes">
            <dt>Titular</dt><dd>{r.huesped.nombres} {r.huesped.apellidos}</dd>
            <dt>Documento</dt><dd>{r.huesped.documento ? `${r.huesped.tipo_documento} ${r.huesped.documento}` : '—'}</dd>
            <dt>Contacto</dt><dd>{[r.huesped.telefono, r.huesped.email].filter(Boolean).join(' · ') || '—'}</dd>
            <dt>Habitación</dt><dd>{r.tipo}{r.habitacion ? ` · ${r.habitacion}` : ' · sin asignar'}</dd>
            <dt>Llegada</dt><dd>{fechaCorta(r.fecha_llegada)}{r.hora_estimada_llegada ? ` · ${r.hora_estimada_llegada}` : ''}</dd>
            <dt>Salida</dt><dd>{fechaCorta(r.fecha_salida)} · {r.noches} {r.noches === 1 ? 'noche' : 'noches'}</dd>
            <dt>Huéspedes</dt><dd>{r.adultos} {r.adultos === 1 ? 'adulto' : 'adultos'}{r.ninos ? ` y ${r.ninos} ${r.ninos === 1 ? 'niño' : 'niños'}` : ''}</dd>
            {r.check_in_at && (<><dt>Entrada</dt><dd>{fechaHora(r.check_in_at)}</dd></>)}
            {r.check_out_at && (<><dt>Salida registrada</dt><dd>{fechaHora(r.check_out_at)}</dd></>)}
            {r.notas && (<><dt>Notas</dt><dd>{r.notas}</dd></>)}
            {r.motivo_cancelacion && (<><dt>Motivo de cancelación</dt><dd>{r.motivo_cancelacion}</dd></>)}
          </dl>
        </section>

        <section className="tarjeta-simple">
          <h3>Importes</h3>
          <dl className="importes">
            <dt>Alojamiento</dt><dd>{formatoUSD(r.subtotal_alojamiento)}</dd>
            {r.servicios.map((s) => (
              <div key={s.linea} className="importes__linea">
                <dt>
                  {s.nombre} × {s.cantidad}
                  {s.consumo && <span className="form-nota"> · consumo {fechaCorta(s.fecha_servicio)}{s.notas ? ` · ${s.notas}` : ''}</span>}
                  {s.consumo && a.consumos && can('reserva_update') && (
                    <button type="button" className="enlace-quitar" onClick={(e) => ejecutar(e, `/consumos/${s.linea}`, undefined, 'delete')}>quitar</button>
                  )}
                </dt>
                <dd>{formatoUSD(s.subtotal)}</dd>
              </div>
            ))}
            <dt>Impuestos</dt><dd>{formatoUSD(r.impuesto)}</dd>
            <dt className="importes__total">Total</dt><dd className="importes__total">{formatoUSD(r.total)}</dd>
            <dt>Anticipo requerido</dt><dd>{formatoUSD(r.anticipo_requerido)}</dd>
            <dt>Pagado</dt><dd>{formatoUSD(r.pagado)}</dd>
            <dt className="importes__anticipo">Saldo</dt><dd className="importes__anticipo">{formatoUSD(r.saldo)}</dd>
          </dl>
        </section>
      </div>

      {r.comprobante && (
        <section className="tarjeta-simple comprobante-admin">
          <div>
            <h3>Comprobante {r.comprobante.numero}</h3>
            <p className="form-nota">
              Versión {r.comprobante.version} · {r.comprobante.estado}
              {r.comprobante.verificado_at ? ` · verificado ${fechaHora(r.comprobante.verificado_at)}` : ''}
            </p>
          </div>
          <div className="form-acciones">
            <button type="button" className="btn btn--ghost" onClick={bajarPdf}>Descargar PDF</button>
            {can('comprobante_verificar') && <Link className="btn btn--ghost" to={`/panel/verificar/${r.comprobante.qr_token}`}>Verificar</Link>}
          </div>
        </section>
      )}

      {r.pagos.length > 0 && (
        <section className="tarjeta-simple">
          <h3>Pagos</h3>
          <ul className="historial">
            {r.pagos.map((p) => (
              <li key={p.id} className="pago">
                <span>
                  <span className="form-nota">{fechaHora(p.fecha)}</span> · {p.tipo === 'saldo' ? 'Saldo' : 'Anticipo'} {formatoUSD(p.monto)} · {p.medio || p.metodo}
                  {p.pasarela === 'sandbox' && <span className="form-nota"> (pasarela de pruebas)</span>}
                  {p.referencia && <span className="form-nota"> · ref. {p.referencia}</span>}
                  {p.registrado_por && <span className="form-nota"> · {p.registrado_por}</span>}
                  {p.motivo && <span> — {p.motivo}</span>}
                  {p.requiere_reembolso && <strong> — reembolsar al huésped</strong>}
                </span>
                <span className={`badge ${{ 1: 'badge--aviso', 2: 'badge--ok', 3: 'badge--danger' }[p.estado] || ''}`}>{p.estado_nombre}</span>
              </li>
            ))}
          </ul>
        </section>
      )}

      <div className="detalle-reserva__grid">
        <section className="tarjeta-simple">
          <h3>Historial</h3>
          <ul className="historial">
            {r.historial.map((h, i) => (
              <li key={i}>
                <span className="form-nota">{fechaHora(h.fecha)}</span> · <strong>{h.cambio_estado ? h.estado : 'Cambio'}</strong>
                {h.comentario && <span> — {h.comentario}</span>}
                {h.usuario && <span className="form-nota"> · {h.usuario}</span>}
              </li>
            ))}
          </ul>
        </section>

        <section className="tarjeta-simple">
          <h3>Correos al huésped</h3>
          {r.notificaciones.length === 0 ? (
            <p className="form-nota">{r.huesped.email ? 'Aún no se envió ninguno.' : 'El huésped no tiene correo registrado.'}</p>
          ) : (
            <ul className="historial">
              {r.notificaciones.map((n, i) => (
                <li key={i} className="pago">
                  <span>
                    <span className="form-nota">{fechaHora(n.fecha)}</span> · {n.tipo}
                    {n.error && <span className="form-nota"> — {n.error.slice(0, 80)}</span>}
                  </span>
                  <span className={`badge ${n.estado === 'Enviado' ? 'badge--ok' : n.estado === 'Fallido' ? 'badge--danger' : 'badge--aviso'}`}>{n.estado}</span>
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
    </div>
  )
}
