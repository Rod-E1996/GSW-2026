import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'
import Icono from '../../../components/Icono'
import { FormSeccion } from '../../../components/ui'
import { fechaCorta, formatoUSD } from '../../../utils'
import '../../../styles/admin-catalogo.css'

// Colores sugeridos para distinguir temporadas en el calendario
const COLORES = ['#008A9C', '#2F80ED', '#27AE60', '#9B51E0', '#F2994A', '#EB5757', '#D98A1C', '#56636A']

function noches(desde, hasta) {
  if (!desde || !hasta || hasta < desde) return null
  const a = Date.UTC(...desde.split('-').map((n, i) => Number(n) - (i === 1 ? 1 : 0)))
  const b = Date.UTC(...hasta.split('-').map((n, i) => Number(n) - (i === 1 ? 1 : 0)))
  return Math.round((b - a) / 86400000) + 1
}

export default function TemporadaForm() {
  const { id } = useParams()
  const editando = Boolean(id)
  const navigate = useNavigate()

  const [form, setForm] = useState({
    nombre: '',
    nombre_en: '',
    fecha_inicio: '',
    fecha_fin: '',
    multiplicador: '1.00',
    prioridad: 0,
    color: '#008A9C',
  })
  const [tipos, setTipos] = useState([])
  const [errores, setErrores] = useState({})
  const [error, setError] = useState(null)
  const [cargando, setCargando] = useState(editando)
  const [guardando, setGuardando] = useState(false)

  useEffect(() => {
    // Tipos de habitacion para mostrar como queda el precio (si no hay permiso, simplemente no se muestra)
    api.get('/admin/tarifas').then((res) => setTipos(res.data.tipos || [])).catch(() => {})

    if (!editando) return
    api
      .get(`/admin/temporadas/${id}`)
      .then((res) => {
        const t = res.data.data
        setForm({
          nombre: t.nombre,
          nombre_en: t.nombre_en || '',
          fecha_inicio: t.fecha_inicio,
          fecha_fin: t.fecha_fin,
          multiplicador: t.multiplicador,
          prioridad: t.prioridad ?? 0,
          color: t.color || '#008A9C',
        })
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
        await api.put(`/admin/temporadas/${id}`, form)
      } else {
        await api.post('/admin/temporadas', form)
      }
      navigate('/panel/tarifas')
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

  if (cargando) {
    return (
      <div className="form-page">
        <span className="esqueleto" style={{ width: 240, height: '1.6rem', marginBottom: '1.5rem' }} />
        <div className="form"><span className="esqueleto" /><span className="esqueleto" /><span className="esqueleto" /></div>
      </div>
    )
  }

  const err = (campo) => errores[campo] && <small className="campo-error">{errores[campo][0]}</small>
  const duracion = noches(form.fecha_inicio, form.fecha_fin)
  const multiplicador = Number(form.multiplicador) || 0
  const pct = Math.round((multiplicador - 1) * 100)

  return (
    <div className="form-page form-page--con-previa">
      <Link to="/panel/tarifas" className="volver"><Icono nombre="volver" size={16} /> Temporadas y tarifas</Link>
      <div className="page-head">
        <div>
          <h1 className="page-title">{editando ? form.nombre || 'Editar temporada' : 'Nueva temporada'}</h1>
          <p className="page-sub">Durante estas fechas el precio base de cada habitación se multiplica por el ajuste que definas.</p>
        </div>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}

      <div className="con-previa">
        <form className="form" onSubmit={onSubmit} noValidate>
          <FormSeccion titulo="Nombre" descripcion="Cómo se identifica en el panel y en el portal en inglés.">
            <div className="form-row">
              <label>
                Nombre
                <input value={form.nombre} onChange={(e) => set('nombre', e.target.value)} placeholder="Ej.: Semana Santa 2027" />
                {err('nombre')}
              </label>
              <label>
                Nombre en inglés
                <input value={form.nombre_en} onChange={(e) => set('nombre_en', e.target.value)} placeholder="Ej.: Holy Week 2027" />
                {err('nombre_en')}
              </label>
            </div>
          </FormSeccion>

          <FormSeccion titulo="Fechas" descripcion="Ambos días se incluyen. Cada noche dentro del rango usa el precio de esta temporada.">
            <div className="form-row">
              <label>
                Primer día
                <input type="date" value={form.fecha_inicio} onChange={(e) => set('fecha_inicio', e.target.value)} />
                {err('fecha_inicio')}
              </label>
              <label>
                Último día
                <input type="date" value={form.fecha_fin} min={form.fecha_inicio || undefined} onChange={(e) => set('fecha_fin', e.target.value)} />
                {err('fecha_fin')}
              </label>
            </div>
            {duracion && (
              <p className="nota-calculo">
                <Icono nombre="calendario" size={16} />
                <span>{duracion} {duracion === 1 ? 'día' : 'días'}, del {fechaCorta(form.fecha_inicio)} al {fechaCorta(form.fecha_fin)}.</span>
              </p>
            )}
          </FormSeccion>

          <FormSeccion titulo="Ajuste de precio" descripcion="1.00 deja el precio igual; 1.30 lo sube 30 %; 0.85 lo baja 15 %. En Tarifas puedes fijar un precio exacto por tipo.">
            <div className="form-row">
              <label>
                Multiplicador
                <span className="campo-prefijo">
                  <span>×</span>
                  <input type="number" step="0.01" min="0.1" max="9.99" value={form.multiplicador} onChange={(e) => set('multiplicador', e.target.value)} />
                </span>
                {err('multiplicador')}
              </label>
              <label>
                Prioridad
                <input type="number" min="0" value={form.prioridad} onChange={(e) => set('prioridad', e.target.value)} />
                <small className="form-nota">Si se cruza con otra temporada, gana la de número mayor.</small>
                {err('prioridad')}
              </label>
            </div>
          </FormSeccion>

          <FormSeccion titulo="Color" descripcion="Para reconocerla de un vistazo en el calendario de temporadas.">
            <div className="selector-color" role="radiogroup" aria-label="Color">
              {COLORES.map((c) => (
                <button
                  key={c}
                  type="button"
                  role="radio"
                  aria-checked={form.color.toUpperCase() === c}
                  aria-label={c}
                  className={form.color.toUpperCase() === c ? 'is-elegido' : ''}
                  style={{ background: c }}
                  onClick={() => set('color', c)}
                />
              ))}
              <label className="selector-color__otro" title="Otro color">
                <input type="color" value={form.color} onChange={(e) => set('color', e.target.value.toUpperCase())} aria-label="Otro color" />
                Otro
              </label>
            </div>
            {err('color')}
          </FormSeccion>

          <div className="form-acciones">
            <button type="button" className="btn btn--ghost" onClick={() => navigate('/panel/tarifas')}>Cancelar</button>
            <button className="btn" disabled={guardando}>{guardando ? 'Guardando…' : editando ? 'Guardar cambios' : 'Crear temporada'}</button>
          </div>
        </form>

        <aside className="previa" aria-label="Resumen">
          <span className="previa__titulo">Resumen</span>
          <div className="tarjeta-simple previa-temporada">
            <div className="previa-temporada__cabecera" style={{ '--color': form.color }}>
              <strong>{form.nombre || 'Nueva temporada'}</strong>
              <span>{form.fecha_inicio && form.fecha_fin ? `${fechaCorta(form.fecha_inicio)} → ${fechaCorta(form.fecha_fin)}` : 'Elige las fechas'}</span>
            </div>
            <div className={`previa-temporada__ajuste ${pct > 0 ? 'ajuste--sube' : pct < 0 ? 'ajuste--baja' : ''}`}>
              ×{multiplicador.toFixed(2)} <small>{pct > 0 ? `+${pct}` : pct} % sobre la base</small>
            </div>
            {tipos.length > 0 && (
              <>
                <span className="previa__subtitulo">Precio por noche resultante</span>
                <ul className="previa-temporada__precios">
                  {tipos.map((t) => (
                    <li key={t.id}>
                      <span>{t.nombre}</span>
                      <span className="form-nota">{formatoUSD(t.precio_base)}</span>
                      <Icono nombre="flecha" size={14} />
                      <strong>{formatoUSD(t.precio_base * multiplicador)}</strong>
                    </li>
                  ))}
                </ul>
                <small className="form-nota">Salvo los tipos con precio fijo en Tarifas.</small>
              </>
            )}
          </div>
        </aside>
      </div>
    </div>
  )
}
