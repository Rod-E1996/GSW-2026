import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'
import Icono from '../../../components/Icono'
import { FormSeccion } from '../../../components/ui'
import { formatoUSD, rutaImagen } from '../../../utils'
import '../../../styles/admin-hotel.css'

// Miniaturas de los archivos elegidos antes de subirlos
function usePrevias(archivos) {
  const previas = useMemo(() => archivos.map((a) => ({ nombre: a.name, url: URL.createObjectURL(a) })), [archivos])
  useEffect(() => () => previas.forEach((p) => URL.revokeObjectURL(p.url)), [previas])
  return previas
}

export default function TipoForm() {
  const { id } = useParams()
  const editando = Boolean(id)
  const navigate = useNavigate()
  const fileRef = useRef(null)

  const [form, setForm] = useState({
    nombre: '',
    nombre_en: '',
    capacidad: 1,
    precio_base: '',
    descripcion: '',
    descripcion_en: '',
    orden: 0,
  })
  const [archivos, setArchivos] = useState([])
  const [imagenes, setImagenes] = useState([])
  const [slug, setSlug] = useState('')
  const [errores, setErrores] = useState({})
  const [error, setError] = useState(null)
  const [aviso, setAviso] = useState(null)
  const [cargando, setCargando] = useState(editando)
  const [guardando, setGuardando] = useState(false)
  const [subiendo, setSubiendo] = useState(false)
  const previas = usePrevias(archivos)

  useEffect(() => {
    if (!editando) return
    api
      .get(`/admin/tipos-habitacion/${id}`)
      .then((res) => {
        const t = res.data.data
        setForm({
          nombre: t.nombre,
          nombre_en: t.nombre_en || '',
          capacidad: t.capacidad,
          precio_base: t.precio_base,
          descripcion: t.descripcion || '',
          descripcion_en: t.descripcion_en || '',
          orden: t.orden ?? 0,
        })
        setSlug(t.slug)
        setImagenes(t.imagenes || [])
      })
      .catch(() => setError('No se pudo cargar el registro.'))
      .finally(() => setCargando(false))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  function set(campo, valor) {
    setForm((f) => ({ ...f, [campo]: valor }))
  }

  function elegirArchivos(e) {
    setArchivos(Array.from(e.target.files))
  }

  function quitarArchivos() {
    setArchivos([])
    if (fileRef.current) fileRef.current.value = ''
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
        setError('Revisa los campos marcados.')
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
    setSubiendo(true)
    setError(null)
    try {
      await api.post(`/admin/tipos-habitacion/${id}/imagenes`, fd)
      setAviso(archivos.length === 1 ? 'Foto subida.' : `${archivos.length} fotos subidas.`)
      quitarArchivos()
      recargarImagenes()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudieron subir las imágenes.')
    } finally {
      setSubiendo(false)
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

  const campoError = (campo) => errores[campo] && <small className="campo-error">{errores[campo][0]}</small>

  if (cargando) return <p className="estado">Cargando…</p>

  const selectorFotos = (
    <div className="fotos-subir">
      <label className="fotos-subir__zona">
        <Icono nombre="foto" size={22} />
        <span><strong>Elegir fotos</strong> JPG, PNG o WEBP. Puedes elegir varias a la vez.</span>
        <input ref={fileRef} type="file" multiple accept="image/*" onChange={elegirArchivos} />
      </label>
      {previas.length > 0 && (
        <>
          <div className="fotos-previas">
            {previas.map((p) => (
              <figure key={p.url}>
                <img src={p.url} alt="" />
                <figcaption title={p.nombre}>{p.nombre}</figcaption>
              </figure>
            ))}
          </div>
          <div className="fotos-subir__acciones">
            <span className="form-nota">{previas.length} {previas.length === 1 ? 'foto elegida' : 'fotos elegidas'}</span>
            <button type="button" className="btn btn--sm btn--ghost" onClick={quitarArchivos}>Quitar</button>
            {editando && (
              <button type="button" className="btn btn--sm" onClick={subirImagenes} disabled={subiendo}>
                {subiendo ? 'Subiendo…' : 'Subir fotos'}
              </button>
            )}
          </div>
        </>
      )}
    </div>
  )

  return (
    <div className="form-page">
      <Link to="/panel/tipos-habitacion" className="reserva__volver">← Tipos de habitación</Link>
      <div className="page-head">
        <div>
          <h1 className="page-title">{editando ? `Editar ${form.nombre || 'tipo de habitación'}` : 'Nuevo tipo de habitación'}</h1>
          <p className="page-sub">
            {editando
              ? <>Se muestra en el portal en <strong>/{slug}</strong>.</>
              : 'Define cómo se presenta este tipo de habitación en el portal de reservas.'}
          </p>
        </div>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

      <form className="form" onSubmit={onSubmit}>
        <FormSeccion titulo="Nombre" descripcion="Cómo lo verán los huéspedes. El nombre en inglés se usa cuando el portal está en ese idioma.">
          <div className="form-row">
            <label>
              Nombre
              <input value={form.nombre} onChange={(e) => set('nombre', e.target.value)} placeholder="Ej.: Doble" autoFocus={!editando} />
              {campoError('nombre')}
            </label>
            <label>
              Nombre en inglés
              <input value={form.nombre_en} onChange={(e) => set('nombre_en', e.target.value)} placeholder="Ej.: Double" />
              {campoError('nombre_en')}
            </label>
          </div>
        </FormSeccion>

        <FormSeccion titulo="Capacidad y precio" descripcion="El precio base se cobra por noche fuera de temporada; las temporadas y tarifas lo ajustan.">
          <div className="form-row form-row--3">
            <label>
              Capacidad (personas)
              <input type="number" min="1" value={form.capacidad} onChange={(e) => set('capacidad', e.target.value)} />
              {campoError('capacidad')}
            </label>
            <label>
              Precio base por noche (USD)
              <input type="number" step="0.01" min="0" value={form.precio_base} onChange={(e) => set('precio_base', e.target.value)} placeholder="0.00" />
              {campoError('precio_base')}
            </label>
            <label>
              Orden en el portal
              <input type="number" min="0" value={form.orden} onChange={(e) => set('orden', e.target.value)} />
              {campoError('orden')}
            </label>
          </div>
          {Number(form.precio_base) > 0 && (
            <small className="form-nota">
              Una estadía de 3 noches cuesta {formatoUSD(Number(form.precio_base) * 3)} antes de impuestos fuera de temporada.
            </small>
          )}
        </FormSeccion>

        <FormSeccion titulo="Descripción" descripcion="Camas, baño, vista y comodidades. Aparece en la tarjeta del portal.">
          <label>
            Descripción
            <textarea rows="3" value={form.descripcion} onChange={(e) => set('descripcion', e.target.value)} />
            {campoError('descripcion')}
          </label>
          <label>
            Descripción en inglés
            <textarea rows="3" value={form.descripcion_en} onChange={(e) => set('descripcion_en', e.target.value)} />
            {campoError('descripcion_en')}
          </label>
        </FormSeccion>

        {!editando && (
          <FormSeccion titulo="Fotos" descripcion="Opcional. La primera queda como principal; después podrás cambiarla.">
            {selectorFotos}
            {campoError('imagenes')}
          </FormSeccion>
        )}

        <div className="form-acciones">
          <button type="button" className="btn btn--ghost" onClick={() => navigate('/panel/tipos-habitacion')}>
            Cancelar
          </button>
          <button className="btn" disabled={guardando}>
            {guardando ? 'Guardando…' : editando ? 'Guardar cambios' : 'Crear tipo'}
          </button>
        </div>
      </form>

      {editando && (
        <section className="tarjeta-simple galeria-tipo">
          <div className="galeria-tipo__cabecera">
            <div>
              <h3>Fotos</h3>
              <p className="form-nota">La foto principal es la que se ve en el portal y en las listas.</p>
            </div>
            <span className="panel-lista__conteo">{imagenes.length}</span>
          </div>

          {imagenes.length === 0 ? (
            <p className="vacio-mini">Este tipo todavía no tiene fotos.</p>
          ) : (
            <div className="galeria-tipo__grid">
              {imagenes.map((img) => (
                <figure key={img.id} className={`galeria-tipo__foto ${img.principal ? 'is-principal' : ''}`}>
                  <img src={rutaImagen(img.url)} alt="" />
                  {img.principal && <span className="badge-principal">Principal</span>}
                  <figcaption>
                    {!img.principal && (
                      <button type="button" className="btn btn--sm btn--ghost" onClick={() => hacerPrincipal(img.id)}>
                        Hacer principal
                      </button>
                    )}
                    <button type="button" className="btn btn--sm btn--danger" onClick={() => eliminarImagen(img.id)} title="Eliminar foto" aria-label="Eliminar foto">
                      <Icono nombre="eliminar" size={14} />
                    </button>
                  </figcaption>
                </figure>
              ))}
            </div>
          )}

          {selectorFotos}
        </section>
      )}
    </div>
  )
}
