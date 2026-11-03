import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import { fechaCorta, formatoUSD } from '../../../utils'

const clave = (tipoId, temporadaId) => `${tipoId}-${temporadaId}`

export default function TarifasPage() {
  const { can } = useAuth()
  const [temporadas, setTemporadas] = useState([])
  const [meta, setMeta] = useState(null)
  const [page, setPage] = useState(1)
  const [matriz, setMatriz] = useState({ tipos: [], temporadas: [] })
  const [precios, setPrecios] = useState({})
  const [errores, setErrores] = useState({})
  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)
  const [mensaje, setMensaje] = useState(null)
  const [error, setError] = useState(null)

  const puedeEditar = can('tarifa_update')

  function aplicarMatriz(datos) {
    setMatriz(datos)
    setPrecios(
      Object.fromEntries(
        datos.tarifas.map((t) => [clave(t.tipo_habitacion_id, t.temporada_id), String(Number(t.precio_noche))]),
      ),
    )
  }

  function cargarTemporadas() {
    return api.get('/admin/temporadas', { params: { page } }).then((res) => {
      setTemporadas(res.data.data)
      setMeta(res.data.meta)
    })
  }

  function cargarTodo() {
    setCargando(true)
    Promise.all([cargarTemporadas(), api.get('/admin/tarifas').then((res) => aplicarMatriz(res.data))])
      .catch(() => setError('No se pudieron cargar las temporadas y tarifas.'))
      .finally(() => setCargando(false))
  }

  useEffect(() => {
    cargarTodo()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page])

  async function eliminarTemporada(t) {
    if (!confirm(`¿Eliminar la temporada "${t.nombre}"?`)) return
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

  if (cargando) return <p className="estado">Cargando…</p>

  return (
    <div>
      <h1 className="page-title">Temporadas y tarifas</h1>
      <p className="page-sub">Define los períodos del año y el precio por noche de cada tipo de habitación.</p>

      {mensaje && <div className="alerta alerta--ok">{mensaje}</div>}
      {error && <div className="alerta alerta--error">{error}</div>}

      <div className="page-head">
        <h2 className="seccion-title">Temporadas</h2>
        {can('temporada_store') && (
          <Link to="/panel/tarifas/temporadas/nueva" className="btn">
            + Nueva temporada
          </Link>
        )}
      </div>

      <div className="tabla-wrap">
        <table className="tabla">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Inicio</th>
              <th>Fin</th>
              <th>Multiplicador</th>
              <th>Prioridad</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {temporadas.length === 0 && (
              <tr><td colSpan="6" className="tabla__vacio">Sin temporadas.</td></tr>
            )}
            {temporadas.map((t) => (
              <tr key={t.id}>
                <td>
                  <span className="muestra-color" style={{ background: t.color || 'transparent' }} />
                  {t.nombre}
                </td>
                <td>{fechaCorta(t.fecha_inicio)}</td>
                <td>{fechaCorta(t.fecha_fin)}</td>
                <td>× {t.multiplicador.toFixed(2)}</td>
                <td>{t.prioridad ?? 0}</td>
                <td className="tabla__acciones">
                  {can('temporada_update') && (
                    <Link to={`/panel/tarifas/temporadas/${t.id}/editar`} className="btn btn--sm btn--ghost">
                      Editar
                    </Link>
                  )}
                  {can('temporada_destroy') && (
                    <button className="btn btn--sm btn--danger" onClick={() => eliminarTemporada(t)}>
                      Eliminar
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

      <h2 className="seccion-title">Precio por noche</h2>
      <p className="page-sub">
        Una casilla vacía usa el precio base por el multiplicador de la temporada, que aparece como referencia.
        Solo se muestran las temporadas que aún no terminan.
      </p>

      {matriz.temporadas.length === 0 || matriz.tipos.length === 0 ? (
        <p className="estado">Crea al menos un tipo de habitación y una temporada vigente para definir precios.</p>
      ) : (
        <form onSubmit={guardarTarifas}>
          <div className="tabla-wrap">
            <table className="tabla tabla--matriz">
              <thead>
                <tr>
                  <th>Tipo</th>
                  <th>Base</th>
                  {matriz.temporadas.map((t) => (
                    <th key={t.id}>{t.nombre}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {matriz.tipos.map((tipo) => (
                  <tr key={tipo.id}>
                    <td>{tipo.nombre}</td>
                    <td>{formatoUSD(tipo.precio_base)}</td>
                    {matriz.temporadas.map((temporada) => {
                      const k = clave(tipo.id, temporada.id)
                      return (
                        <td key={temporada.id}>
                          <input
                            className={errores[k] ? 'is-invalido' : ''}
                            type="number"
                            min="0"
                            step="0.01"
                            aria-label={`${tipo.nombre}, ${temporada.nombre}`}
                            placeholder={(tipo.precio_base * temporada.multiplicador).toFixed(2)}
                            value={precios[k] ?? ''}
                            disabled={!puedeEditar}
                            onChange={(e) => setPrecios((p) => ({ ...p, [k]: e.target.value }))}
                          />
                        </td>
                      )
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {puedeEditar && (
            <div className="form-acciones">
              <button className="btn" disabled={guardando}>
                {guardando ? 'Guardando…' : 'Guardar tarifas'}
              </button>
            </div>
          )}
        </form>
      )}
    </div>
  )
}
