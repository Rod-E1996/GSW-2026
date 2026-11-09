import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'
import { FormSeccion } from '../../../components/ui'
import '../../../styles/admin-hotel.css'

const AYUDA_ESTADO = {
  disponible: 'Lista para recibir huéspedes.',
  limpieza: 'Se puede reservar; recepción ve que falta limpiarla.',
  mantenimiento: 'No se ofrece en reservas nuevas mientras siga así.',
}

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
  const [numeroOriginal, setNumeroOriginal] = useState('')
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
          setNumeroOriginal(h.numero)
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
        setError('Revisa los campos marcados.')
      } else {
        setError(err.response?.data?.message || 'No se pudo guardar.')
      }
    } finally {
      setGuardando(false)
    }
  }

  const campoError = (campo) => errores[campo] && <small className="campo-error">{errores[campo][0]}</small>

  if (cargando) return <p className="estado">Cargando…</p>

  return (
    <div className="form-page">
      <Link to="/panel/habitaciones" className="reserva__volver">← Habitaciones</Link>
      <div className="page-head">
        <div>
          <h1 className="page-title">{editando ? `Editar habitación ${numeroOriginal}` : 'Nueva habitación'}</h1>
          <p className="page-sub">Cada habitación física del hotel, con su tipo y su estado de limpieza o mantenimiento.</p>
        </div>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}

      <form className="form" onSubmit={onSubmit}>
        <FormSeccion titulo="Ubicación" descripcion="El número que ve el huésped en la puerta y el piso donde está. El número no se puede repetir.">
          <div className="form-row">
            <label>
              Número
              <input value={form.numero} maxLength="10" onChange={(e) => set('numero', e.target.value)} placeholder="Ej.: 204" autoFocus={!editando} />
              {campoError('numero')}
            </label>
            <label>
              Piso
              <input type="number" min="0" value={form.piso} onChange={(e) => set('piso', e.target.value)} />
              {campoError('piso')}
            </label>
          </div>
        </FormSeccion>

        <FormSeccion titulo="Tipo" descripcion="Define la capacidad y el precio. No se puede cambiar si la habitación tiene reservas por atender.">
          <label>
            Tipo de habitación
            <select value={form.tipo_habitacion_id} onChange={(e) => set('tipo_habitacion_id', e.target.value)}>
              <option value="">Selecciona un tipo</option>
              {catalogos.tipos.map((t) => (
                <option key={t.id} value={t.id}>{t.nombre}</option>
              ))}
            </select>
            {campoError('tipo_habitacion_id')}
          </label>
        </FormSeccion>

        <FormSeccion titulo="Estado" descripcion="El estado físico de la habitación. Recepción también lo cambia desde el tablero.">
          <div className="opciones-estado" role="radiogroup" aria-label="Estado de la habitación">
            {catalogos.estados.map((s) => (
              <label key={s.valor} className={`opcion-estado opcion-estado--${s.valor} ${form.estado_habitacion === s.valor ? 'is-elegida' : ''}`}>
                <input
                  type="radio"
                  name="estado_habitacion"
                  value={s.valor}
                  checked={form.estado_habitacion === s.valor}
                  onChange={() => set('estado_habitacion', s.valor)}
                />
                <span className="opcion-estado__nombre"><i className={`hab-punto hab-punto--${s.valor}`} />{s.nombre}</span>
                <span className="opcion-estado__ayuda">{AYUDA_ESTADO[s.valor]}</span>
              </label>
            ))}
          </div>
          {campoError('estado_habitacion')}
        </FormSeccion>

        <FormSeccion titulo="Observaciones" descripcion="Notas internas para recepción: vista, accesibilidad, detalles pendientes.">
          <label>
            Observaciones
            <textarea rows="3" value={form.descripcion} onChange={(e) => set('descripcion', e.target.value)} placeholder="Ej.: vista a la piscina" />
            {campoError('descripcion')}
          </label>
        </FormSeccion>

        <div className="form-acciones">
          <button type="button" className="btn btn--ghost" onClick={() => navigate('/panel/habitaciones')}>
            Cancelar
          </button>
          <button className="btn" disabled={guardando}>
            {guardando ? 'Guardando…' : editando ? 'Guardar cambios' : 'Crear habitación'}
          </button>
        </div>
      </form>
    </div>
  )
}
