import { useState } from 'react'
import { api } from '../api'
import { useAuth } from '../auth/AuthContext'

// Recordatorio para quien aun no verifica su correo, con opcion de pedir otro enlace.
export default function AvisoVerificacion() {
  const { user } = useAuth()
  const [mensaje, setMensaje] = useState(null)
  const [enviando, setEnviando] = useState(false)

  if (!user || user.email_verificado) return null

  async function reenviar() {
    setEnviando(true)
    try {
      const res = await api.post('/correo/reenviar')
      setMensaje(res.data.message)
    } catch (err) {
      setMensaje(err.response?.data?.message || 'No se pudo enviar el correo.')
    } finally {
      setEnviando(false)
    }
  }

  return (
    <div className="alerta alerta--aviso aviso-verificacion">
      <span>
        Verifica tu correo <strong>{user.email}</strong> para poder reservar. Revisa tu bandeja de entrada.
      </span>
      {mensaje ? (
        <span>{mensaje}</span>
      ) : (
        <button type="button" className="btn btn--sm btn--ghost" onClick={reenviar} disabled={enviando}>
          {enviando ? 'Enviando…' : 'Enviar otro enlace'}
        </button>
      )}
    </div>
  )
}
