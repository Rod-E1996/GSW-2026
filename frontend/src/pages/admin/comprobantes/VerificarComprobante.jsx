import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'
import { descargar, fechaCorta, formatoUSD } from '../../../utils'

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
    <div className="form-page">
      <h1 className="page-title">Verificar comprobante</h1>
      <p className="page-sub">Escanea el código QR del comprobante o escribe el código de la reserva.</p>

      <form className="form verificar__buscar" onSubmit={onSubmit}>
        <label>
          Código QR o código de reserva
          <input
            autoFocus
            placeholder="HL-XXXXXXXX"
            value={entrada}
            onChange={(e) => setEntrada(e.target.value)}
          />
        </label>
        <div className="form-acciones">
          <button className="btn" disabled={buscando || !entrada.trim()}>
            {buscando ? 'Buscando…' : 'Buscar'}
          </button>
        </div>
      </form>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

      {resultado && (
        <section className="tarjeta-simple verificar">
          <div className={`verificar__veredicto ${resultado.valido ? 'verificar__veredicto--ok' : 'verificar__veredicto--mal'}`}>
            <strong>{resultado.valido ? '✓ ' : '✕ '}{resultado.mensaje}</strong>
            <span>Comprobante {c.numero} · versión {c.version} · {c.estado}</span>
          </div>

          <dl className="importes">
            <dt>Reserva</dt><dd><strong>{r.codigo}</strong> · {r.estado_nombre}</dd>
            <dt>Titular</dt><dd>{r.titular}</dd>
            {r.documento && (<><dt>Documento</dt><dd>{r.documento}</dd></>)}
            {r.telefono && (<><dt>Teléfono</dt><dd>{r.telefono}</dd></>)}
            <dt>Habitación</dt><dd>{r.tipo}{r.habitacion ? ` · ${r.habitacion}` : ''}</dd>
            <dt>Estadía</dt><dd>{fechaCorta(r.fecha_llegada)} al {fechaCorta(r.fecha_salida)} · {r.noches} {r.noches === 1 ? 'noche' : 'noches'}</dd>
            <dt>Huéspedes</dt><dd>{r.adultos} {r.adultos === 1 ? 'adulto' : 'adultos'}{r.ninos ? ` y ${r.ninos} ${r.ninos === 1 ? 'niño' : 'niños'}` : ''}</dd>
            <dt>Total</dt><dd>{formatoUSD(r.total)}</dd>
            <dt>Pagado</dt><dd>{formatoUSD(r.pagado)}</dd>
            <dt className="importes__total">Saldo a cobrar</dt><dd className="importes__total">{formatoUSD(r.saldo)}</dd>
            <dt>Emitido</dt><dd>{fechaHora(c.emitido_at)}</dd>
            <dt>Verificado</dt>
            <dd>{c.verificado_at ? `${fechaHora(c.verificado_at)}${c.verificado_por ? ` por ${c.verificado_por}` : ''}` : 'Todavía no'}</dd>
          </dl>

          <div className="form-acciones">
            <button type="button" className="btn btn--ghost" onClick={verPdf}>Descargar PDF</button>
            {resultado.valido && !c.verificado_at && (
              <button type="button" className="btn" onClick={marcarVerificado} disabled={marcando}>
                {marcando ? 'Registrando…' : 'Marcar como verificado'}
              </button>
            )}
          </div>
        </section>
      )}
    </div>
  )
}
