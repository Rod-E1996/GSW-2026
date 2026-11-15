import { useState } from 'react'
import { api } from '../../api'

const ESTADOS = { pendiente: 'En revisión', aprobada: 'Publicada', rechazada: 'No publicada' }

// Valoracion de una estadia finalizada (una por reserva): estrellas del 1 al 5 y un comentario.
export default function ValorarEstadia({ reserva }) {
  const [valoracion, setValoracion] = useState(reserva.valoracion)
  const [puntuacion, setPuntuacion] = useState(0)
  const [comentario, setComentario] = useState('')
  const [enviando, setEnviando] = useState(false)
  const [error, setError] = useState(null)

  if (!valoracion && !reserva.puede_valorar) return null

  async function enviar(e) {
    e.preventDefault()
    if (!puntuacion) {
      setError('Elige de 1 a 5 estrellas.')
      return
    }
    setEnviando(true)
    setError(null)
    try {
      const res = await api.post(`/mi-cuenta/reservas/${reserva.codigo}/valoracion`, { puntuacion, comentario: comentario || null })
      setValoracion(res.data.data)
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo enviar tu valoración.')
    } finally {
      setEnviando(false)
    }
  }

  return (
    <section className="tarjeta-simple valorar">
      <h3>Tu valoración</h3>
      {valoracion ? (
        <>
          <p className="valorar__estrellas" aria-label={`${valoracion.puntuacion} de 5 estrellas`}>
            {'★'.repeat(valoracion.puntuacion)}{'☆'.repeat(5 - valoracion.puntuacion)}
          </p>
          {valoracion.comentario && <p>{valoracion.comentario}</p>}
          <p className="form-nota">{ESTADOS[valoracion.estado] || valoracion.estado}</p>
          {valoracion.respuesta && <p className="valorar__respuesta"><strong>Respuesta del hotel:</strong> {valoracion.respuesta}</p>}
        </>
      ) : (
        <form onSubmit={enviar}>
          <p className="form-nota">¿Cómo estuvo tu estadía?</p>
          <div className="valorar__selector" role="radiogroup" aria-label="Puntuación">
            {[1, 2, 3, 4, 5].map((n) => (
              <button
                key={n}
                type="button"
                role="radio"
                aria-checked={puntuacion === n}
                aria-label={`${n} ${n === 1 ? 'estrella' : 'estrellas'}`}
                className={n <= puntuacion ? 'is-activa' : ''}
                onClick={() => setPuntuacion(n)}
              >
                ★
              </button>
            ))}
          </div>
          <textarea rows="3" maxLength="1000" placeholder="Cuéntanos tu experiencia (opcional)" value={comentario} onChange={(e) => setComentario(e.target.value)} />
          {error && <p className="campo-error">{error}</p>}
          <button className="btn" disabled={enviando}>{enviando ? 'Enviando…' : 'Enviar valoración'}</button>
        </form>
      )}
    </section>
  )
}
