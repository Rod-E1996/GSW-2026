import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import { fechaCorta } from '../../../utils'

const VACIO = { habitacion_id: '', fecha_inicio: '', fecha_fin: '', motivo: 'mantenimiento', descripcion: '' }

export default function BloqueosList() {
  const { can } = useAuth()
  const [params] = useSearchParams()
  const habitacionInicial = params.get('habitacion') || ''

  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [habitaciones, setHabitaciones] = useState([])
  const [motivos, setMotivos] = useState([])
  const [filtroHabitacion, setFiltroHabitacion] = useState(habitacionInicial)
  const [terminados, setTerminados] = useState(false)
  const [page, setPage] = useState(1)
  const [form, setForm] = useState({ ...VACIO, habitacion_id: habitacionInicial })
  const [editandoId, setEditandoId] = useState(null)
  const [errores, setErrores] = useState({})
  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)
  const [mensaje, setMensaje] = useState(null)
  const [error, setError] = useState(null)

  function cargar() {
    setCargando(true)
    api
      .get('/admin/bloqueos', {
        params: {
          habitacion_id: filtroHabitacion || undefined,
          terminados: terminados ? 1 : undefined,
          page,
        },
      })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
        setHabitaciones(res.data.habitaciones)
        setMotivos(res.data.motivos)
      })
      .catch(() => setError('No se pudieron cargar los bloqueos.'))
      .finally(() => setCargando(false))
  }

  useEffect(() => {
    cargar()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, filtroHabitacion, terminados])

  function set(campo, valor) {
    setForm((f) => ({ ...f, [campo]: valor }))
  }

  function limpiar() {
    setForm({ ...VACIO, habitacion_id: filtroHabitacion })
    setEditandoId(null)
    setErrores({})
  }

  function editar(b) {
    setEditandoId(b.id)
    setErrores({})
    setForm({
      habitacion_id: b.habitacion_id,
      fecha_inicio: b.fecha_inicio,
      fecha_fin: b.fecha_fin,
      motivo: b.motivo,
      descripcion: b.descripcion || '',
    })
  }

  async function onSubmit(e) {
    e.preventDefault()
    setGuardando(true)
    setErrores({})
    setError(null)
    setMensaje(null)
    try {
      if (editandoId) {
        await api.put(`/admin/bloqueos/${editandoId}`, form)
      } else {
        await api.post('/admin/bloqueos', form)
      }
      setMensaje('Bloqueo guardado con éxito.')
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

  async function eliminar(b) {
    if (!confirm(`¿Quitar el bloqueo de la habitación ${b.habitacion}?`)) return
    setError(null)
    setMensaje(null)
    try {
      const res = await api.delete(`/admin/bloqueos/${b.id}`)
      setMensaje(res.data.message)
      if (editandoId === b.id) limpiar()
      cargar()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo eliminar.')
    }
  }

  const puedeEscribir = editandoId ? can('bloqueo_update') : can('bloqueo_store')

  return (
    <div>
      <div className="page-head">
        <h1 className="page-title">Bloqueos de habitación</h1>
        <Link to="/panel/habitaciones" className="btn btn--ghost">
          ← Habitaciones
        </Link>
      </div>
      <p className="page-sub">La habitación no se podrá reservar desde el primer hasta el último día indicados.</p>

      {mensaje && <div className="alerta alerta--ok">{mensaje}</div>}
      {error && <div className="alerta alerta--error">{error}</div>}

      {puedeEscribir && (
        <form className="form form--seccion" onSubmit={onSubmit}>
          <h2 className="seccion-title">{editandoId ? 'Editar bloqueo' : 'Nuevo bloqueo'}</h2>
          <div className="form-row">
            <label>
              Habitación
              <select value={form.habitacion_id} onChange={(e) => set('habitacion_id', e.target.value)}>
                <option value="">Selecciona una habitación</option>
                {habitaciones.map((h) => (
                  <option key={h.id} value={h.id}>{h.numero}</option>
                ))}
              </select>
              {errores.habitacion_id && <small className="campo-error">{errores.habitacion_id[0]}</small>}
            </label>
            <label>
              Motivo
              <select value={form.motivo} onChange={(e) => set('motivo', e.target.value)}>
                {motivos.map((m) => (
                  <option key={m.valor} value={m.valor}>{m.nombre}</option>
                ))}
              </select>
              {errores.motivo && <small className="campo-error">{errores.motivo[0]}</small>}
            </label>
          </div>
          <div className="form-row">
            <label>
              Primer día
              <input type="date" value={form.fecha_inicio} onChange={(e) => set('fecha_inicio', e.target.value)} />
              {errores.fecha_inicio && <small className="campo-error">{errores.fecha_inicio[0]}</small>}
            </label>
            <label>
              Último día
              <input type="date" value={form.fecha_fin} onChange={(e) => set('fecha_fin', e.target.value)} />
              {errores.fecha_fin && <small className="campo-error">{errores.fecha_fin[0]}</small>}
            </label>
          </div>
          <label>
            Detalle
            <textarea rows="2" value={form.descripcion} onChange={(e) => set('descripcion', e.target.value)} />
          </label>
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

      <div className="filtros">
        <select
          aria-label="Filtrar por habitación"
          value={filtroHabitacion}
          onChange={(e) => {
            setPage(1)
            setFiltroHabitacion(e.target.value)
          }}
        >
          <option value="">Todas las habitaciones</option>
          {habitaciones.map((h) => (
            <option key={h.id} value={h.id}>{h.numero}</option>
          ))}
        </select>
        <label className="filtros__check">
          <input
            type="checkbox"
            checked={terminados}
            onChange={(e) => {
              setPage(1)
              setTerminados(e.target.checked)
            }}
          />
          Incluir los terminados
        </label>
      </div>

      <div className="tabla-wrap">
        <table className="tabla">
          <thead>
            <tr>
              <th>Habitación</th>
              <th>Primer día</th>
              <th>Último día</th>
              <th>Motivo</th>
              <th>Detalle</th>
              <th>Registrado por</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {cargando && (
              <tr><td colSpan="7" className="tabla__vacio">Cargando…</td></tr>
            )}
            {!cargando && items.length === 0 && (
              <tr><td colSpan="7" className="tabla__vacio">Sin bloqueos.</td></tr>
            )}
            {!cargando &&
              items.map((b) => (
                <tr key={b.id}>
                  <td>{b.habitacion}</td>
                  <td>{fechaCorta(b.fecha_inicio)}</td>
                  <td>{fechaCorta(b.fecha_fin)}</td>
                  <td>{b.motivo_nombre}</td>
                  <td className="celda-corta">{b.descripcion || '—'}</td>
                  <td>{b.registrado_por || '—'}</td>
                  <td className="tabla__acciones">
                    {can('bloqueo_update') && (
                      <button className="btn btn--sm btn--ghost" onClick={() => editar(b)}>
                        Editar
                      </button>
                    )}
                    {can('bloqueo_destroy') && (
                      <button className="btn btn--sm btn--danger" onClick={() => eliminar(b)}>
                        Quitar
                      </button>
                    )}
                  </td>
                </tr>
              ))}
          </tbody>
        </table>
      </div>

      {meta && meta.last_page > 1 && (
        <div className="paginacion">
          <button className="btn btn--ghost" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            ← Anterior
          </button>
          <span>
            Página {meta.current_page} de {meta.last_page}
          </span>
          <button
            className="btn btn--ghost"
            disabled={page >= meta.last_page}
            onClick={() => setPage((p) => p + 1)}
          >
            Siguiente →
          </button>
        </div>
      )}
    </div>
  )
}
