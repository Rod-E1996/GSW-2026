import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { api } from '../../api'
import { fechaCorta, formatoUSD, rutaImagen } from '../../utils'
import BuscadorDisponibilidad from './BuscadorDisponibilidad'
import BarraPublica from '../../components/BarraPublica'

const FILTROS_VACIOS = { tipo_habitacion_id: '', capacidad: '', precio_min: '', precio_max: '' }

// Pasa el formulario a la direccion, para que recargar o compartir la busqueda funcione.
function aParametros(datos) {
  return Object.fromEntries(Object.entries(datos).filter(([, valor]) => valor !== '' && valor != null))
}

export default function Resultados() {
  const navigate = useNavigate()
  const [params, setParams] = useSearchParams()
  const consulta = Object.fromEntries(params.entries())

  const [respuesta, setRespuesta] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(null)
  const [editando, setEditando] = useState(false)
  const [filtros, setFiltros] = useState(FILTROS_VACIOS)

  const clave = params.toString()

  useEffect(() => {
    setFiltros({ ...FILTROS_VACIOS, ...consulta })
    setCargando(true)
    setError(null)
    api
      .get('/disponibilidad', { params: consulta })
      .then((res) => setRespuesta(res.data))
      .catch((err) => {
        setRespuesta(null)
        const errores = err.response?.data?.errors
        setError(errores ? Object.values(errores)[0][0] : 'No se pudo consultar la disponibilidad.')
        setEditando(true)
      })
      .finally(() => setCargando(false))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [clave])

  function buscar(form) {
    setEditando(false)
    setParams(aParametros(form))
  }

  function aplicarFiltros(e) {
    e.preventDefault()
    setParams(aParametros({ ...consulta, ...filtros }))
  }

  function limpiarFiltros() {
    setParams(aParametros({ ...consulta, ...FILTROS_VACIOS }))
  }

  function verTipo(id) {
    setParams(aParametros({ ...consulta, ...FILTROS_VACIOS, tipo_habitacion_id: id }))
  }

  function verFechas(f) {
    setParams(aParametros({ ...consulta, llegada: f.llegada, salida: f.salida }))
  }

  const meta = respuesta?.meta
  const items = respuesta?.data || []
  const alternativas = respuesta?.alternativas
  const hayFiltros = filtros.capacidad || filtros.precio_min || filtros.precio_max

  return (
    <div className="app">
      <BarraPublica />

      <div className="resumen-busqueda">
        <div className="resumen-busqueda__datos">
          {meta ? (
            <span>
              {fechaCorta(meta.llegada)} → {fechaCorta(meta.salida)} · {meta.noches} {meta.noches === 1 ? 'noche' : 'noches'} · {meta.huespedes} {Number(meta.huespedes) === 1 ? 'huésped' : 'huéspedes'}
            </span>
          ) : (
            <span>Indica tu estadía</span>
          )}
          <button className="btn btn--sm btn--ghost" onClick={() => setEditando((v) => !v)}>
            {editando ? 'Cerrar' : 'Editar'}
          </button>
        </div>
        {editando && (
          <BuscadorDisponibilidad key={clave} inicial={consulta} onBuscar={buscar} />
        )}
      </div>

      <main className="resultados">
        <aside className="resultados__filtros">
          <h2>Filtros</h2>
          <form onSubmit={aplicarFiltros}>
            <label>
              Tipo de habitación
              <select
                value={filtros.tipo_habitacion_id}
                onChange={(e) => setFiltros((f) => ({ ...f, tipo_habitacion_id: e.target.value }))}
              >
                <option value="">Todos</option>
                {(respuesta?.filtros.tipos || []).map((t) => (
                  <option key={t.id} value={t.id}>{t.nombre}</option>
                ))}
              </select>
            </label>
            <label>
              Capacidad mínima
              <select
                value={filtros.capacidad}
                onChange={(e) => setFiltros((f) => ({ ...f, capacidad: e.target.value }))}
              >
                <option value="">Cualquiera</option>
                {(respuesta?.filtros.capacidades || []).map((c) => (
                  <option key={c} value={c}>{c} {c === 1 ? 'persona' : 'personas'}</option>
                ))}
              </select>
            </label>
            <div className="resultados__rango">
              <label>
                Precio mín.
                <input
                  type="number"
                  min="0"
                  step="1"
                  value={filtros.precio_min}
                  onChange={(e) => setFiltros((f) => ({ ...f, precio_min: e.target.value }))}
                />
              </label>
              <label>
                Precio máx.
                <input
                  type="number"
                  min="0"
                  step="1"
                  value={filtros.precio_max}
                  onChange={(e) => setFiltros((f) => ({ ...f, precio_max: e.target.value }))}
                />
              </label>
            </div>
            <button className="btn">Aplicar</button>
            {(hayFiltros || filtros.tipo_habitacion_id) && (
              <button type="button" className="btn btn--ghost" onClick={limpiarFiltros}>
                Limpiar
              </button>
            )}
          </form>
        </aside>

        <section className="resultados__lista" aria-live="polite">
          {cargando && <p className="estado">Buscando habitaciones…</p>}
          {!cargando && error && <p className="estado estado--error">{error}</p>}

          {!cargando && meta && items.length > 0 && (
            <p className="resultados__conteo">
              {items.length} {items.length === 1 ? 'habitación disponible' : 'habitaciones disponibles'}
            </p>
          )}

          {!cargando && items.map((h) => (
            <article key={h.id} className="resultado">
              <div className="resultado__img">
                {rutaImagen(h.tipo.imagen_principal) ? (
                  <img src={rutaImagen(h.tipo.imagen_principal)} alt={h.tipo.nombre} loading="lazy" />
                ) : (
                  <div className="card__img--placeholder">Sin foto</div>
                )}
              </div>
              <div className="resultado__cuerpo">
                <h3>{h.tipo.nombre} · Habitación {h.numero}</h3>
                <p className="card__cap">Hasta {h.tipo.capacidad} {h.tipo.capacidad === 1 ? 'persona' : 'personas'} · Piso {h.piso}</p>
                <p className="card__desc">{h.tipo.descripcion}</p>
              </div>
              <div className="resultado__precio">
                <span className="precio">{formatoUSD(h.precio_noche)} <small>/ noche</small></span>
                <span className="resultado__total">{formatoUSD(h.total)} en total</span>
                <small className="resultado__nota">Antes de impuestos</small>
                <Link
                  className="btn"
                  to={`/reservar?habitacion=${h.id}&llegada=${meta.llegada}&salida=${meta.salida}&huespedes=${meta.huespedes}`}
                >
                  Reservar
                </Link>
              </div>
            </article>
          ))}

          {!cargando && meta && items.length === 0 && !alternativas && (
            <div className="sin-resultados">
              <h3>Ninguna habitación coincide con los filtros</h3>
              <button className="btn btn--ghost" onClick={limpiarFiltros}>Quitar filtros</button>
            </div>
          )}

          {!cargando && alternativas && (
            <div className="sin-resultados">
              <h3>No hay habitaciones disponibles para esa búsqueda</h3>

              {alternativas.tipos.length > 0 && (
                <>
                  <p>En las mismas fechas sí tenemos:</p>
                  <ul className="alternativas">
                    {alternativas.tipos.map((t) => (
                      <li key={t.id}>
                        <span>{t.nombre} · {formatoUSD(t.precio_noche)} / noche · {formatoUSD(t.total)} en total</span>
                        <button className="btn btn--sm btn--ghost" onClick={() => verTipo(t.id)}>Ver</button>
                      </li>
                    ))}
                  </ul>
                </>
              )}

              {alternativas.fechas.length > 0 && (
                <>
                  <p>O puedes viajar en estas fechas cercanas:</p>
                  <ul className="alternativas">
                    {alternativas.fechas.map((f) => (
                      <li key={f.llegada}>
                        <span>{fechaCorta(f.llegada)} → {fechaCorta(f.salida)} · {f.disponibles} {f.disponibles === 1 ? 'disponible' : 'disponibles'}</span>
                        <button className="btn btn--sm btn--ghost" onClick={() => verFechas(f)}>Ver</button>
                      </li>
                    ))}
                  </ul>
                </>
              )}

              {alternativas.tipos.length === 0 && alternativas.fechas.length === 0 && (
                <p>Prueba con otras fechas o menos huéspedes.</p>
              )}
            </div>
          )}

          {!cargando && !meta && !error && (
            <button className="btn" onClick={() => navigate('/')}>Ir al inicio</button>
          )}
        </section>
      </main>

      <footer className="pie">
        HotelLink · Portal de reservas · Frontend React + API Laravel
      </footer>
    </div>
  )
}
