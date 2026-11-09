import { useCallback, useEffect, useRef, useState } from 'react'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { EstadoVacio, Indicador } from '../../../components/ui'
import { Paginacion, fechaHora, haceCuanto } from '../accesos/comun'
import '../../../styles/admin-procesos.css'

const PESTANAS = [
  ['', 'Todos'],
  ['en_curso', 'En curso'],
  ['detenidos', 'Detenidos'],
  ['terminados', 'Terminados'],
]

const SITUACION = {
  en_curso: { nombre: 'En curso', badge: 'badge--info' },
  detenido: { nombre: 'Detenido', badge: 'badge--danger' },
  terminado: { nombre: 'Terminado', badge: 'badge--ok' },
}

// Mientras hay algo corriendo se actualiza cada 2 s; si no, cada 15 s
const INTERVALO_ACTIVO = 2000
const INTERVALO_REPOSO = 15000

function duracion(segundos) {
  if (segundos === null || segundos === undefined) return '—'
  if (segundos < 60) return `${segundos} s`
  const min = Math.floor(segundos / 60)
  if (min < 60) return `${min} min ${segundos % 60} s`
  return `${Math.floor(min / 60)} h ${min % 60} min`
}

export default function ProcesosList() {
  const { can } = useAuth()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [resumen, setResumen] = useState(null)
  const [estado, setEstado] = useState('')
  const [search, setSearch] = useState('')
  const [busqueda, setBusqueda] = useState('')
  const [page, setPage] = useState(1)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(null)
  const [aviso, setAviso] = useState(null)
  const [lanzando, setLanzando] = useState(false)
  const [actualizado, setActualizado] = useState(null)
  const temporizador = useRef(null)

  const cargar = useCallback((silencioso = false) => {
    if (!silencioso) setCargando(true)
    return api
      .get('/admin/procesos', { params: { estado: estado || undefined, search: busqueda || undefined, page } })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
        setResumen(res.data.resumen)
        setActualizado(new Date())
        setError(null)
        return res.data.resumen
      })
      .catch(() => {
        setError('No se pudieron cargar los procesos.')
        return null
      })
      .finally(() => setCargando(false))
  }, [estado, busqueda, page])

  // Carga inicial y actualizacion automatica (se pausa con la pestana oculta)
  useEffect(() => {
    let activo = true

    function programar(r) {
      if (!activo) return
      const corriendo = r && (r.en_curso > 0 || r.en_cola > 0)
      temporizador.current = setTimeout(tick, corriendo ? INTERVALO_ACTIVO : INTERVALO_REPOSO)
    }

    function tick() {
      if (document.hidden) return programar(null)
      cargar(true).then(programar)
    }

    cargar(false).then(programar)

    return () => {
      activo = false
      clearTimeout(temporizador.current)
    }
  }, [cargar])

  function buscar(e) {
    e.preventDefault()
    setPage(1)
    setBusqueda(search.trim())
  }

  function cambiarPestana(valor) {
    setEstado(valor)
    setPage(1)
  }

  // Genera un error de prueba: su aviso por correo crea un proceso en la cola
  async function lanzarPrueba() {
    setLanzando(true)
    setAviso(null)
    try {
      await api.post('/admin/error-logs/prueba')
      setAviso('Se generó un error de prueba. Su aviso por correo entra a la cola y aparecerá aquí cuando el worker lo tome.')
      cargar(true)
    } catch {
      setError('No se pudo lanzar la prueba.')
    } finally {
      setLanzando(false)
    }
  }

  const conteo = (valor) => {
    if (!resumen) return null
    if (valor === '') return resumen.en_curso + resumen.detenidos + resumen.terminados
    return resumen[valor]
  }

  return (
    <div>
      <div className="page-head">
        <div>
          <h1 className="page-title">Procesos en segundo plano</h1>
          <p className="page-sub">Trabajos que el sistema ejecuta en la cola, como el envío de correos, con su avance en tiempo real.</p>
        </div>
        <div className="page-head__acciones">
          <button className="btn btn--ghost" onClick={() => cargar(true)} title="Actualizar ahora">
            <Icono nombre="recargar" size={15} /> Actualizar
          </button>
          {can('error_log_create') && (
            <button className="btn" onClick={lanzarPrueba} disabled={lanzando}>
              <Icono nombre="mas" size={15} /> {lanzando ? 'Lanzando…' : 'Lanzar prueba'}
            </button>
          )}
        </div>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--info">{aviso}</div>}

      {resumen && resumen.en_cola > 0 && resumen.cola !== 'sync' && (
        <div className="alerta alerta--aviso">
          Hay {resumen.en_cola} {resumen.en_cola === 1 ? 'trabajo esperando' : 'trabajos esperando'} en la cola.
          Si el número no baja en unos segundos, revisa que el worker (Supervisor) esté corriendo.
        </div>
      )}
      {resumen && resumen.detenidos > 0 && (
        <div className="alerta alerta--error">
          {resumen.detenidos === 1 ? 'Un proceso lleva' : `${resumen.detenidos} procesos llevan`} más de 10 minutos sin avanzar.
          Probablemente el worker se detuvo a mitad del trabajo.
        </div>
      )}

      {resumen && (
        <div className="indicadores">
          <Indicador icono="recargar" etiqueta="En curso" valor={resumen.en_curso} nota="avanzando ahora" />
          <Indicador icono="reloj" tono="mango" etiqueta="En cola" valor={resumen.en_cola} nota="esperando al worker" />
          <Indicador icono="check" tono="ok" etiqueta="Terminados" valor={resumen.terminados} nota={`${resumen.hoy} iniciados hoy`} />
          <Indicador icono="alerta" tono="error" etiqueta="Trabajos fallidos" valor={resumen.fallidos} nota="en failed_jobs" />
        </div>
      )}

      <div className="pr-barra">
        <div className="pestanas" role="tablist">
          {PESTANAS.map(([valor, nombre]) => (
            <button key={valor} type="button" className={estado === valor ? 'is-activa' : ''} onClick={() => cambiarPestana(valor)}>
              {nombre}
              {conteo(valor) !== null && <span className="pestanas__conteo">{conteo(valor)}</span>}
            </button>
          ))}
        </div>
        <form className="pr-busqueda" onSubmit={buscar}>
          <input
            className="filtros__busqueda"
            placeholder="Buscar por título…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            aria-label="Buscar proceso"
          />
          <button className="btn btn--ghost">Buscar</button>
        </form>
      </div>

      <div className="pr-lista">
        {cargando && [1, 2, 3].map((i) => (
          <div key={i} className="pr-proceso pr-proceso--cargando"><span className="esqueleto" /><span className="esqueleto" /></div>
        ))}

        {!cargando && items.length === 0 && (
          <div className="tarjeta-simple">
            <EstadoVacio
              icono="recargar"
              titulo={busqueda || estado ? 'Ningún proceso coincide' : 'Todavía no hay procesos'}
              texto={busqueda || estado
                ? 'Prueba con otro filtro.'
                : 'Cuando el sistema envíe correos en segundo plano, cada trabajo aparecerá aquí con su avance.'}
              accion={!busqueda && !estado && can('error_log_create') && (
                <button className="btn btn--ghost" onClick={lanzarPrueba} disabled={lanzando}>Lanzar una prueba</button>
              )}
            />
          </div>
        )}

        {!cargando && items.map((p) => {
          const s = SITUACION[p.situacion] || SITUACION.terminado
          return (
            <article key={p.id} className={`pr-proceso pr-proceso--${p.situacion}`}>
              <div className="pr-proceso__cabecera">
                <span className={`pr-proceso__icono pr-proceso__icono--${p.situacion}`}>
                  <Icono nombre={p.situacion === 'terminado' ? 'check' : p.situacion === 'detenido' ? 'alerta' : 'recargar'} size={18} />
                </span>
                <div className="pr-proceso__titulo">
                  <strong>{p.titulo}</strong>
                  <small>
                    #{p.id} · {p.usuario || 'Sistema'} · inició {fechaHora(p.fecha_inicio)}
                  </small>
                </div>
                <span className={`badge ${s.badge}`}>{s.nombre}</span>
              </div>

              <div className="pr-progreso" role="progressbar" aria-valuenow={p.porcentaje} aria-valuemin="0" aria-valuemax="100">
                <div className="pr-progreso__barra" style={{ width: `${p.porcentaje}%` }} />
              </div>

              <div className="pr-proceso__datos">
                <span><strong>{p.porcentaje}%</strong> · {p.progreso} de {p.total} {p.total === 1 ? 'tarea' : 'tareas'}</span>
                <span>
                  {p.situacion === 'terminado'
                    ? <>Terminó {fechaHora(p.fecha_fin)} · duró {duracion(p.duracion_segundos)}</>
                    : <>Lleva {duracion(p.duracion_segundos)} · último avance {haceCuanto(p.ultimo_avance)}</>}
                </span>
              </div>
            </article>
          )
        })}
      </div>

      <Paginacion meta={meta} page={page} setPage={setPage} nombre="procesos" />

      {actualizado && (
        <p className="pr-actualizado">
          <span className={resumen && (resumen.en_curso > 0 || resumen.en_cola > 0) ? 'pr-pulso is-activo' : 'pr-pulso'} />
          Actualizado a las {actualizado.toLocaleTimeString('es-SV')} ·
          {resumen && (resumen.en_curso > 0 || resumen.en_cola > 0) ? ' se refresca cada 2 segundos' : ' se refresca cada 15 segundos'}
        </p>
      )}
    </div>
  )
}
