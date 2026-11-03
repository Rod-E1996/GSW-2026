import { useEffect, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'
import { rutaImagen } from '../../../utils'

export default function ServicioForm() {
  const { id } = useParams()
  const editando = Boolean(id)
  const navigate = useNavigate()
  const fileRef = useRef(null)

  const [form, setForm] = useState({
    categoria_servicio_id: '',
    nombre: '',
    nombre_en: '',
    descripcion: '',
    descripcion_en: '',
    icono: '',
    precio: '',
    forma_cobro: 'fijo',
    requiere_fecha: false,
  })
  const [categorias, setCategorias] = useState([])
  const [formasCobro, setFormasCobro] = useState([])
  const [imagenUrl, setImagenUrl] = useState(null)
  const [archivo, setArchivo] = useState(null)
  const [errores, setErrores] = useState({})
  const [error, setError] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)

  useEffect(() => {
    const pedidos = [api.get('/admin/categorias-servicio'), api.get('/admin/servicios')]
    if (editando) pedidos.push(api.get(`/admin/servicios/${id}`))

    Promise.all(pedidos)
      .then(([cat, lista, servicio]) => {
        setCategorias(cat.data.data)
        setFormasCobro(lista.data.formas_cobro)
        if (servicio) {
          const s = servicio.data.data
          setForm({
            categoria_servicio_id: s.categoria_servicio_id,
            nombre: s.nombre,
            nombre_en: s.nombre_en || '',
            descripcion: s.descripcion || '',
            descripcion_en: s.descripcion_en || '',
            icono: s.icono || '',
            precio: s.precio,
            forma_cobro: s.forma_cobro,
            requiere_fecha: s.requiere_fecha,
          })
          setImagenUrl(s.imagen_url)
        }
      })
      .catch(() => setError('No se pudo cargar el formulario.'))
      .finally(() => setCargando(false))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  function set(campo, valor) {
    setForm((f) => ({ ...f, [campo]: valor }))
  }

  async function subirImagen(servicioId) {
    const fd = new FormData()
    fd.append('imagen', archivo)
    await api.post(`/admin/servicios/${servicioId}/imagen`, fd)
  }

  async function onSubmit(e) {
    e.preventDefault()
    setGuardando(true)
    setErrores({})
    setError(null)

    let servicioId = id
    try {
      if (editando) {
        await api.put(`/admin/servicios/${id}`, form)
      } else {
        servicioId = (await api.post('/admin/servicios', form)).data.data.id
      }
    } catch (err) {
      if (err.response?.status === 422) {
        setErrores(err.response.data.errors || {})
      } else {
        setError(err.response?.data?.message || 'No se pudo guardar.')
      }
      setGuardando(false)
      return
    }

    // El servicio ya quedo guardado: si la imagen falla se continua en edicion para reintentarla
    if (archivo) {
      try {
        await subirImagen(servicioId)
      } catch (err) {
        setError(err.response?.data?.errors?.imagen?.[0] || 'Se guardó el servicio, pero no se pudo subir la imagen.')
        setGuardando(false)
        if (!editando) navigate(`/panel/servicios/${servicioId}/editar`, { replace: true })
        return
      }
    }

    navigate('/panel/servicios')
  }

  async function quitarImagen() {
    if (!confirm('¿Quitar la imagen de este servicio?')) return
    try {
      await api.delete(`/admin/servicios/${id}/imagen`)
      setImagenUrl(null)
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo quitar la imagen.')
    }
  }

  if (cargando) return <p className="estado">Cargando…</p>

  return (
    <div className="form-page">
      <h1 className="page-title">{editando ? 'Editar servicio' : 'Nuevo servicio'}</h1>

      {error && <div className="alerta alerta--error">{error}</div>}

      <form className="form" onSubmit={onSubmit}>
        <label>
          Categoría
          <select value={form.categoria_servicio_id} onChange={(e) => set('categoria_servicio_id', e.target.value)}>
            <option value="">Selecciona una categoría</option>
            {categorias.map((c) => (
              <option key={c.id} value={c.id}>{c.nombre}</option>
            ))}
          </select>
          {errores.categoria_servicio_id && <small className="campo-error">{errores.categoria_servicio_id[0]}</small>}
        </label>

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
            Precio (USD)
            <input type="number" step="0.01" min="0" value={form.precio} onChange={(e) => set('precio', e.target.value)} />
            {errores.precio && <small className="campo-error">{errores.precio[0]}</small>}
          </label>
          <label>
            Forma de cobro
            <select value={form.forma_cobro} onChange={(e) => set('forma_cobro', e.target.value)}>
              {formasCobro.map((f) => (
                <option key={f.valor} value={f.valor}>{f.nombre}</option>
              ))}
            </select>
            {errores.forma_cobro && <small className="campo-error">{errores.forma_cobro[0]}</small>}
          </label>
        </div>

        <label>
          Descripción
          <textarea rows="3" value={form.descripcion} onChange={(e) => set('descripcion', e.target.value)} />
        </label>
        <label>
          Descripción en inglés
          <textarea rows="3" value={form.descripcion_en} onChange={(e) => set('descripcion_en', e.target.value)} />
        </label>

        <label>
          Ícono de Bootstrap Icons (opcional)
          <input placeholder="bi-cup-hot" value={form.icono} onChange={(e) => set('icono', e.target.value)} />
          {errores.icono && <small className="campo-error">{errores.icono[0]}</small>}
        </label>

        <label className="form-check">
          <input
            type="checkbox"
            checked={form.requiere_fecha}
            onChange={(e) => set('requiere_fecha', e.target.checked)}
          />
          El huésped debe indicar fecha y hora al contratarlo
        </label>

        <div className="form-imagen">
          {imagenUrl && (
            <>
              <img className="thumb" src={rutaImagen(imagenUrl)} alt={form.nombre} />
              <button type="button" className="btn btn--sm btn--danger" onClick={quitarImagen}>
                Quitar imagen
              </button>
            </>
          )}
          <label>
            {imagenUrl ? 'Reemplazar imagen' : 'Imagen (opcional)'}
            <input
              ref={fileRef}
              type="file"
              accept="image/jpeg,image/png,image/webp"
              onChange={(e) => setArchivo(e.target.files[0] || null)}
            />
          </label>
        </div>

        <div className="form-acciones">
          <button type="button" className="btn btn--ghost" onClick={() => navigate('/panel/servicios')}>
            Cancelar
          </button>
          <button className="btn" disabled={guardando}>
            {guardando ? 'Guardando…' : 'Guardar'}
          </button>
        </div>
      </form>
    </div>
  )
}
