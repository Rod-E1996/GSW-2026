import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { FilaVacia, FilasCargando } from '../../../components/ui'
import { ICONOS_SUGERIDOS, IconoServicio, tonoCategoria } from './iconosServicio'
import '../../../styles/admin-catalogo.css'

const VACIO = { nombre: '', nombre_en: '', icono: '', orden: 0 }

export default function CategoriasList() {
  const { can } = useAuth()
  const [items, setItems] = useState([])
  const [form, setForm] = useState(VACIO)
  const [editandoId, setEditandoId] = useState(null)
  const [errores, setErrores] = useState({})
  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)
  const [mensaje, setMensaje] = useState(null)
  const [error, setError] = useState(null)

  function cargar() {
    setCargando(true)
    api
      .get('/admin/categorias-servicio')
      .then((res) => setItems(res.data.data))
      .catch(() => setError('No se pudieron cargar las categorías.'))
      .finally(() => setCargando(false))
  }

  useEffect(() => {
    cargar()
  }, [])

  function set(campo, valor) {
    setForm((f) => ({ ...f, [campo]: valor }))
  }

  function limpiar() {
    setForm(VACIO)
    setEditandoId(null)
    setErrores({})
  }

  function editar(c) {
    setEditandoId(c.id)
    setErrores({})
    setMensaje(null)
    setForm({ nombre: c.nombre, nombre_en: c.nombre_en || '', icono: c.icono || '', orden: c.orden ?? 0 })
  }

  async function onSubmit(e) {
    e.preventDefault()
    setGuardando(true)
    setErrores({})
    setError(null)
    setMensaje(null)
    try {
      if (editandoId) {
        await api.put(`/admin/categorias-servicio/${editandoId}`, form)
      } else {
        await api.post('/admin/categorias-servicio', form)
      }
      setMensaje(editandoId ? 'Categoría actualizada.' : 'Categoría creada.')
      limpiar()
      cargar()
    } catch (err) {
      if (err.response?.status === 422) {
        setErrores(err.response.data.errors || {})
      } else {
        setError(err.response?.data?.message || 'No se pudo guardar.')
      }
    } finally {
      setGuardando(false)
    }
  }

  async function eliminar(c) {
    if (!confirm(`¿Eliminar la categoría "${c.nombre}"?`)) return
    setError(null)
    setMensaje(null)
    try {
      const res = await api.delete(`/admin/categorias-servicio/${c.id}`)
      setMensaje(res.data.message)
      if (editandoId === c.id) limpiar()
      cargar()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo eliminar.')
    }
  }

  const puedeEscribir = editandoId ? can('categoria_servicio_update') : can('categoria_servicio_store')
  const err = (campo) => errores[campo] && <small className="campo-error">{errores[campo][0]}</small>
  const tonoForm = tonoCategoria(editandoId || items.length + 1)

  return (
    <div className="catalogo">
      <Link to="/panel/servicios" className="volver"><Icono nombre="volver" size={16} /> Servicios</Link>
      <div className="page-head">
        <div>
          <h1 className="page-title">Categorías de servicio</h1>
          <p className="page-sub">Agrupan el catálogo en el portal. El orden define en qué posición aparece cada grupo.</p>
        </div>
      </div>

      {mensaje && <div className="alerta alerta--ok">{mensaje}</div>}
      {error && <div className="alerta alerta--error">{error}</div>}

      <div className={`dos-columnas ${puedeEscribir ? '' : 'dos-columnas--sola'}`}>
        <div className="tabla-wrap">
          <table className="tabla">
            <thead>
              <tr>
                <th className="tabla__num">Orden</th>
                <th>Categoría</th>
                <th>Servicios</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {cargando && <FilasCargando columnas={4} filas={4} />}
              {!cargando && items.length === 0 && (
                <FilaVacia columnas={4} icono="lista" titulo="Sin categorías" texto={puedeEscribir ? 'Crea la primera con el formulario.' : 'Aún no se ha creado ninguna.'} />
              )}
              {!cargando &&
                items.map((c) => (
                  <tr key={c.id} className={editandoId === c.id ? 'is-editando' : ''}>
                    <td className="tabla__num">{c.orden ?? 0}</td>
                    <td>
                      <div className="celda-entidad">
                        <IconoServicio icono={c.icono} nombre={c.nombre} tono={tonoCategoria(c.id)} size="sm" />
                        <div>
                          <strong>{c.nombre}</strong>
                          <small>{c.nombre_en || 'Sin nombre en inglés'}</small>
                        </div>
                      </div>
                    </td>
                    <td>
                      {c.servicios_count > 0 ? (
                        <Link to={`/panel/servicios?categoria=${c.id}`} className="enlace-conteo">
                          {c.servicios_count} {c.servicios_count === 1 ? 'servicio' : 'servicios'}
                        </Link>
                      ) : (
                        <span className="form-nota">Vacía</span>
                      )}
                    </td>
                    <td className="tabla__acciones">
                      {can('categoria_servicio_update') && (
                        <button className="btn btn--sm btn--ghost" onClick={() => editar(c)}>
                          <Icono nombre="editar" size={14} /> Editar
                        </button>
                      )}
                      {can('categoria_servicio_destroy') && (
                        <button
                          className="btn btn--sm btn--danger btn--icono-sm"
                          onClick={() => eliminar(c)}
                          title={c.servicios_count > 0 ? 'Tiene servicios: muévelos antes de eliminarla' : `Eliminar ${c.nombre}`}
                          aria-label={`Eliminar ${c.nombre}`}
                        >
                          <Icono nombre="eliminar" size={14} />
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
            </tbody>
          </table>
        </div>

        {puedeEscribir && (
          <form className="form panel-lateral" onSubmit={onSubmit}>
            <div className="panel-lateral__cabecera">
              <IconoServicio icono={form.icono} nombre={form.nombre || '?'} tono={tonoForm} />
              <div>
                <h2>{editandoId ? 'Editar categoría' : 'Nueva categoría'}</h2>
                <small className="form-nota">{editandoId ? form.nombre : 'Aparecerá como un grupo del catálogo.'}</small>
              </div>
            </div>
            <label>
              Nombre
              <input value={form.nombre} onChange={(e) => set('nombre', e.target.value)} placeholder="Ej.: Bienestar" />
              {err('nombre')}
            </label>
            <label>
              Nombre en inglés
              <input value={form.nombre_en} onChange={(e) => set('nombre_en', e.target.value)} placeholder="Ej.: Wellness" />
              {err('nombre_en')}
            </label>
            <label>
              Orden en el catálogo
              <input type="number" min="0" value={form.orden} onChange={(e) => set('orden', e.target.value)} />
              {err('orden')}
            </label>
            <div>
              <span className="campo-etiqueta">Ícono</span>
              <div className="selector-icono" role="radiogroup" aria-label="Ícono">
                {ICONOS_SUGERIDOS.map((nombre) => (
                  <button
                    key={nombre}
                    type="button"
                    role="radio"
                    aria-checked={form.icono === nombre}
                    className={form.icono === nombre ? 'is-elegido' : ''}
                    onClick={() => set('icono', form.icono === nombre ? '' : nombre)}
                  >
                    <IconoServicio icono={nombre} tono={tonoForm} size="sm" />
                  </button>
                ))}
              </div>
              {err('icono')}
            </div>
            <div className="form-acciones">
              {editandoId && (
                <button type="button" className="btn btn--ghost" onClick={limpiar}>Cancelar</button>
              )}
              <button className="btn" disabled={guardando}>
                {guardando ? 'Guardando…' : editandoId ? 'Guardar cambios' : 'Crear categoría'}
              </button>
            </div>
          </form>
        )}
      </div>

    </div>
  )
}
