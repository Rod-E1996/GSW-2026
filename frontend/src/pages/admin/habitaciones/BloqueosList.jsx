import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { FilaVacia, FilasCargando } from '../../../components/ui'
import { fechaCorta, hoyEnHotel } from '../../../utils'
import '../../../styles/admin-hotel.css'

const VACIO = { habitacion_id: '', fecha_inicio: '', fecha_fin: '', motivo: 'mantenimiento', descripcion: '' }

// Dias que dura un bloqueo, contando el primero y el ultimo
function duracion(inicio, fin) {
  if (!inicio || !fin || fin < inicio) return 0
  return Math.round((new Date(`${fin}T12:00:00`) - new Date(`${inicio}T12:00:00`)) / 86400000) + 1
}

function situacion(b, hoy) {
  if (b.fecha_fin < hoy) return { nombre: 'Terminado', clase: '' }
  if (b.fecha_inicio > hoy) return { nombre: 'Próximo', clase: 'badge--info' }
  return { nombre: 'En curso', clase: 'badge--aviso' }
}

export default function BloqueosList() {
  const { can } = useAuth()
  const [params] = useSearchParams()
  const habitacionInicial = params.get('habitacion') || ''
  const hoy = hoyEnHotel()

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
    setMensaje(null)
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
      setMensaje(editandoId ? 'Bloqueo actualizado.' : 'Bloqueo registrado: la habitación no se ofrecerá en esas fechas.')
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

  function cambiarHabitacion(valor) {
    setPage(1)
    setFiltroHabitacion(valor)
    if (!editandoId) set('habitacion_id', valor)
  }

  function cambiarAlcance(conTerminados) {
    setPage(1)
    setTerminados(conTerminados)
  }

  const puedeEscribir = editandoId ? can('bloqueo_update') : can('bloqueo_store')
  const campoError = (campo) => errores[campo] && <small className="campo-error">{errores[campo][0]}</small>
  const dias = duracion(form.fecha_inicio, form.fecha_fin)
  const numeroHabitacion = (hid) => habitaciones.find((h) => String(h.id) === String(hid))?.numero

  return (
    <div>
      <Link to="/panel/habitaciones" className="reserva__volver">← Habitaciones</Link>
      <div className="page-head">
        <div>
          <h1 className="page-title">Bloqueos de habitación</h1>
          <p className="page-sub">Fechas en que una habitación no se puede reservar, por mantenimiento o uso interno.</p>
        </div>
      </div>

      {mensaje && <div className="alerta alerta--ok">{mensaje}</div>}
      {error && <div className="alerta alerta--error">{error}</div>}

      <div className={`bloqueos ${puedeEscribir ? '' : 'bloqueos--sin-form'}`}>
        <div className="bloqueos__lista">
          <div className="bloqueos__barra">
            <div className="pestanas" role="tablist" aria-label="Bloqueos a mostrar">
              <button type="button" role="tab" aria-selected={!terminados} className={!terminados ? 'is-activa' : ''} onClick={() => cambiarAlcance(false)}>
                En curso y próximos
              </button>
              <button type="button" role="tab" aria-selected={terminados} className={terminados ? 'is-activa' : ''} onClick={() => cambiarAlcance(true)}>
                Todos
              </button>
            </div>
            <select aria-label="Filtrar por habitación" value={filtroHabitacion} onChange={(e) => cambiarHabitacion(e.target.value)}>
              <option value="">Todas las habitaciones</option>
              {habitaciones.map((h) => (
                <option key={h.id} value={h.id}>Habitación {h.numero}</option>
              ))}
            </select>
          </div>

          <div className="tabla-wrap">
            <table className="tabla">
              <thead>
                <tr>
                  <th>Habitación</th>
                  <th>Fechas</th>
                  <th>Situación</th>
                  <th>Registrado por</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {cargando && items.length === 0 && <FilasCargando columnas={5} filas={4} />}
                {!cargando && items.length === 0 && (
                  <FilaVacia
                    columnas={5}
                    icono="candado"
                    titulo={terminados ? 'No hay bloqueos registrados' : 'No hay bloqueos en curso ni próximos'}
                    texto="Todas las habitaciones se pueden reservar en cualquier fecha."
                  />
                )}
                {items.map((b) => {
                  const s = situacion(b, hoy)
                  return (
                    <tr key={b.id} className={editandoId === b.id ? 'is-editando' : ''}>
                      <td>
                        <div className="celda-entidad">
                          <span className="hab-numero hab-numero--bloqueo">{b.habitacion}</span>
                          <div>
                            <strong>{b.motivo_nombre}</strong>
                            {b.descripcion && <small title={b.descripcion}>{b.descripcion}</small>}
                          </div>
                        </div>
                      </td>
                      <td>
                        <div className="celda-entidad">
                          <div>
                            <strong>{fechaCorta(b.fecha_inicio)} → {fechaCorta(b.fecha_fin)}</strong>
                            <small>{duracion(b.fecha_inicio, b.fecha_fin)} {duracion(b.fecha_inicio, b.fecha_fin) === 1 ? 'día' : 'días'}</small>
                          </div>
                        </div>
                      </td>
                      <td><span className={`badge ${s.clase}`}>{s.nombre}</span></td>
                      <td>{b.registrado_por || <span className="form-nota">—</span>}</td>
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
                  )
                })}
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
              <button className="btn btn--ghost" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>
                Siguiente →
              </button>
            </div>
          )}
        </div>

        {puedeEscribir && (
          <aside className="bloqueos__panel">
            <form className="form" onSubmit={onSubmit}>
              <div className="bloqueos__panel-titulo">
                <span className="indicador__icono"><Icono nombre={editandoId ? 'editar' : 'candado'} size={18} /></span>
                <div>
                  <h3>{editandoId ? 'Editar bloqueo' : 'Nuevo bloqueo'}</h3>
                  <p className="form-nota">Se bloquean el primer y el último día indicados.</p>
                </div>
              </div>

              <label>
                Habitación
                <select value={form.habitacion_id} onChange={(e) => set('habitacion_id', e.target.value)}>
                  <option value="">Selecciona una habitación</option>
                  {habitaciones.map((h) => (
                    <option key={h.id} value={h.id}>Habitación {h.numero}</option>
                  ))}
                </select>
                {campoError('habitacion_id')}
              </label>

              <div className="motivos" role="radiogroup" aria-label="Motivo">
                {motivos.map((m) => (
                  <label key={m.valor} className={`motivo ${form.motivo === m.valor ? 'is-elegido' : ''}`}>
                    <input type="radio" name="motivo" value={m.valor} checked={form.motivo === m.valor} onChange={() => set('motivo', m.valor)} />
                    {m.nombre}
                  </label>
                ))}
              </div>
              {campoError('motivo')}

              <div className="form-row">
                <label>
                  Primer día
                  <input type="date" value={form.fecha_inicio} onChange={(e) => set('fecha_inicio', e.target.value)} />
                  {campoError('fecha_inicio')}
                </label>
                <label>
                  Último día
                  <input type="date" min={form.fecha_inicio || undefined} value={form.fecha_fin} onChange={(e) => set('fecha_fin', e.target.value)} />
                  {campoError('fecha_fin')}
                </label>
              </div>

              {dias > 0 && (
                <p className="bloqueos__resumen">
                  <Icono nombre="info" size={15} />
                  {numeroHabitacion(form.habitacion_id) ? `La habitación ${numeroHabitacion(form.habitacion_id)}` : 'La habitación'} quedará fuera de venta {dias} {dias === 1 ? 'día' : 'días'}.
                </p>
              )}

              <label>
                Detalle
                <textarea rows="2" value={form.descripcion} onChange={(e) => set('descripcion', e.target.value)} placeholder="Ej.: cambio del compresor del aire" />
                {campoError('descripcion')}
              </label>

              <div className="form-acciones">
                {editandoId && (
                  <button type="button" className="btn btn--ghost" onClick={limpiar}>
                    Cancelar
                  </button>
                )}
                <button className="btn" disabled={guardando}>
                  {guardando ? 'Guardando…' : editandoId ? 'Guardar cambios' : 'Bloquear fechas'}
                </button>
              </div>
            </form>
          </aside>
        )}
      </div>
    </div>
  )
}
