import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../../api'
import { formatoUSD } from '../../utils'

const TARJETAS = [
  { numero: '4242 4242 4242 4242', resultado: 'Aprobada' },
  { numero: '4000 0000 0000 0002', resultado: 'Rechazada por el banco' },
  { numero: '4000 0000 0000 9995', resultado: 'Fondos insuficientes' },
]

// Vuelve al portal: la URL de retorno es absoluta, pero apunta a este mismo portal.
function rutaRelativa(url) {
  try {
    const u = new URL(url)
    return u.pathname + u.search
  } catch {
    return url
  }
}

function conEspacios(valor) {
  return valor.replace(/\D/g, '').slice(0, 19).replace(/(\d{4})(?=\d)/g, '$1 ')
}

function conBarra(valor) {
  const digitos = valor.replace(/\D/g, '').slice(0, 4)
  return digitos.length > 2 ? `${digitos.slice(0, 2)}/${digitos.slice(2)}` : digitos
}

// Pagina de pago de la pasarela de pruebas: hace las veces de la pagina externa de una pasarela real.
export default function PasarelaPruebas() {
  const { referencia } = useParams()
  const navigate = useNavigate()
  const [cobro, setCobro] = useState(null)
  const [error, setError] = useState(null)
  const [errores, setErrores] = useState({})
  const [enviando, setEnviando] = useState(false)
  const [tarjeta, setTarjeta] = useState({ numero: '', titular: '', vencimiento: '', cvv: '' })

  useEffect(() => {
    api
      .get(`/pasarela-pruebas/${referencia}`)
      .then((res) => setCobro(res.data.data))
      .catch((err) => setError(err.response?.status === 404 ? 'Este cobro no existe.' : 'No se pudo cargar el cobro.'))
  }, [referencia])

  function cambiar(campo, valor) {
    setTarjeta((actual) => ({ ...actual, [campo]: valor }))
  }

  async function pagar(e) {
    e.preventDefault()
    setEnviando(true)
    setError(null)
    setErrores({})
    try {
      const res = await api.post(`/pasarela-pruebas/${referencia}`, tarjeta)
      setCobro(res.data.data)
    } catch (err) {
      const data = err.response?.data
      if (err.response?.status === 422 && data?.errors) {
        setErrores(data.errors)
      } else if (err.response?.status === 409 && data?.data) {
        setCobro(data.data)
      } else {
        setError(data?.message || 'No se pudo procesar el pago.')
      }
    } finally {
      setEnviando(false)
    }
  }

  const campoError = (campo) => errores[campo] && <small className="campo-error">{errores[campo][0]}</small>

  return (
    <div className="pasarela">
      <header className="pasarela__barra">
        <span className="pasarela__marca">Pasarela de pruebas</span>
        <span className="pasarela__aviso">Ambiente de pruebas · no se realizan cargos reales</span>
      </header>

      <main className="pasarela__contenido">
        {!cobro ? (
          error ? (
            <div className="tarjeta-simple">
              <div className="alerta alerta--error">{error}</div>
              <Link to="/mi-cuenta" className="btn btn--ghost">Ir a Mi cuenta</Link>
            </div>
          ) : (
            <p className="estado">Cargando…</p>
          )
        ) : (
          <div className="tarjeta-simple pasarela__tarjeta">
            <div className="pasarela__cobro">
              <span className="form-nota">{cobro.comercio}</span>
              <strong>{cobro.concepto}</strong>
              <span className="pasarela__monto">{formatoUSD(cobro.monto)}</span>
            </div>

            {cobro.estado === 'pendiente' ? (
              <form className="form" onSubmit={pagar} noValidate>
                {error && <div className="alerta alerta--error">{error}</div>}
                <label>
                  Número de tarjeta
                  <input
                    inputMode="numeric"
                    autoComplete="off"
                    placeholder="4242 4242 4242 4242"
                    value={tarjeta.numero}
                    onChange={(e) => cambiar('numero', conEspacios(e.target.value))}
                  />
                  {campoError('numero')}
                </label>
                <label>
                  Nombre en la tarjeta
                  <input autoComplete="off" value={tarjeta.titular} onChange={(e) => cambiar('titular', e.target.value)} />
                  {campoError('titular')}
                </label>
                <div className="pasarela__fila">
                  <label>
                    Vencimiento
                    <input
                      inputMode="numeric"
                      autoComplete="off"
                      placeholder="MM/AA"
                      value={tarjeta.vencimiento}
                      onChange={(e) => cambiar('vencimiento', conBarra(e.target.value))}
                    />
                    {campoError('vencimiento')}
                  </label>
                  <label>
                    CVV
                    <input
                      inputMode="numeric"
                      autoComplete="off"
                      maxLength="4"
                      value={tarjeta.cvv}
                      onChange={(e) => cambiar('cvv', e.target.value.replace(/\D/g, ''))}
                    />
                    {campoError('cvv')}
                  </label>
                </div>
                <button className="btn btn--block" disabled={enviando}>
                  {enviando ? 'Procesando…' : `Pagar ${formatoUSD(cobro.monto)}`}
                </button>
                <button type="button" className="btn btn--ghost btn--block" onClick={() => navigate(rutaRelativa(cobro.url_retorno))}>
                  Cancelar y volver al hotel
                </button>
              </form>
            ) : (
              <div className="pasarela__resultado">
                {cobro.estado === 'aprobado' ? (
                  <div className="alerta alerta--ok">Pago aprobado.</div>
                ) : (
                  <div className="alerta alerta--error">Pago rechazado{cobro.motivo ? `: ${cobro.motivo}` : '.'}</div>
                )}
                <button type="button" className="btn btn--block" onClick={() => navigate(rutaRelativa(cobro.url_retorno))}>
                  Volver al hotel
                </button>
              </div>
            )}
          </div>
        )}

        {cobro?.estado === 'pendiente' && (
          <section className="tarjeta-simple pasarela__ayuda">
            <h3>Tarjetas de prueba</h3>
            <p className="form-nota">Usa cualquier nombre, un vencimiento futuro y cualquier CVV de 3 dígitos.</p>
            <ul>
              {TARJETAS.map((t) => (
                <li key={t.numero}>
                  <button type="button" className="pasarela__usar" onClick={() => cambiar('numero', t.numero)}>
                    {t.numero}
                  </button>
                  <span>{t.resultado}</span>
                </li>
              ))}
            </ul>
          </section>
        )}
      </main>
    </div>
  )
}
