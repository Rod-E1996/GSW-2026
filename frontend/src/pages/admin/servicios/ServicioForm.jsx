import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'
import Icono from '../../../components/Icono'
import { FormSeccion } from '../../../components/ui'
import { formatoUSD, rutaImagen } from '../../../utils'
import { ICONOS_SUGERIDOS, IconoServicio, tonoCategoria } from './iconosServicio'
import '../../../styles/admin-catalogo.css'

// Como se calcula cada forma de cobro, con un ejemplo de 2 personas y 3 noches
const EXPLICACION = {
  por_persona_noche: { texto: 'Se multiplica por personas y por noches.', cantidad: 6, ejemplo: '2 personas × 3 noches' },
  por_persona: { texto: 'Se cobra una vez por cada persona de la reserva.', cantidad: 2, ejemplo: '2 personas' },
  por_noche: { texto: 'Se cobra una vez por cada noche, sin importar las personas.', cantidad: 3, ejemplo: '3 noches' },
  fijo: { texto: 'Un solo cargo; el huésped puede pedir varias unidades.', cantidad: 1, ejemplo: '1 unidad' },
}

const NOMBRES_ICONO = {
  'bi-cup-hot': 'Comida',
  'bi-basket': 'Lavandería',
  'bi-car-front': 'Transporte',
  'bi-people': 'Eventos',
  'bi-water': 'Playa',
  'bi-geo-alt': 'Tour',
  'bi-flower1': 'Bienestar',
  'bi-heart': 'Especial',
  'bi-p-square': 'Parqueo',
  'bi-clock-history': 'Horario',
  'bi-bell': 'Servicio',
}

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

  // Vista previa local de la imagen elegida, antes de subirla
  const previaArchivo = useMemo(() => (archivo ? URL.createObjectURL(archivo) : null), [archivo])
  useEffect(() => () => previaArchivo && URL.revokeObjectURL(previaArchivo), [previaArchivo])

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
        setError('Revisa los campos marcados.')
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

  function descartarArchivo() {
    setArchivo(null)
    if (fileRef.current) fileRef.current.value = ''
  }

  if (cargando) {
    return (
      <div className="form-page">
        <span className="esqueleto" style={{ width: 240, height: '1.6rem', marginBottom: '1.5rem' }} />
        <div className="form"><span className="esqueleto" /><span className="esqueleto" /><span className="esqueleto" /></div>
      </div>
    )
  }

  const err = (campo) => errores[campo] && <small className="campo-error">{errores[campo][0]}</small>
  const categoria = categorias.find((c) => String(c.id) === String(form.categoria_servicio_id))
  const tono = tonoCategoria(form.categoria_servicio_id)
  const explicacion = EXPLICACION[form.forma_cobro]
  const precio = Number(form.precio) || 0
  const imagenPrevia = previaArchivo || rutaImagen(imagenUrl)
  const formaNombre = formasCobro.find((f) => f.valor === form.forma_cobro)?.nombre

  return (
    <div className="form-page form-page--con-previa">
      <Link to="/panel/servicios" className="volver"><Icono nombre="volver" size={16} /> Servicios</Link>
      <div className="page-head">
        <div>
          <h1 className="page-title">{editando ? form.nombre || 'Editar servicio' : 'Nuevo servicio'}</h1>
          <p className="page-sub">{editando ? 'Los cambios se aplican a las reservas nuevas; las ya hechas conservan su precio.' : 'Aparecerá en el portal y en recepción en cuanto lo guardes.'}</p>
        </div>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}

      <div className="con-previa">
        <form className="form" onSubmit={onSubmit} noValidate>
          <FormSeccion titulo="Datos del servicio" descripcion="Cómo se llama y dónde aparece en el catálogo.">
            <label>
              Categoría
              <select value={form.categoria_servicio_id} onChange={(e) => set('categoria_servicio_id', e.target.value)}>
                <option value="">Selecciona una categoría</option>
                {categorias.map((c) => (
                  <option key={c.id} value={c.id}>{c.nombre}</option>
                ))}
              </select>
              {err('categoria_servicio_id')}
            </label>
            <div className="form-row">
              <label>
                Nombre
                <input value={form.nombre} onChange={(e) => set('nombre', e.target.value)} placeholder="Ej.: Desayuno buffet" />
                {err('nombre')}
              </label>
              <label>
                Nombre en inglés
                <input value={form.nombre_en} onChange={(e) => set('nombre_en', e.target.value)} placeholder="Ej.: Breakfast buffet" />
                {err('nombre_en')}
              </label>
            </div>
          </FormSeccion>

          <FormSeccion titulo="Precio" descripcion="El total se calcula solo en la reserva según la forma de cobro.">
            <div className="form-row">
              <label>
                Precio (USD)
                <span className="campo-prefijo">
                  <span>$</span>
                  <input type="number" step="0.01" min="0" value={form.precio} onChange={(e) => set('precio', e.target.value)} placeholder="0.00" />
                </span>
                {err('precio')}
              </label>
              <label>
                Forma de cobro
                <select value={form.forma_cobro} onChange={(e) => set('forma_cobro', e.target.value)}>
                  {formasCobro.map((f) => (
                    <option key={f.valor} value={f.valor}>{f.nombre}</option>
                  ))}
                </select>
                {err('forma_cobro')}
              </label>
            </div>
            {explicacion && (
              <div className="nota-calculo">
                <Icono nombre="info" size={16} />
                <span>
                  {explicacion.texto} Ejemplo con {explicacion.ejemplo}:{' '}
                  <strong>{formatoUSD(precio)} × {explicacion.cantidad} = {formatoUSD(precio * explicacion.cantidad)}</strong>
                </span>
              </div>
            )}
          </FormSeccion>

          <FormSeccion titulo="Descripción" descripcion="Lo que lee el huésped al elegirlo. Breve y concreta.">
            <label>
              En español
              <textarea rows="3" value={form.descripcion} onChange={(e) => set('descripcion', e.target.value)} />
              {err('descripcion')}
            </label>
            <label>
              En inglés
              <textarea rows="3" value={form.descripcion_en} onChange={(e) => set('descripcion_en', e.target.value)} />
              {err('descripcion_en')}
            </label>
          </FormSeccion>

          <FormSeccion titulo="Al reservar" descripcion="Algunos servicios necesitan que el huésped elija cuándo.">
            <label className="switch-row switch-row--check">
              <span>
                <strong>Pedir fecha y hora</strong>
                <span className="switch-row__hint">Para tours, masajes o cenas: el huésped indica cuándo los quiere, dentro de su estadía.</span>
              </span>
              <input type="checkbox" checked={form.requiere_fecha} onChange={(e) => set('requiere_fecha', e.target.checked)} />
            </label>
          </FormSeccion>

          <FormSeccion titulo="Imagen e ícono" descripcion="La imagen se muestra en el portal. Sin imagen se usa el ícono.">
            <div className="subida-imagen">
              <div className={`subida-imagen__previa servicio-card__media--${tono}`}>
                {imagenPrevia ? <img src={imagenPrevia} alt="" /> : <IconoServicio icono={form.icono} nombre={form.nombre} tono={tono} size="lg" />}
              </div>
              <div className="subida-imagen__acciones">
                <label className="btn btn--ghost btn--sm subida-imagen__elegir">
                  <Icono nombre="foto" size={15} /> {imagenUrl || archivo ? 'Cambiar imagen' : 'Elegir imagen'}
                  <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => setArchivo(e.target.files[0] || null)} />
                </label>
                {archivo && (
                  <button type="button" className="btn btn--ghost btn--sm" onClick={descartarArchivo}>Descartar {archivo.name}</button>
                )}
                {!archivo && imagenUrl && (
                  <button type="button" className="btn btn--sm btn--danger" onClick={quitarImagen}>Quitar imagen</button>
                )}
                <small className="form-nota">JPG, PNG o WEBP.{archivo ? ' Se subirá al guardar.' : ''}</small>
                {err('imagen')}
              </div>
            </div>

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
                    title={NOMBRES_ICONO[nombre] || nombre}
                  >
                    <IconoServicio icono={nombre} tono={tono} size="sm" />
                  </button>
                ))}
              </div>
              {err('icono')}
            </div>
          </FormSeccion>

          <div className="form-acciones">
            <button type="button" className="btn btn--ghost" onClick={() => navigate('/panel/servicios')}>Cancelar</button>
            <button className="btn" disabled={guardando}>{guardando ? 'Guardando…' : editando ? 'Guardar cambios' : 'Crear servicio'}</button>
          </div>
        </form>

        <aside className="previa" aria-label="Vista previa">
          <span className="previa__titulo">Así se verá en el catálogo</span>
          <article className="servicio-card">
            <div className={`servicio-card__media servicio-card__media--${tono}`}>
              {imagenPrevia ? <img src={imagenPrevia} alt="" /> : <IconoServicio icono={form.icono} nombre={form.nombre} tono={tono} size="lg" />}
              {form.requiere_fecha && <span className="servicio-card__agenda"><Icono nombre="reloj" size={13} /> Con fecha y hora</span>}
            </div>
            <div className="servicio-card__cuerpo">
              <span className="servicio-card__categoria">{categoria?.nombre || 'Sin categoría'}</span>
              <h3>{form.nombre || 'Nombre del servicio'}</h3>
              <p className="servicio-card__desc">{form.descripcion || 'Aquí aparecerá la descripción.'}</p>
            </div>
            <div className="servicio-card__pie">
              <div className="servicio-card__precio">
                <strong>{formatoUSD(precio)}</strong>
                <span>{formaNombre?.toLowerCase()}</span>
              </div>
            </div>
          </article>
        </aside>
      </div>
    </div>
  )
}
