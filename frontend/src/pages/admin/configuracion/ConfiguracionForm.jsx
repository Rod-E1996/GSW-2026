import { useEffect, useState } from 'react'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'

const UNIDAD = { porcentaje: '%', horas: 'horas' }

export default function ConfiguracionForm() {
  const { can } = useAuth()
  const [parametros, setParametros] = useState([])
  const [valores, setValores] = useState({})
  const [errores, setErrores] = useState({})
  const [error, setError] = useState(null)
  const [aviso, setAviso] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)

  const puedeEditar = can('configuracion_update')

  function aplicar(lista) {
    setParametros(lista)
    setValores(Object.fromEntries(lista.map((p) => [p.clave, p.valor ?? ''])))
  }

  useEffect(() => {
    api
      .get('/admin/configuraciones')
      .then((res) => aplicar(res.data.data))
      .catch(() => setError('No se pudieron cargar los parámetros.'))
      .finally(() => setCargando(false))
  }, [])

  async function onSubmit(e) {
    e.preventDefault()
    setGuardando(true)
    setErrores({})
    setError(null)
    setAviso(null)
    try {
      const res = await api.put('/admin/configuraciones', { valores })
      aplicar(res.data.data)
      setAviso(res.data.message)
    } catch (err) {
      if (err.response?.status === 422) {
        setErrores(err.response.data.errors || {})
        setError('Revisa los valores marcados. No se guardó ningún cambio.')
      } else {
        setError(err.response?.data?.message || 'No se pudieron guardar los parámetros.')
      }
    } finally {
      setGuardando(false)
    }
  }

  if (cargando) return <p className="estado">Cargando…</p>

  const grupos = [...new Set(parametros.map((p) => p.grupo))]

  return (
    <div className="form-page">
      <h1 className="page-title">Configuración</h1>
      <p className="page-sub">Estos valores se aplican a las reservas nuevas.</p>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

      <form className="form" onSubmit={onSubmit}>
        {grupos.map((grupo) => (
          <fieldset key={grupo} className="form-grupo">
            <legend>{grupo}</legend>
            {parametros
              .filter((p) => p.grupo === grupo)
              .map((p) => (
                <label key={p.clave}>
                  {p.descripcion}
                  <span className="form-unidad">
                    <input
                      type="number"
                      min="0"
                      max={p.tipo === 'porcentaje' ? 100 : 8760}
                      step={p.tipo === 'porcentaje' ? '0.01' : '1'}
                      value={valores[p.clave] ?? ''}
                      disabled={!puedeEditar}
                      onChange={(e) => setValores((v) => ({ ...v, [p.clave]: e.target.value }))}
                    />
                    <span>{UNIDAD[p.tipo]}</span>
                  </span>
                  {errores[`valores.${p.clave}`] && (
                    <small className="form-error">{errores[`valores.${p.clave}`][0]}</small>
                  )}
                </label>
              ))}
          </fieldset>
        ))}

        {puedeEditar && (
          <div className="form-acciones">
            <button className="btn" disabled={guardando}>
              {guardando ? 'Guardando…' : 'Guardar cambios'}
            </button>
          </div>
        )}
      </form>
    </div>
  )
}
