import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'

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
  const [errores, setErrores] = useState({})
  const [error, setError] = useState(null)
  const [cargando, setCargando] = useState(editando)
  const [guardando, setGuardando] = useState(false)

  useEffect(() => {
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
      } else {
        setError(err.response?.data?.message || 'No se pudo guardar.')
      }
    } finally {
      setGuardando(false)
    }
  }

  if (cargando) return <p className="estado">Cargando…</p>

  return (
    <div className="form-page">
      <h1 className="page-title">{editando ? 'Editar temporada' : 'Nueva temporada'}</h1>

      {error && <div className="alerta alerta--error">{error}</div>}

      <form className="form" onSubmit={onSubmit}>
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
            Fecha de inicio
            <input type="date" value={form.fecha_inicio} onChange={(e) => set('fecha_inicio', e.target.value)} />
            {errores.fecha_inicio && <small className="campo-error">{errores.fecha_inicio[0]}</small>}
          </label>
          <label>
            Fecha de fin
            <input type="date" value={form.fecha_fin} onChange={(e) => set('fecha_fin', e.target.value)} />
            {errores.fecha_fin && <small className="campo-error">{errores.fecha_fin[0]}</small>}
          </label>
        </div>

        <div className="form-row">
          <label>
            Multiplicador del precio base
            <input
              type="number"
              step="0.01"
              min="0.1"
              max="9.99"
              value={form.multiplicador}
              onChange={(e) => set('multiplicador', e.target.value)}
            />
            {errores.multiplicador && <small className="campo-error">{errores.multiplicador[0]}</small>}
          </label>
          <label>
            Prioridad si se traslapa con otra
            <input type="number" min="0" value={form.prioridad} onChange={(e) => set('prioridad', e.target.value)} />
            {errores.prioridad && <small className="campo-error">{errores.prioridad[0]}</small>}
          </label>
        </div>

        <label>
          Color en el calendario
          <input className="form-color" type="color" value={form.color} onChange={(e) => set('color', e.target.value)} />
          {errores.color && <small className="campo-error">{errores.color[0]}</small>}
        </label>

        <div className="form-acciones">
          <button type="button" className="btn btn--ghost" onClick={() => navigate('/panel/tarifas')}>
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
