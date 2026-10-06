import { useEffect, useState } from 'react'
import { api } from './api'

// Convierte la URL absoluta de la imagen a ruta relativa para usar el proxy de Vite
// (asi funciona sin importar el dominio con que se sirva Laravel).
function rutaImagen(url) {
  if (!url) return null
  try {
    return new URL(url).pathname
  } catch {
    return url
  }
}

function formatoUSD(valor) {
  return new Intl.NumberFormat('es-SV', { style: 'currency', currency: 'USD' }).format(valor)
}

export default function App() {
  const [tipos, setTipos] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    api
      .get('/tipos-habitacion')
      .then((res) => setTipos(res.data.data))
      .catch(() => setError('No se pudieron cargar las habitaciones.'))
      .finally(() => setCargando(false))
  }, [])

  return (
    <div className="app">
      <header className="hero">
        <div className="hero__inner">
          <span className="brand">
            hotel<span className="brand__accent">link</span>
          </span>
          <h1>Tu descanso frente al mar, a un clic de distancia</h1>
          <p>Reserva directo, sin comisiones. Playa El Tunco, La Libertad.</p>
        </div>
      </header>

      <main className="contenido">
        <h2>Nuestras habitaciones</h2>

        {cargando && <p className="estado">Cargando habitaciones…</p>}
        {error && <p className="estado estado--error">{error}</p>}

        <div className="grid">
          {tipos.map((tipo) => (
            <article key={tipo.id} className="card">
              <div className="card__img">
                {rutaImagen(tipo.imagen_principal) ? (
                  <img src={rutaImagen(tipo.imagen_principal)} alt={tipo.nombre} loading="lazy" />
                ) : (
                  <div className="card__img--placeholder">Sin foto</div>
                )}
              </div>
              <div className="card__body">
                <h3>{tipo.nombre}</h3>
                <p className="card__cap">Hasta {tipo.capacidad} personas</p>
                <p className="card__desc">{tipo.descripcion}</p>
                <div className="card__footer">
                  <span className="precio">
                    {formatoUSD(tipo.precio_base)} <small>/ noche</small>
                  </span>
                  <button className="btn" disabled>
                    Reservar
                  </button>
                </div>
              </div>
            </article>
          ))}
        </div>
      </main>

      <footer className="pie">
        HotelLink · Portal de reservas · Frontend React + API Laravel
      </footer>
    </div>
  )
}
