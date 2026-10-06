import { useEffect, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'
import { rutaImagen } from '../../../utils'

export default function TipoForm() {
  const { id } = useParams()
  const editando = Boolean(id)
  const navigate = useNavigate()
  const fileRef = useRef(null)

  const [form, setForm] = useState({ nombre: '', capacidad: 1, precio_base: '', descripcion: '' })
  const [archivos, setArchivos] = useState([])
  const [imagenes, setImagenes] = useState([])
  const [errores, setErrores] = useState({})
  const [error, setError] = useState(null)
  const [cargando, setCargando] = useState(editando)
  const [guardando, setGuardando] = useState(false)

  useEffect(() => {
    if (!editando) return
    api
      .get(`/admin/tipos-habitacion/${id}`)
      .then((res) => {
        const t = res.data.data
        setForm({
          nombre: t.nombre,
          capacidad: t.capacidad,
          precio_base: t.precio_base,
          descripcion: t.descripcion || '',
        })
        setImagenes(t.imagenes || [])
      })
      .catch(() => setError('No se pudo cargar el registro.'))
      .finally(() => setCargando(false))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  function set(campo, valor) {
    setForm((f) => ({ ...f, [campo]: valor }))
  }

  async function onSubmit(e) {
    e.preventDefault()
    setGuardando(true)
    setErrores({})
    setError(null)
    try {
      if (editando) {
        await api.put(`/admin/tipos-habitacion/${id}`, form)
      } else {
        const fd = new FormData()
        Object.entries(form).forEach(([k, v]) => fd.append(k, v ?? ''))
        archivos.forEach((a) => fd.append('imagenes[]', a))
        await api.post('/admin/tipos-habitacion', fd)
      }
      navigate('/panel/tipos-habitacion')
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

  // --- Gestion de imagenes (solo en edicion) ---
  function recargarImagenes() {
    api.get(`/admin/tipos-habitacion/${id}`).then((res) => setImagenes(res.data.data.imagenes || []))
  }

  async function subirImagenes() {
    if (archivos.length === 0) return
    const fd = new FormData()
    archivos.forEach((a) => fd.append('imagenes[]', a))
    try {
      await api.post(`/admin/tipos-habitacion/${id}/imagenes`, fd)
      setArchivos([])
      if (fileRef.current) fileRef.current.value = ''
      recargarImagenes()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudieron subir las imágenes.')
    }
  }

  async function eliminarImagen(imgId) {
    if (!confirm('¿Eliminar esta imagen?')) return
    await api.delete(`/admin/tipos-habitacion/${id}/imagenes/${imgId}`)
    recargarImagenes()
  }

  async function hacerPrincipal(imgId) {
    await api.put(`/admin/tipos-habitacion/${id}/imagenes/${imgId}/principal`)
    recargarImagenes()
  }

  if (cargando) return <p className="estado">Cargando…</p>

  return (
    <div className="form-page">
      <h1 className="page-title">{editando ? 'Editar tipo de habitación' : 'Nuevo tipo de habitación'}</h1>

      {error && <div className="alerta alerta--error">{error}</div>}

      <form className="form" onSubmit={onSubmit}>
        <label>
          Nombre
          <input value={form.nombre} onChange={(e) => set('nombre', e.target.value)} />
          {errores.nombre && <small className="campo-error">{errores.nombre[0]}</small>}
        </label>

        <div className="form-row">
          <label>
            Capacidad
            <input
              type="number"
              min="1"
              value={form.capacidad}
              onChange={(e) => set('capacidad', e.target.value)}
            />
            {errores.capacidad && <small className="campo-error">{errores.capacidad[0]}</small>}
          </label>
          <label>
            Precio base (USD)
            <input
              type="number"
              step="0.01"
              min="0"
              value={form.precio_base}
              onChange={(e) => set('precio_base', e.target.value)}
            />
            {errores.precio_base && <small className="campo-error">{errores.precio_base[0]}</small>}
          </label>
        </div>

        <label>
          Descripción
          <textarea
            rows="3"
            value={form.descripcion}
            onChange={(e) => set('descripcion', e.target.value)}
          />
        </label>

        {!editando && (
          <label>
            Fotos (opcional)
            <input
              type="file"
              multiple
              accept="image/*"
              onChange={(e) => setArchivos(Array.from(e.target.files))}
            />
          </label>
        )}

        <div className="form-acciones">
          <button type="button" className="btn btn--ghost" onClick={() => navigate('/panel/tipos-habitacion')}>
            Cancelar
          </button>
          <button className="btn" disabled={guardando}>
            {guardando ? 'Guardando…' : 'Guardar'}
          </button>
        </div>
      </form>

      {editando && (
        <div className="galeria-admin">
          <h2 className="seccion-title">Fotos</h2>
          <div className="galeria-grid">
            {imagenes.map((img) => (
              <div key={img.id} className={`galeria-item ${img.principal ? 'is-principal' : ''}`}>
                <img src={rutaImagen(img.url)} alt="" />
                {img.principal && <span className="badge-principal">Principal</span>}
                <div className="galeria-item__acciones">
                  {!img.principal && (
                    <button className="btn btn--sm btn--ghost" onClick={() => hacerPrincipal(img.id)}>
                      Hacer principal
                    </button>
                  )}
                  <button className="btn btn--sm btn--danger" onClick={() => eliminarImagen(img.id)}>
                    Eliminar
                  </button>
                </div>
              </div>
            ))}
            {imagenes.length === 0 && <p className="estado">Sin fotos aún.</p>}
          </div>

          <div className="subir-fotos">
            <input
              ref={fileRef}
              type="file"
              multiple
              accept="image/*"
              onChange={(e) => setArchivos(Array.from(e.target.files))}
            />
            <button type="button" className="btn btn--ghost" onClick={subirImagenes} disabled={archivos.length === 0}>
              Subir fotos
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
