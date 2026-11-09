import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { EstadoVacio, Indicador } from '../../../components/ui'
import { fechaCorta, formatoUSD, hoyEnHotel } from '../../../utils'
import '../../../styles/admin-catalogo.css'

const clave = (tipoId, temporadaId) => `${tipoId}-${temporadaId}`
const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic']
const COLOR_DEFECTO = '#008a9c'

// Dias entre dos fechas AAAA-MM-DD (sin zona horaria)
function dias(desde, hasta) {
  const a = Date.UTC(...desde.split('-').map((n, i) => Number(n) - (i === 1 ? 1 : 0)))
  const b = Date.UTC(...hasta.split('-').map((n, i) => Number(n) - (i === 1 ? 1 : 0)))
  return Math.round((b - a) / 86400000)
}

function estadoTemporada(t, hoy) {
  if (t.fecha_fin < hoy) return { clave: 'terminada', nombre: 'Terminada', badge: '' }
  if (t.fecha_inicio <= hoy) return { clave: 'curso', nombre: 'En curso', badge: 'badge--ok' }
  return { clave: 'proxima', nombre: 'Próxima', badge: 'badge--info' }
}

// Las temporadas vienen paginadas: para la linea de tiempo se piden todas las paginas
async function todasLasTemporadas() {
  const primera = await api.get('/admin/temporadas', { params: { page: 1 } })
  const ultima = primera.data.meta?.last_page || 1
  const resto = await Promise.all(
    Array.from({ length: ultima - 1 }, (_, i) => api.get('/admin/temporadas', { params: { page: i + 2 } })),
  )
  return [primera, ...resto].flatMap((r) => r.data.data)
}

function LineaDeTiempo({ temporadas, anio, hoy }) {
  const inicioAnio = `${anio}-01-01`
  const finAnio = `${anio}-12-31`
  const total = dias(inicioAnio, finAnio) + 1
  const delAnio = temporadas
    .filter((t) => t.fecha_inicio <= finAnio && t.fecha_fin >= inicioAnio)
    .sort((a, b) => a.fecha_inicio.localeCompare(b.fecha_inicio))
  const pos = (fecha) => (dias(inicioAnio, fecha) / total) * 100
  const hoyVisible = hoy >= inicioAnio && hoy <= finAnio

  if (delAnio.length === 0) {
    return <EstadoVacio icono="calendario" titulo={`Sin temporadas en ${anio}`} texto="Todo el año se cobra el precio base de cada tipo de habitación." />
  }

  return (
    <div className="linea-tiempo">
      <div className="linea-tiempo__meses">
        <span className="linea-tiempo__etiqueta" />
        <div className="linea-tiempo__pista">
          {MESES.map((m, i) => (
            <span key={m} style={{ left: `${pos(`${anio}-${String(i + 1).padStart(2, '0')}-01`)}%` }}>{m}</span>
          ))}
        </div>
      </div>
      {delAnio.map((t) => {
        const desde = t.fecha_inicio < inicioAnio ? inicioAnio : t.fecha_inicio
        const hasta = t.fecha_fin > finAnio ? finAnio : t.fecha_fin
        const izquierda = pos(desde)
        const ancho = Math.max(((dias(desde, hasta) + 1) / total) * 100, 0.6)
        const color = t.color || COLOR_DEFECTO
        return (
          <div key={t.id} className="linea-tiempo__fila">
            <span className="linea-tiempo__etiqueta" title={t.nombre}>
              <i style={{ background: color }} />
              {t.nombre}
            </span>
            <div className="linea-tiempo__pista">
              {MESES.map((m, i) => (
                <span key={m} className="linea-tiempo__guia" style={{ left: `${pos(`${anio}-${String(i + 1).padStart(2, '0')}-01`)}%` }} />
              ))}
              <div
                className="linea-tiempo__barra"
                style={{ left: `${izquierda}%`, width: `${ancho}%`, '--color': color }}
                title={`${t.nombre}: ${fechaCorta(t.fecha_inicio)} → ${fechaCorta(t.fecha_fin)} · ×${Number(t.multiplicador).toFixed(2)}`}
              >
                <span>×{Number(t.multiplicador).toFixed(2)}</span>
              </div>
              {hoyVisible && <span className="linea-tiempo__hoy" style={{ left: `${pos(hoy)}%` }} />}
            </div>
          </div>
        )
      })}
      {hoyVisible && (
        <div className="linea-tiempo__leyenda">
          <span><i className="linea-tiempo__marca-hoy" /> Hoy, {fechaCorta(hoy)}</span>
          <span>Fuera de temporada se cobra el precio base.</span>
        </div>
      )}
    </div>
  )
}

