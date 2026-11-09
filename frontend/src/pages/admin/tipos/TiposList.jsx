import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { EstadoVacio } from '../../../components/ui'
import { rutaImagen, formatoUSD } from '../../../utils'
import '../../../styles/admin-hotel.css'

// Catalogo de tipos de habitacion en tarjetas: foto principal, capacidad y precio a la vista.
export default function TiposList() {
  const { can } = useAuth()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [nombre, setNombre] = useState('')
  const [buscado, setBuscado] = useState('')
  const [page, setPage] = useState(1)
  const [mensaje, setMensaje] = useState(null)
  const [error, setError] = useState(null)

  function cargar(filtro = buscado) {
    setCargando(true)
    api
      .get('/admin/tipos-habitacion', { params: { nombre: filtro || undefined, page } })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
      })
      .catch(() => setError('No se pudieron cargar los tipos de habitación.'))
      .finally(() => setCargando(false))
  }

  useEffect(() => {
    cargar()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page])

  function buscar(e) {
    e.preventDefault()
    setBuscado(nombre)
    if (page === 1) cargar(nombre)
    else setPage(1)
  }

  function limpiar() {
    setNombre('')
    setBuscado('')
    if (page === 1) cargar('')
    else setPage(1)
  }

  async function eliminar(tipo) {
    if (!confirm(`¿Eliminar "${tipo.nombre}"?`)) return
    try {
      const res = await api.delete(`/admin/tipos-habitacion/${tipo.id}`)
      setMensaje(res.data.message)
      setError(null)
      cargar()
    } catch (err) {
      setMensaje(null)
      setError(err.response?.data?.message || 'No se pudo eliminar.')
    }
  }

  return (
    <div>
      <div className="page-head">
        <div>
          <h1 className="page-title">Tipos de habitación</h1>
          <p className="page-sub">Lo que el huésped elige al reservar: capacidad, precio base y fotos.</p>
        </div>
        {can('tipo_habitacion_store') && (
          <Link to="/panel/tipos-habitacion/nuevo" className="btn">
            <Icono nombre="mas" size={16} /> Nuevo tipo
          </Link>
        )}
      </div>

      {mensaje && <div className="alerta alerta--ok">{mensaje}</div>}
      {error && <div className="alerta alerta--error">{error}</div>}

      <form className="filtros" onSubmit={buscar}>
        <input
          className="filtros__busqueda"
          placeholder="Buscar por nombre…"
          value={nombre}
          onChange={(e) => setNombre(e.target.value)}
          aria-label="Buscar tipo de habitación"
        />
        <button className="btn btn--ghost">Buscar</button>
        {buscado && <button type="button" className="btn btn--ghost" onClick={limpiar}>Limpiar</button>}
        {meta && <span className="tipos-total">{meta.total} {meta.total === 1 ? 'tipo' : 'tipos'}</span>}
      </form>

      {cargando && items.length === 0 && (
        <div className="tipos-grid">
          {Array.from({ length: 4 }, (_, i) => (
            <div key={i} className="tipo-card tipo-card--cargando">
              <div className="tipo-card__foto" />
              <div className="tipo-card__cuerpo"><span className="esqueleto" /><span className="esqueleto" /></div>
            </div>
          ))}
        </div>
      )}

      {!cargando && items.length === 0 && (
        <div className="tarjeta-simple">
          {buscado ? (
            <EstadoVacio
              icono="buscar"
              titulo={`Ningún tipo coincide con «${buscado}»`}
              texto="Revisa el nombre o quita el filtro."
              accion={<button type="button" className="btn btn--ghost" onClick={limpiar}>Quitar filtro</button>}
            />
          ) : (
            <EstadoVacio
              icono="cama"
              titulo="Aún no hay tipos de habitación"
              texto="Crea al menos uno (por ejemplo, Doble o Suite) para registrar habitaciones y recibir reservas."
              accion={can('tipo_habitacion_store') && <Link to="/panel/tipos-habitacion/nuevo" className="btn">Nuevo tipo</Link>}
            />
          )}
        </div>
      )}

      {items.length > 0 && (
        <div className={`tipos-grid ${cargando ? 'is-recargando' : ''}`}>
          {items.map((t) => {
            const foto = rutaImagen(t.imagen_principal)
            return (
              <article key={t.id} className="tipo-card">
                <div className="tipo-card__foto">
                  {foto ? <img src={foto} alt={t.nombre} loading="lazy" /> : (
                    <span className="tipo-card__sin-foto"><Icono nombre="foto" size={28} />Sin foto</span>
                  )}
                </div>
                <div className="tipo-card__cuerpo">
                  <div className="tipo-card__titulo">
                    <h3>{t.nombre}</h3>
                    {t.nombre_en && <span>{t.nombre_en}</span>}
                  </div>
                  <div className="tipo-card__datos">
                    <span><Icono nombre="personas" size={15} /> Hasta {t.capacidad} {t.capacidad === 1 ? 'persona' : 'personas'}</span>
                    <span className="tipo-card__precio">{formatoUSD(t.precio_base)}<small> / noche</small></span>
                  </div>
                  {t.descripcion && <p className="tipo-card__desc">{t.descripcion}</p>}
                  <footer className="tipo-card__pie">
                    <span className="form-nota">/{t.slug}</span>
                    <div className="tipo-card__acciones">
                      {can('tipo_habitacion_update') && (
                        <Link to={`/panel/tipos-habitacion/${t.id}/editar`} className="btn btn--sm btn--ghost">
                          <Icono nombre="editar" size={14} /> Editar
                        </Link>
                      )}
                      {can('tipo_habitacion_destroy') && (
                        <button className="btn btn--sm btn--danger" onClick={() => eliminar(t)} title="Eliminar" aria-label={`Eliminar ${t.nombre}`}>
                          <Icono nombre="eliminar" size={14} />
                        </button>
                      )}
                    </div>
                  </footer>
                </div>
              </article>
            )
          })}
        </div>
      )}

      {meta && meta.last_page > 1 && (
        <div className="paginacion">
          <button className="btn btn--ghost" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            ← Anterior
          </button>
          <span>
            Página {meta.current_page} de {meta.last_page}
          </span>
          <button className="btn btn--ghost" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>
            Siguiente →
          </button>
        </div>
      )}
    </div>
  )
}
