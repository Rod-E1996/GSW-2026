import { createContext, useContext, useState } from 'react'

// Portal publico en espanol e ingles. El idioma elegido se recuerda en el navegador.
const TEXTOS = {
  es: {
    'nav.habitaciones': 'Habitaciones', 'nav.servicios': 'Servicios', 'nav.galeria': 'Galería', 'nav.contacto': 'Contacto',
    'barra.panel': 'Panel', 'barra.cuenta': 'Mi cuenta', 'barra.salir': 'Salir',
    'hero.titulo': 'Tu descanso frente al mar, a un clic de distancia',
    'hero.sub': 'Reserva directo, sin comisiones. Playa El Tunco, La Libertad.',
    'hab.titulo': 'Nuestras habitaciones', 'hab.sub': 'Todas con baño privado, aire acondicionado y wifi.',
    'hab.cargando': 'Cargando habitaciones…', 'hab.error': 'No se pudieron cargar las habitaciones.',
    'hab.sin_foto': 'Sin foto', 'hab.capacidad': 'Hasta {n} personas', 'hab.noche': '/ noche', 'hab.fechas': 'Ver fechas',
    'serv.titulo': 'Todo lo que necesitas durante tu estadía',
    'serv.sub': 'Servicios complementarios que puedes solicitar al momento de reservar o directamente en recepción.',
    'cobro.por_persona_noche': 'Por persona y noche', 'cobro.por_persona': 'Por persona', 'cobro.por_noche': 'Por noche', 'cobro.fijo': 'Precio fijo',
    'opi.titulo': 'Lo que dicen nuestros huéspedes', 'opi.resumen': '{promedio} de 5 · {total} valoraciones', 'opi.respuesta': 'Respuesta del hotel:',
    'gal.titulo': 'Conoce el hotel', 'gal.sub': 'Nuestras instalaciones frente al mar.',
    'con.titulo': 'Estamos para ayudarte',
    'con.sub': 'Escríbenos o llámanos para consultar disponibilidad, tarifas de grupo o cualquier duda sobre tu visita.',
    'con.direccion': 'Dirección', 'con.mapa': 'Ver en el mapa', 'con.telefono': 'Teléfono', 'con.telefono_nota': 'Recepción, 24 horas',
    'con.whatsapp_nota': 'Reservaciones', 'con.correo': 'Correo', 'con.horarios': 'Horarios',
    'con.horarios_detalle': 'Check-in desde las {entrada} · Check-out hasta las {salida}',
    'con.reserva': 'Reserva en línea',
    'con.reserva_texto': 'Crea tu cuenta de huésped para consultar disponibilidad, reservar y llevar el historial de tus estadías.',
    'con.buscar': 'Buscar fechas', 'con.crear': 'Crear cuenta', 'con.entrar': 'Iniciar sesión',
    'pie.lugar': 'Playa El Tunco, La Libertad · Reservas directas con el hotel', 'pie.privacidad': 'Aviso de privacidad',
    'bus.llegada': 'Llegada', 'bus.salida': 'Salida', 'bus.huespedes': 'Huéspedes', 'bus.tipo': 'Tipo de habitación',
    'bus.todos': 'Todos', 'bus.buscar': 'Buscar',
    'bus.error_fechas': 'Indica las fechas de llegada y de salida.', 'bus.error_orden': 'La salida debe ser posterior a la llegada.',
  },
  en: {
    'nav.habitaciones': 'Rooms', 'nav.servicios': 'Services', 'nav.galeria': 'Gallery', 'nav.contacto': 'Contact',
    'barra.panel': 'Dashboard', 'barra.cuenta': 'My account', 'barra.salir': 'Sign out',
    'hero.titulo': 'Your beachfront getaway, one click away',
    'hero.sub': 'Book direct, no fees. Playa El Tunco, La Libertad.',
    'hab.titulo': 'Our rooms', 'hab.sub': 'All with private bathroom, air conditioning and wifi.',
    'hab.cargando': 'Loading rooms…', 'hab.error': 'The rooms could not be loaded.',
    'hab.sin_foto': 'No photo', 'hab.capacidad': 'Up to {n} guests', 'hab.noche': '/ night', 'hab.fechas': 'Check dates',
    'serv.titulo': 'Everything you need during your stay',
    'serv.sub': 'Extra services you can request when you book or directly at the front desk.',
    'cobro.por_persona_noche': 'Per person per night', 'cobro.por_persona': 'Per person', 'cobro.por_noche': 'Per night', 'cobro.fijo': 'Flat rate',
    'opi.titulo': 'What our guests say', 'opi.resumen': '{promedio} out of 5 · {total} reviews', 'opi.respuesta': 'Hotel reply:',
    'gal.titulo': 'Discover the hotel', 'gal.sub': 'Our beachfront facilities.',
    'con.titulo': 'We are here to help',
    'con.sub': 'Write or call us about availability, group rates or any question about your visit.',
    'con.direccion': 'Address', 'con.mapa': 'View on map', 'con.telefono': 'Phone', 'con.telefono_nota': 'Front desk, 24 hours',
    'con.whatsapp_nota': 'Bookings', 'con.correo': 'Email', 'con.horarios': 'Hours',
    'con.horarios_detalle': 'Check-in from {entrada} · Check-out until {salida}',
    'con.reserva': 'Book online',
    'con.reserva_texto': 'Create your guest account to check availability, book and keep a record of your stays.',
    'con.buscar': 'Search dates', 'con.crear': 'Create account', 'con.entrar': 'Sign in',
    'pie.lugar': 'Playa El Tunco, La Libertad · Book directly with the hotel', 'pie.privacidad': 'Privacy notice',
    'bus.llegada': 'Check-in', 'bus.salida': 'Check-out', 'bus.huespedes': 'Guests', 'bus.tipo': 'Room type',
    'bus.todos': 'All', 'bus.buscar': 'Search',
    'bus.error_fechas': 'Enter your check-in and check-out dates.', 'bus.error_orden': 'Check-out must be after check-in.',
  },
}