export default function TarifasPage() {
  const { can } = useAuth()
  const hoy = hoyEnHotel()
  const [temporadas, setTemporadas] = useState([])
  const [matriz, setMatriz] = useState({ tipos: [], temporadas: [], tarifas: [] })
  const [precios, setPrecios] = useState({})
  const [originales, setOriginales] = useState({})
  const [errores, setErrores] = useState({})
  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)
  const [mensaje, setMensaje] = useState(null)
  const [error, setError] = useState(null)
  const [vista, setVista] = useState('vigentes')
  const [anio, setAnio] = useState(Number(hoy.slice(0, 4)))

  const puedeEditar = can('tarifa_update')

  function aplicarMatriz(datos) {
    const valores = Object.fromEntries(
      datos.tarifas.map((t) => [clave(t.tipo_habitacion_id, t.temporada_id), String(Number(t.precio_noche))]),
    )
    setMatriz(datos)
    setPrecios(valores)
    setOriginales(valores)
  }

  function cargarTodo() {
    setCargando(true)
    Promise.all([todasLasTemporadas().then(setTemporadas), api.get('/admin/tarifas').then((res) => aplicarMatriz(res.data))])
      .catch(() => setError('No se pudieron cargar las temporadas y tarifas.'))
      .finally(() => setCargando(false))
  }

  useEffect(() => {
    cargarTodo()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  async function eliminarTemporada(t) {
    if (!confirm(`¿Eliminar la temporada "${t.nombre}"? Sus precios fijos también dejarán de aplicarse.`)) return
    setMensaje(null)
    setError(null)
    try {
      const res = await api.delete(`/admin/temporadas/${t.id}`)
      setMensaje(res.data.message)
      cargarTodo()
    } catch (err) {
      setError(err.response?.data?.message || 'No se pudo eliminar.')
    }
  }

  async function guardarTarifas(e) {
    e.preventDefault()
    setGuardando(true)
    setErrores({})
    setMensaje(null)
    setError(null)

    const tarifas = matriz.tipos.flatMap((tipo) =>
      matriz.temporadas.map((temporada) => ({
        tipo_habitacion_id: tipo.id,
        temporada_id: temporada.id,
        precio_noche: precios[clave(tipo.id, temporada.id)] || null,
      })),
    )

    try {
      const res = await api.put('/admin/tarifas', { tarifas })
      aplicarMatriz(res.data)
      setMensaje(res.data.message)
    } catch (err) {
      if (err.response?.status === 422) {
        // Convierte tarifas.N.precio_noche en la celda que le corresponde
        setErrores(
          Object.fromEntries(
            Object.keys(err.response.data.errors || {}).map((campo) => {
              const celda = tarifas[Number(campo.split('.')[1])]
              return [clave(celda.tipo_habitacion_id, celda.temporada_id), true]
            }),
          ),
        )
        setError('Revisa los precios marcados. No se guardó ningún cambio.')
      } else {
        setError(err.response?.data?.message || 'No se pudieron guardar las tarifas.')
      }
    } finally {
      setGuardando(false)
    }
  }

  const cambios = useMemo(() => {
    const llaves = new Set([...Object.keys(precios), ...Object.keys(originales)])
    return [...llaves].filter((k) => (precios[k] || '') !== (originales[k] || '')).length
  }, [precios, originales])

  const anios = useMemo(() => {
    const lista = new Set([Number(hoy.slice(0, 4))])
    temporadas.forEach((t) => {
      lista.add(Number(t.fecha_inicio.slice(0, 4)))
      lista.add(Number(t.fecha_fin.slice(0, 4)))
    })
    return [...lista].sort()
  }, [temporadas, hoy])

  const listado = useMemo(() => {
    const ordenadas = [...temporadas].sort((a, b) => a.fecha_inicio.localeCompare(b.fecha_inicio))
    if (vista === 'terminadas') return ordenadas.filter((t) => t.fecha_fin < hoy).reverse()
    if (vista === 'vigentes') return ordenadas.filter((t) => t.fecha_fin >= hoy)
    return ordenadas
  }, [temporadas, vista, hoy])

  if (cargando) {
    return (
      <div>
        <span className="esqueleto" style={{ width: 260, height: '1.6rem', marginBottom: '1.5rem' }} />
        <div className="indicadores">{[1, 2, 3, 4].map((i) => <div key={i} className="indicador"><span className="esqueleto" /></div>)}</div>
        <div className="tarjeta-simple"><span className="esqueleto" /><br /><span className="esqueleto" /></div>
      </div>
    )
  }

  const actual = temporadas
    .filter((t) => t.fecha_inicio <= hoy && t.fecha_fin >= hoy)
    .sort((a, b) => (b.prioridad ?? 0) - (a.prioridad ?? 0) || b.multiplicador - a.multiplicador)[0]
  const proxima = temporadas.filter((t) => t.fecha_inicio > hoy).sort((a, b) => a.fecha_inicio.localeCompare(b.fecha_inicio))[0]
  const fijas = Object.values(originales).filter(Boolean).length
  const conteo = {
    vigentes: temporadas.filter((t) => t.fecha_fin >= hoy).length,
    terminadas: temporadas.filter((t) => t.fecha_fin < hoy).length,
    todas: temporadas.length,
  }

  return (
    <div className="tarifas">
      <div className="page-head">
        <div>
          <h1 className="page-title">Temporadas y tarifas</h1>
          <p className="page-sub">Los períodos del año que cambian el precio por noche de cada tipo de habitación.</p>
        </div>
        <div className="page-head__acciones">
          {can('temporada_store') && (
            <Link to="/panel/tarifas/temporadas/nueva" className="btn">
              <Icono nombre="mas" size={16} /> Nueva temporada
            </Link>
          )}
        </div>
      </div>

      {mensaje && <div className="alerta alerta--ok">{mensaje}</div>}
      {error && <div className="alerta alerta--error">{error}</div>}

      <div className="indicadores">
        <Indicador
          icono="tarifa"
          etiqueta="Temporada de hoy"
          valor={actual ? actual.nombre : 'Baja'}
          nota={actual ? `×${Number(actual.multiplicador).toFixed(2)} hasta el ${fechaCorta(actual.fecha_fin)}` : 'se cobra el precio base'}
        />
        <Indicador
          icono="calendario"
          tono="azul"
          etiqueta="Próxima temporada"
          valor={proxima ? proxima.nombre : '—'}
          nota={proxima ? `desde el ${fechaCorta(proxima.fecha_inicio)} (en ${dias(hoy, proxima.fecha_inicio)} días)` : 'no hay ninguna programada'}
        />
        <Indicador icono="lista" tono="ok" etiqueta="Temporadas vigentes" valor={conteo.vigentes} nota={`${conteo.terminadas} terminadas`} />
        <Indicador icono="dinero" tono="mango" etiqueta="Precios fijos" valor={fijas} nota="el resto se calcula" />
      </div>

      {/* Linea de tiempo del ano */}
      <section className="tarjeta-simple">
        <div className="seccion-cabecera">
          <div>
            <h2>Calendario de temporadas</h2>
            <p className="form-nota">Si dos temporadas se cruzan, manda la de mayor prioridad.</p>
          </div>
          <div className="selector-anio" role="group" aria-label="Año">
            <button type="button" onClick={() => setAnio((a) => a - 1)} disabled={anio <= Math.min(...anios)} aria-label="Año anterior">‹</button>
            <strong>{anio}</strong>
            <button type="button" onClick={() => setAnio((a) => a + 1)} disabled={anio >= Math.max(...anios)} aria-label="Año siguiente">›</button>
          </div>
        </div>
        <LineaDeTiempo temporadas={temporadas} anio={anio} hoy={hoy} />
      </section>

      {/* Lista de temporadas */}
      <section className="bloque">
        <div className="seccion-cabecera seccion-cabecera--suelta">
          <h2>Temporadas</h2>
          <div className="pestanas" role="tablist">
            {[['vigentes', 'Vigentes y próximas'], ['terminadas', 'Terminadas'], ['todas', 'Todas']].map(([v, nombre]) => (
              <button key={v} type="button" role="tab" aria-selected={vista === v} className={vista === v ? 'is-activa' : ''} onClick={() => setVista(v)}>
                {nombre} <span className="pestanas__conteo">{conteo[v]}</span>
              </button>
            ))}
          </div>
        </div>

        <div className="tabla-wrap">
          <table className="tabla">
            <thead>
              <tr>
                <th>Temporada</th>
                <th>Fechas</th>
                <th>Duración</th>
                <th>Ajuste</th>
                <th>Prioridad</th>
                <th>Estado</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {listado.length === 0 && (
                <tr className="tabla__fila-vacia">
                  <td colSpan="7">
                    <EstadoVacio
                      icono="calendario"
                      titulo={vista === 'terminadas' ? 'Ninguna temporada ha terminado' : 'No hay temporadas por venir'}
                      texto="Sin temporada se cobra el precio base de cada tipo de habitación."
                      accion={vista !== 'terminadas' && can('temporada_store') && <Link to="/panel/tarifas/temporadas/nueva" className="btn">Crear temporada</Link>}
                    />
                  </td>
                </tr>
              )}
              {listado.map((t) => {
                const estado = estadoTemporada(t, hoy)
                const pct = Math.round((Number(t.multiplicador) - 1) * 100)
                return (
                  <tr key={t.id} className={estado.clave === 'terminada' ? 'is-apagada' : ''}>
                    <td>
                      <div className="celda-entidad">
                        <span className="muestra-temporada" style={{ background: t.color || COLOR_DEFECTO }} />
                        <div>
                          <strong>{t.nombre}</strong>
                          {t.nombre_en && <small>{t.nombre_en}</small>}
                        </div>
                      </div>
                    </td>
                    <td className="celda-fechas">{fechaCorta(t.fecha_inicio)} → {fechaCorta(t.fecha_fin)}</td>
                    <td>{dias(t.fecha_inicio, t.fecha_fin) + 1} días</td>
                    <td>
                      <span className={`ajuste ${pct > 0 ? 'ajuste--sube' : pct < 0 ? 'ajuste--baja' : ''}`}>
                        ×{Number(t.multiplicador).toFixed(2)} <small>{pct > 0 ? `+${pct}` : pct} %</small>
                      </span>
                    </td>
                    <td>{t.prioridad ?? 0}</td>
                    <td><span className={`badge ${estado.badge}`}>{estado.nombre}</span></td>
                    <td className="tabla__acciones">
                      {can('temporada_update') && (
                        <Link to={`/panel/tarifas/temporadas/${t.id}/editar`} className="btn btn--sm btn--ghost">
                          <Icono nombre="editar" size={14} /> Editar
                        </Link>
                      )}
                      {can('temporada_destroy') && (
                        <button className="btn btn--sm btn--danger btn--icono-sm" onClick={() => eliminarTemporada(t)} title={`Eliminar ${t.nombre}`} aria-label={`Eliminar ${t.nombre}`}>
                          <Icono nombre="eliminar" size={14} />
                        </button>
                      )}
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      </section>

      {/* Matriz de precios */}
      <section className="bloque">
        <div className="seccion-cabecera seccion-cabecera--suelta">
          <div>
            <h2>Precio por noche</h2>
            <p className="form-nota">Escribe un precio para fijarlo; deja la casilla vacía para que se calcule con el multiplicador. Solo aparecen las temporadas que no han terminado.</p>
          </div>
          <div className="leyenda-precios">
            <span><i className="leyenda-precios__calculado" /> Calculado</span>
            <span><i className="leyenda-precios__fijo" /> Precio fijo</span>
          </div>
        </div>

        {matriz.temporadas.length === 0 || matriz.tipos.length === 0 ? (
          <div className="tarjeta-simple">
            <EstadoVacio
              icono="tarifa"
              titulo="Nada que tarifar todavía"
              texto="Necesitas al menos un tipo de habitación y una temporada vigente para definir precios."
              accion={can('temporada_store') && <Link to="/panel/tarifas/temporadas/nueva" className="btn">Crear temporada</Link>}
            />
          </div>
        ) : (
          <form onSubmit={guardarTarifas}>
            <div className="tabla-wrap">
              <table className="tabla matriz-precios">
                <thead>
                  <tr>
                    <th>Tipo de habitación</th>
                    {matriz.temporadas.map((t) => (
                      <th key={t.id}>
                        <span className="matriz-precios__temporada">
                          <i style={{ background: t.color || COLOR_DEFECTO }} />
                          {t.nombre}
                        </span>
                        <small>{fechaCorta(t.fecha_inicio)} → {fechaCorta(t.fecha_fin)} · ×{Number(t.multiplicador).toFixed(2)}</small>
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {matriz.tipos.map((tipo) => (
                    <tr key={tipo.id}>
                      <th scope="row">
                        <strong>{tipo.nombre}</strong>
                        <small>Base {formatoUSD(tipo.precio_base)}</small>
                      </th>
                      {matriz.temporadas.map((temporada) => {
                        const k = clave(tipo.id, temporada.id)
                        const calculado = tipo.precio_base * temporada.multiplicador
                        const fijo = precios[k] !== undefined && precios[k] !== ''
                        const cambiado = (precios[k] || '') !== (originales[k] || '')
                        const valor = fijo ? Number(precios[k]) : calculado
                        const diferencia = tipo.precio_base ? Math.round(((valor - tipo.precio_base) / tipo.precio_base) * 100) : 0
                        return (
                          <td key={temporada.id} className={`matriz-precios__celda ${fijo ? 'is-fijo' : 'is-calculado'} ${cambiado ? 'is-cambiado' : ''}`}>
                            <div className="matriz-precios__campo">
                              <span>$</span>
                              <input
                                className={errores[k] ? 'is-invalido' : ''}
                                type="number"
                                min="0"
                                step="0.01"
                                aria-label={`${tipo.nombre}, ${temporada.nombre}`}
                                placeholder={calculado.toFixed(2)}
                                value={precios[k] ?? ''}
                                disabled={!puedeEditar}
                                onChange={(e) => setPrecios((p) => ({ ...p, [k]: e.target.value }))}
                              />
                              {fijo && puedeEditar && (
                                <button
                                  type="button"
                                  className="matriz-precios__quitar"
                                  title="Volver al precio calculado"
                                  aria-label="Volver al precio calculado"
                                  onClick={() => setPrecios((p) => ({ ...p, [k]: '' }))}
                                >
                                  <Icono nombre="cerrar" size={12} />
                                </button>
                              )}
                            </div>
                            <small>
                              {fijo ? 'Fijo' : 'Calculado'} · {diferencia > 0 ? `+${diferencia}` : diferencia} % sobre la base
                            </small>
                          </td>
                        )
                      })}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {puedeEditar && (
              <div className={`barra-guardar ${cambios ? 'is-pendiente' : ''}`}>
                <span>{cambios ? `${cambios} ${cambios === 1 ? 'precio cambiado' : 'precios cambiados'} sin guardar` : 'Sin cambios pendientes'}</span>
                <div>
                  {cambios > 0 && (
                    <button type="button" className="btn btn--ghost" onClick={() => { setPrecios(originales); setErrores({}) }}>
                      Descartar
                    </button>
                  )}
                  <button className="btn" disabled={guardando || !cambios}>
                    {guardando ? 'Guardando…' : 'Guardar tarifas'}
                  </button>
                </div>
              </div>
            )}
          </form>
        )}
      </section>
    </div>
  )
}
