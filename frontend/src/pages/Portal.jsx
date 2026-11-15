import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api } from '../api'
import BuscadorDisponibilidad from './publico/BuscadorDisponibilidad'
import { useAuth } from '../auth/AuthContext'
import { SelectorIdioma, useIdioma } from '../i18n'

// Convierte la URL absoluta de la imagen a ruta relativa para usar el proxy de Vite.
function rutaImagen(url) {
  if (!url) return null
  try {
    return new URL(url).pathname
  } catch {
    return url
  }
}

export default function Portal() {
  const navigate = useNavigate()
  const { user } = useAuth()
  const { idioma, t, campo } = useIdioma()
  const [tipos, setTipos] = useState([])
  const [servicios, setServicios] = useState([])
  const [hotel, setHotel] = useState(null)
  const [opiniones, setOpiniones] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(false)

  const usd = (valor) => new Intl.NumberFormat(idioma === 'en' ? 'en-US' : 'es-SV', { style: 'currency', currency: 'USD' }).format(valor)

  useEffect(() => {
    api
      .get('/tipos-habitacion')
      .then((res) => setTipos(res.data.data))
      .catch(() => setError(true))
      .finally(() => setCargando(false))
    api.get('/servicios').then((res) => setServicios(res.data.data)).catch(() => {})
    api.get('/hotel').then((res) => setHotel(res.data.data)).catch(() => {})
    api.get('/valoraciones').then((res) => setOpiniones(res.data)).catch(() => {})
  }, [])

  function buscar(form) {
    const datos = Object.entries(form).filter(([, valor]) => valor !== '')
    navigate(`/disponibilidad?${new URLSearchParams(datos)}`)
  }

  return (
    <div className="app">
      <header className="hero">
        <div className="hero__inner">
          <div className="hero__top">
            <span className="brand">
              hotel<span className="brand__accent">link</span>
            </span>
            <nav className="hero__nav">
              <a href="#habitaciones">{t('nav.habitaciones')}</a>
              <a href="#servicios">{t('nav.servicios')}</a>
              <a href="#galeria">{t('nav.galeria')}</a>
              <a href="#contacto">{t('nav.contacto')}</a>
            </nav>
            <div className="hero__acciones">
              <SelectorIdioma />
              <Link to={user?.acceso_panel ? '/panel' : '/mi-cuenta'} className="hero__login">
                {user?.acceso_panel ? t('barra.panel') : t('barra.cuenta')}
              </Link>
            </div>
          </div>
          <h1>{t('hero.titulo')}</h1>
          <p>{t('hero.sub')}</p>
          <div id="buscar" className="hero__buscador">
            <BuscadorDisponibilidad onBuscar={buscar} />
          </div>
        </div>
      </header>

      <main className="contenido">
        <section id="habitaciones" className="portal-seccion">
          <div className="contenido__encabezado">
            <h2>{t('hab.titulo')}</h2>
            <p>{t('hab.sub')}</p>
          </div>

          {cargando && <p className="estado">{t('hab.cargando')}</p>}
          {error && <p className="estado estado--error">{t('hab.error')}</p>}

          <div className="grid">
            {tipos.map((tipo) => (
              <article key={tipo.id} className="card">
                <div className="card__img">
                  {rutaImagen(tipo.imagen_principal) ? (
                    <img src={rutaImagen(tipo.imagen_principal)} alt={campo(tipo, 'nombre')} loading="lazy" />
                  ) : (
                    <div className="card__img--placeholder">{t('hab.sin_foto')}</div>
                  )}
                </div>
                <div className="card__body">
                  <h3>{campo(tipo, 'nombre')}</h3>
                  <p className="card__cap">{t('hab.capacidad', { n: tipo.capacidad })}</p>
                  <p className="card__desc">{campo(tipo, 'descripcion')}</p>
                  <div className="card__footer">
                    <span className="precio">
                      {usd(tipo.precio_base)} <small>{t('hab.noche')}</small>
                    </span>
                    <a href="#buscar" className="btn btn--ghost btn--sm">
                      {t('hab.fechas')}
                    </a>
                  </div>
                </div>
              </article>
            ))}
          </div>
        </section>

        {servicios.length > 0 && (
          <section id="servicios" className="portal-seccion">
            <div className="contenido__encabezado">
              <h2>{t('serv.titulo')}</h2>
              <p>{t('serv.sub')}</p>
            </div>
            <div className="servicios-grid">
              {servicios.map((s) => (
                <article key={s.id} className="servicio-item">
                  <span className="servicio-item__categoria">{campo(s, 'categoria')}</span>
                  <h3>{campo(s, 'nombre')}</h3>
                  {campo(s, 'descripcion') && <p>{campo(s, 'descripcion')}</p>}
                  <span className="servicio-item__precio">{usd(s.precio)} · {t(`cobro.${s.forma_cobro}`)}</span>
                </article>
              ))}
            </div>
          </section>
        )}

        {opiniones?.total > 0 && (
          <section id="opiniones" className="portal-seccion">
            <div className="contenido__encabezado">
              <h2>{t('opi.titulo')}</h2>
              <p>
                <span className="valorar__estrellas">★</span> {t('opi.resumen', { promedio: opiniones.promedio, total: opiniones.total })}
              </p>
            </div>
            <div className="servicios-grid">
              {opiniones.data.map((o) => (
                <article key={o.id} className="servicio-item">
                  <span className="valorar__estrellas" aria-label={`${o.puntuacion} / 5`}>{'★'.repeat(o.puntuacion)}{'☆'.repeat(5 - o.puntuacion)}</span>
                  {o.comentario && <p>“{o.comentario}”</p>}
                  <span className="form-nota">{o.huesped}{o.habitacion ? ` · ${o.habitacion}` : ''}</span>
                  {o.respuesta && <p className="valorar__respuesta"><strong>{t('opi.respuesta')}</strong> {o.respuesta}</p>}
                </article>
              ))}
            </div>
          </section>
        )}

        {hotel?.galeria?.length > 0 && (
          <section id="galeria" className="portal-seccion">
            <div className="contenido__encabezado">
              <h2>{t('gal.titulo')}</h2>
              <p>{t('gal.sub')}</p>
            </div>
            <div className="galeria-grid">
              {hotel.galeria.map((foto) => (
                <a key={foto.url} className="galeria-item" href={foto.url} target="_blank" rel="noopener noreferrer">
                  <img src={foto.url} alt={foto.titulo} loading="lazy" />
                  <span>{foto.titulo}</span>
                </a>
              ))}
            </div>
          </section>
        )}

        {hotel && (
          <section id="contacto" className="portal-seccion">
            <div className="contenido__encabezado">
              <h2>{t('con.titulo')}</h2>
              <p>{t('con.sub')}</p>
            </div>
            <div className="contacto-grid">
              <ul className="contacto-card contacto-lista">
                <li>
                  <strong>{t('con.direccion')}</strong>
                  <span>{hotel.direccion}</span>
                  <a href={hotel.mapa_url} target="_blank" rel="noopener noreferrer">{t('con.mapa')}</a>
                </li>
                <li>
                  <strong>{t('con.telefono')}</strong>
                  <span>{hotel.telefono} · {t('con.telefono_nota')}</span>
                </li>
                <li>
                  <strong>WhatsApp</strong>
                  <span>{hotel.whatsapp} · {t('con.whatsapp_nota')}</span>
                </li>
                <li>
                  <strong>{t('con.correo')}</strong>
                  <a href={`mailto:${hotel.email}`}>{hotel.email}</a>
                </li>
                <li>
                  <strong>{t('con.horarios')}</strong>
                  <span>{t('con.horarios_detalle', { entrada: hotel.checkin, salida: hotel.checkout })}</span>
                </li>
              </ul>
              <div className="contacto-card">
                <h3>{t('con.reserva')}</h3>
                <p>{t('con.reserva_texto')}</p>
                <div className="contacto-card__acciones">
                  {user ? (
                    <a href="#buscar" className="btn">{t('con.buscar')}</a>
                  ) : (
                    <>
                      <Link to="/registro" className="btn">{t('con.crear')}</Link>
                      <Link to="/login" className="btn btn--ghost">{t('con.entrar')}</Link>
                    </>
                  )}
                </div>
              </div>
            </div>
          </section>
        )}
      </main>

      <footer className="pie">
        <span className="brand brand--dark">hotel<span className="brand__accent">link</span></span>
        <span>{t('pie.lugar')}</span>
        <Link to="/privacidad">{t('pie.privacidad')}</Link>
      </footer>
    </div>
  )
}
