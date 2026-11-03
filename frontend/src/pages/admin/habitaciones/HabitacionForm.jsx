import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'

export default function HabitacionForm() {
  const { id } = useParams()
  const editando = Boolean(id)
  const navigate = useNavigate()

  const [form, setForm] = useState({
    numero: '',
    piso: 1,
    tipo_habitacion_id: '',
    estado_habitacion: 'disponible',
    descripcion: '',
  })
  const [catalogos, setCatalogos] = useState({ tipos: [], estados: [] })
  const [errores, setErrores] = useState({})
  const [error, setError] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)

  useEffect(() => {
    const pedidos = [api.get('/admin/habitaciones/catalogos')]
    if (editando) pedidos.push(api.get(`/admin/habitaciones/${id}`))

    Promise.all(pedidos)
      .then(([cat, hab]) => {
        setCatalogos(cat.data)
        if (hab) {
          const h = hab.data.data
          setForm({
            numero: h.numero,
            piso: h.piso,
            tipo_habitacion_id: h.tipo_habitacion_id,
            estado_habitacion: h.estado_habitacion,
            descripcion: h.descripcion || '',
          })
        }
      })
      .catch(() => setError('No se pudo cargar el formulario.'))
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
        await api.put(`/admin/habitaciones/${id}`, form)
      } else {
        await api.post('/admin/habitaciones', form)
      }
      navigate('/panel/habitaciones')
    } catch (err) {
      if (err.response?.status === 422 && err.response.data.errors) {
        setErrores(err.response.data.errors)
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
      <h1 className="page-title">{editando ? 'Editar habitación' : 'Nueva habitación'}</h1>

      {error && <div className="alerta alerta--error">{error}</div>}

      <form className="form" onSubmit={onSubmit}>
        <div className="form-row">
          <label>
            Número
            <input value={form.numero} maxLength="10" onChange={(e) => set('numero', e.target.value)} />
            {errores.numero && <small className="campo-error">{errores.numero[0]}</small>}
          </label>
          <label>
            Piso
            <input type="number" min="0" value={form.piso} onChange={(e) => set('piso', e.target.value)} />
            {errores.piso && <small className="campo-error">{errores.piso[0]}</small>}
          </label>
        </div>

        <div className="form-row">
          <label>
            Tipo de habitación
            <select value={form.tipo_habitacion_id} onChange={(e) => set('tipo_habitacion_id', e.target.value)}>
              <option value="">Selecciona un tipo</option>
              {catalogos.tipos.map((t) => (
                <option key={t.id} value={t.id}>{t.nombre}</option>
              ))}
            </select>
            {errores.tipo_habitacion_id && <small className="campo-error">{errores.tipo_habitacion_id[0]}</small>}
          </label>
          <label>
            Estado
            <select value={form.estado_habitacion} onChange={(e) => set('estado_habitacion', e.target.value)}>
              {catalogos.estados.map((s) => (
                <option key={s.valor} value={s.valor}>{s.nombre}</option>
              ))}
            </select>
            {errores.estado_habitacion && <small className="campo-error">{errores.estado_habitacion[0]}</small>}
          </label>
        </div>

        <label>
          Observaciones
          <textarea rows="3" value={form.descripcion} onChange={(e) => set('descripcion', e.target.value)} />
        </label>

        <div className="form-acciones">
          <button type="button" className="btn btn--ghost" onClick={() => navigate('/panel/habitaciones')}>
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
