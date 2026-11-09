import { iniciales } from '../../../components/ui'

// El catalogo guarda nombres de Bootstrap Icons (bi-cup-hot...), pero el panel React no carga
// esa fuente: aqui se dibujan con trazos propios los que usa el hotel. Si no hay uno, se
// muestran las iniciales del nombre.
const TRAZOS = {
  'bi-cup-hot': <path d="M17 8h1a4 4 0 1 1 0 8h-1M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4zM6 2v2M10 2v2M14 2v2" />,
  'bi-basket': <path d="m15 11-1 9M19 11l-4-7M2 11h20M3.5 11l1.6 7.4a2 2 0 0 0 2 1.6h9.8a2 2 0 0 0 2-1.6l1.7-7.4M4.5 15.5h15M5 11l4-7M9 11l1 9" />,
  'bi-car-front': (
    <>
      <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9L18.4 10 16 6.3A2 2 0 0 0 14.3 5H9.7a2 2 0 0 0-1.7.9L5.6 10l-2.1 1.1C2.7 11.3 2 12.1 2 13v3c0 .6.4 1 1 1h2" />
      <circle cx="7" cy="17" r="2" />
      <path d="M9 17h6" />
      <circle cx="17" cy="17" r="2" />
    </>
  ),
  'bi-people': (
    <>
      <circle cx="9" cy="7" r="4" />
      <path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2M16 3.1a4 4 0 0 1 0 7.8M21 21v-2a4 4 0 0 0-3-3.9" />
    </>
  ),
  'bi-water': <path d="M2 12c2-2 4-2 6 0s4 2 6 0 4-2 6 0M2 17c2-2 4-2 6 0s4 2 6 0 4-2 6 0M2 7c2-2 4-2 6 0s4 2 6 0 4-2 6 0" />,
  'bi-geo-alt': (
    <>
      <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z" />
      <circle cx="12" cy="10" r="3" />
    </>
  ),
  'bi-flower1': (
    <>
      <circle cx="12" cy="12" r="3" />
      <path d="M12 16.5A3 3 0 1 1 7.5 12 3 3 0 1 1 12 7.5a3 3 0 1 1 4.5 4.5 3 3 0 1 1-4.5 4.5M12 7.5V9M7.5 12H9M16.5 12H15M12 16.5V15" />
    </>
  ),
  'bi-heart': <path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7z" />,
  'bi-p-square': (
    <>
      <rect x="3" y="3" width="18" height="18" rx="2" />
      <path d="M9 17V7h4a3 3 0 0 1 0 6H9" />
    </>
  ),
  'bi-clock-history': (
    <>
      <path d="M3 12a9 9 0 1 0 9-9 9.7 9.7 0 0 0-6.7 2.7L3 8" />
      <path d="M3 3v5h5M12 7v5l4 2" />
    </>
  ),
  'bi-bell': <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.9 1.9 0 0 0 3.4 0" />,
}

// Tono de cada categoria (se elige por su id para que no cambie entre pantallas)
const TONOS = ['ola', 'azul', 'ok', 'mango', 'lila']

export function tonoCategoria(id) {
  return TONOS[(Number(id) || 0) % TONOS.length]
}

export function tieneIcono(nombre) {
  return Boolean(TRAZOS[nombre])
}

// Recuadro con el icono del servicio o, si no hay, sus iniciales
export function IconoServicio({ icono, nombre, tono = 'ola', size = 'md' }) {
  const trazo = TRAZOS[icono]
  return (
    <span className={`icono-servicio icono-servicio--${tono} icono-servicio--${size}`} aria-hidden="true">
      {trazo ? (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
          {trazo}
        </svg>
      ) : (
        <b>{iniciales(nombre) || '?'}</b>
      )}
    </span>
  )
}

export const ICONOS_SUGERIDOS = Object.keys(TRAZOS)
