import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import { descargar, fechaCorta, formatoUSD } from '../../../utils'
import Icono from '../../../components/Icono'
import { Avatar, EstadoVacio } from '../../../components/ui'
import '../../../styles/admin-reservas.css'

const UUID = /[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i

// Acepta lo que entregue el lector: la URL del QR, el token solo o el codigo de la reserva.
function normalizar(valor) {
  const texto = valor.trim()
  const token = texto.match(UUID)
  return token ? token[0].toLowerCase() : texto.toUpperCase()
}

function fechaHora(iso) {
  return iso ? new Date(iso).toLocaleString('es-SV', { dateStyle: 'short', timeStyle: 'short' }) : '—'
}

// Recepcion comprueba el comprobante del huesped al registrar la entrada.
export default function VerificarComprobante() {
  const { token } = useParams()
  const navigate = useNavigate()
  const { can } = useAuth()
  const [entrada, setEntrada] = useState(token || '')
  const [resultado, setResultado] = useState(null)
  const [error, setError] = useState(null)
  const [aviso, setAviso] = useState(null)
  const [buscando, setBuscando] = useState(false)
  const [marcando, setMarcando] = useState(false)

  async function buscar(valor) {
    const clave = normalizar(valor)
    if (!clave) return
    setBuscando(true)
    setError(null)
    setAviso(null)
    setResultado(null)
    try {
      const res = await api.get(`/admin/comprobantes/${encodeURIComponent(clave)}`)
      setResultado(res.data.data)
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo consultar el comprobante.')
    } finally {
      setBuscando(false)
    }
  }

  // Al llegar desde el QR (/panel/verificar/:token) se consulta de inmediato
  useEffect(() => {
    if (token) {
      setEntrada(token)
      buscar(token)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token])

  function onSubmit(e) {
    e.preventDefault()
    const clave = normalizar(entrada)
    if (UUID.test(clave) && clave !== token) {
      navigate(`/panel/verificar/${clave}`)
    } else {
      buscar(clave)
    }
  }

  async function marcarVerificado() {
    setMarcando(true)
    setError(null)
    try {
      const res = await api.post(`/admin/comprobantes/${resultado.comprobante.qr_token}/verificar`)
      setResultado(res.data.data)
      setAviso(res.data.message)
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo registrar la verificación.')
    } finally {
      setMarcando(false)
    }
  }

  async function verPdf() {
    try {
      await descargar(api, `/admin/comprobantes/${resultado.comprobante.qr_token}/pdf`, `${resultado.comprobante.numero}.pdf`)
    } catch {
      setError('No se pudo descargar el comprobante.')
    }
  }

  const c = resultado?.comprobante
  const r = resultado?.reserva

  return (
    <div className="verificar-page">
      <div className="page-head">
        <div>
          <h1 className="page-title">Verificar comprobante</h1>
          <p className="page-sub">Comprueba el comprobante del huésped antes de registrar su entrada.</p>
        </div>
      </div>

      <div className="vc-grid">
        <form className="tarjeta-simple vc-buscar" onSubmit={onSubmit}>
          <span className="vc-buscar__icono"><Icono nombre="qr" size={26} /></span>
          <h3>Escanea el código QR</h3>
          <p className="form-nota">Con el lector, apunta al QR del comprobante impreso o en el teléfono del huésped. Si no se puede leer, escribe el código de la reserva.</p>
          <label className="vc-buscar__campo">
            <span className="sr-solo">Código QR o código de reserva</span>
            <input
              autoFocus
              placeholder="HL-XXXXXXXX o enlace del QR"
              value={entrada}
              onChange={(e) => setEntrada(e.target.value)}
            />
          </label>
          <button className="btn btn--block" disabled={buscando || !entrada.trim()}>
            <Icono nombre="buscar" size={16} />
            {buscando ? 'Buscando…' : 'Buscar comprobante'}
          </button>
        </form>

        <div className="vc-resultado">
          {error && <div className="alerta alerta--error">{error}</div>}
          {aviso && <div className="alerta alerta--ok">{aviso}</div>}

          {!resultado && !error && (
            <div className="tarjeta-simple">
              <EstadoVacio
                icono="qr"
                titulo={buscando ? 'Buscando…' : 'Esperando un comprobante'}
                texto="Aquí verás si el comprobante es válido, los datos de la reserva y el saldo que falta cobrar."
              />
            </div>
          )}

          {resultado && (
            <section className="tarjeta-simple verificar">
              <div className={`verificar__veredicto ${resultado.valido ? 'verificar__veredicto--ok' : 'verificar__veredicto--mal'}`}>
                <span className="verificar__icono"><Icono nombre={resultado.valido ? 'check' : 'cerrar'} size={22} /></span>
                <div>
                  <strong>{resultado.mensaje}</strong>
                  <span>Comprobante {c.numero} · versión {c.version} · {c.estado}</span>
                </div>
              </div>

              <div className="vc-titular">
                <Avatar nombre={r.titular} size="lg" />
                <div>
                  <strong>{r.titular}</strong>
                  <span className="form-nota">{[r.documento, r.telefono].filter(Boolean).join(' · ') || 'Sin documento ni teléfono'}</span>
                </div>
                <span className="vc-titular__codigo">
                  <span className="form-nota">Reserva</span>
                  <strong className="codigo-reserva">{r.codigo}</strong>
                  <span className="form-nota">{r.estado_nombre}</span>
                </span>
              </div>

              <dl className="ra-datos ra-datos--4">
                <div><dt>Habitación</dt><dd>{r.tipo}{r.habitacion ? ` · ${r.habitacion}` : ''}</dd></div>
                <div><dt>Llegada</dt><dd>{fechaCorta(r.fecha_llegada)}</dd></div>
                <div><dt>Salida</dt><dd>{fechaCorta(r.fecha_salida)} · {r.noches} {r.noches === 1 ? 'noche' : 'noches'}</dd></div>
                <div><dt>Huéspedes</dt><dd>{r.adultos} {r.adultos === 1 ? 'adulto' : 'adultos'}{r.ninos ? ` y ${r.ninos} ${r.ninos === 1 ? 'niño' : 'niños'}` : ''}</dd></div>
              </dl>

              <div className="vc-cuenta">
                <div><span>Total</span><strong>{formatoUSD(r.total)}</strong></div>
                <div><span>Pagado</span><strong>{formatoUSD(r.pagado)}</strong></div>
                <div className={r.saldo > 0 ? 'is-pendiente' : ''}><span>Saldo a cobrar</span><strong>{formatoUSD(r.saldo)}</strong></div>
              </div>

              <p className="form-nota vc-fechas">
                Emitido {fechaHora(c.emitido_at)} · {c.verificado_at ? `Verificado ${fechaHora(c.verificado_at)}${c.verificado_por ? ` por ${c.verificado_por}` : ''}` : 'Todavía no se ha verificado'}
              </p>

              <div className="form-acciones">
                <button type="button" className="btn btn--ghost" onClick={verPdf}><Icono nombre="descargar" size={16} /> Descargar PDF</button>
                {can('reserva_show') && <Link className="btn btn--ghost" to={`/panel/reservas/${r.codigo}`}>Abrir reserva</Link>}
                {resultado.valido && !c.verificado_at && (
                  <button type="button" className="btn" onClick={marcarVerificado} disabled={marcando}>
                    <Icono nombre="check" size={16} />
                    {marcando ? 'Registrando…' : 'Marcar como verificado'}
                  </button>
                )}
              </div>
            </section>
          )}
        </div>
      </div>
    </div>
  )
}