function inicial() {
  try {
    const guardado = localStorage.getItem('idioma')
    if (guardado === 'es' || guardado === 'en') return guardado
  } catch {
    // Sin almacenamiento: se usa el idioma del navegador
  }
  return navigator.language?.toLowerCase().startsWith('en') ? 'en' : 'es'
}

const IdiomaContext = createContext(null)

export function IdiomaProvider({ children }) {
  const [idioma, setIdiomaState] = useState(inicial)

  function setIdioma(valor) {
    try {
      localStorage.setItem('idioma', valor)
    } catch {
      // Sin almacenamiento: dura mientras la pagina este abierta
    }
    document.documentElement.lang = valor
    setIdiomaState(valor)
  }

  // t('clave', { variable }): texto del idioma activo; si falta, el espanol
  const t = (clave, vars = {}) =>
    (TEXTOS[idioma][clave] ?? TEXTOS.es[clave] ?? clave).replace(/\{(\w+)\}/g, (m, k) => vars[k] ?? m)

  // Campo bilingue de la API: nombre_en si el portal esta en ingles y existe
  const campo = (obj, nombre) => (idioma === 'en' && obj?.[`${nombre}_en`]) || obj?.[nombre] || ''

  return <IdiomaContext.Provider value={{ idioma, setIdioma, t, campo }}>{children}</IdiomaContext.Provider>
}

export function useIdioma() {
  return useContext(IdiomaContext)
}

export function SelectorIdioma() {
  const { idioma, setIdioma } = useIdioma()
  return (
    <div className="selector-idioma" role="group" aria-label="Idioma / Language">
      {['es', 'en'].map((i) => (
        <button key={i} type="button" className={i === idioma ? 'is-activo' : ''} aria-pressed={i === idioma} onClick={() => setIdioma(i)}>
          {i.toUpperCase()}
        </button>
      ))}
    </div>
  )
}
