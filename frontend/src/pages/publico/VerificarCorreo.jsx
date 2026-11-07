import { useEffect, useRef, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api } from '../../api'
import { useAuth } from '../../auth/AuthContext'
import BarraPublica from '../../components/BarraPublica'

// Destino del enlace del correo: reenvia la firma a la API.
export default function VerificarCorreo() {
  const [params] = useSearchParams()
  const { user, refrescar } = useAuth()
  const [estado, setEstado] = useState({ cargando: true, ok: false, mensaje: '' })
  const enviado = useRef(false)

  useEffect(() => {
    // StrictMode monta dos veces en desarrollo: la verificacion se pide una sola vez
    if (enviado.current) return
    enviado.current = true

    const id = params.get('id')
    const hash = params.get('hash')
    if (!id || !hash) {
      setEstado({ cargando: false, ok: false, mensaje: 'El enlace está incompleto.' })
      return
    }

    api
      .get(`/correo/verificar/${id}/${hash}`, {
        params: { expires: params.get('expires'), signature: params.get('signature') },
      })
      .then((res) => {
        setEstado({ cargando: false, ok: true, mensaje: res.data.message })
        if (user) refrescar()
      })
      .catch((err) => {
        setEstado({
          cargando: false,
          ok: false,
          mensaje: err.response?.data?.message || 'No se pudo verificar el correo.',
        })
      })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  return (
    <div className="app">
      <BarraPublica />
      <main className="pagina-simple">
        <div className="tarjeta-simple">
          <h1>Verificación de correo</h1>
          {estado.cargando ? (
            <p className="estado">Verificando…</p>
          ) : (
            <>
              <div className={`alerta ${estado.ok ? 'alerta--ok' : 'alerta--error'}`}>{estado.mensaje}</div>
              <Link to="/mi-cuenta" className="btn">Ir a Mi cuenta</Link>
            </>
          )}
        </div>
      </main>
    </div>
  )
}
