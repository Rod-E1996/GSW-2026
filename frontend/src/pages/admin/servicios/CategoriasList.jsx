import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'

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
      setMensaje('Categoría guardada con éxito.')
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

  return (
    <div>
      <div className="page-head">
        <h1 className="page-title">Categorías de servicio</h1>
        <Link to="/panel/servicios" className="btn btn--ghost">
          ← Servicios
        </Link>
      </div>

      {mensaje && <div className="alerta alerta--ok">{mensaje}</div>}
      {error && <div className="alerta alerta--error">{error}</div>}

      {puedeEscribir && (
        <form className="form form--seccion" onSubmit={onSubmit}>
          <h2 className="seccion-title">{editandoId ? 'Editar categoría' : 'Nueva categoría'}</h2>
          <div className="form-row">
            <label>
              Nombre
              <input value={form.nombre} onChange={(e) => set('nombre', e.target.value)} />
              {errores.nombre && <small className="campo-error">{errores.nombre[0]}</small>}
            </label>
            <label>
              Nombre en inglés
              <input value={form.nombre_en} onChange={(e) => set('nombre_en', e.target.value)} />
              {errores.nombre_en && <small className="campo-error">{errores.nombre_en[0]}</small>}
            </label>
          </div>
          <div className="form-row">
            <label>
              Ícono de Bootstrap Icons (opcional)
              <input placeholder="bi-bell" value={form.icono} onChange={(e) => set('icono', e.target.value)} />
              {errores.icono && <small className="campo-error">{errores.icono[0]}</small>}
            </label>
            <label>
              Orden
              <input type="number" min="0" value={form.orden} onChange={(e) => set('orden', e.target.value)} />
              {errores.orden && <small className="campo-error">{errores.orden[0]}</small>}
            </label>
          </div>
          <div className="form-acciones">
            {editandoId && (
              <button type="button" className="btn btn--ghost" onClick={limpiar}>
                Cancelar
              </button>
            )}
            <button className="btn" disabled={guardando}>
              {guardando ? 'Guardando…' : 'Guardar'}
            </button>
          </div>
        </form>
      )}

      <div className="tabla-wrap">
        <table className="tabla">
          <thead>
            <tr>
              <th>Orden</th>
              <th>Nombre</th>
              <th>Nombre en inglés</th>
              <th>Servicios</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {cargando && (
              <tr><td colSpan="5" className="tabla__vacio">Cargando…</td></tr>
            )}
            {!cargando && items.length === 0 && (
              <tr><td colSpan="5" className="tabla__vacio">Sin categorías.</td></tr>
            )}
            {!cargando &&
              items.map((c) => (
                <tr key={c.id}>
                  <td>{c.orden ?? 0}</td>
                  <td>{c.nombre}</td>
                  <td>{c.nombre_en || '—'}</td>
                  <td>{c.servicios_count}</td>
                  <td className="tabla__acciones">
                    {can('categoria_servicio_update') && (
                      <button className="btn btn--sm btn--ghost" onClick={() => editar(c)}>
                        Editar
                      </button>
                    )}
                    {can('categoria_servicio_destroy') && (
                      <button className="btn btn--sm btn--danger" onClick={() => eliminar(c)}>
                        Eliminar
                      </button>
                    )}
                  </td>
                </tr>
              ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
