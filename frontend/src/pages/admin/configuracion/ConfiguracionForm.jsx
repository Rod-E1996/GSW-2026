import { useEffect, useMemo, useState } from 'react'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import { FormSeccion } from '../../../components/ui'
import '../../../styles/admin-accesos.css'

const UNIDAD = { porcentaje: '%', horas: 'horas' }

// Explicacion de cada grupo de parametros (los grupos vienen del servidor)
const AYUDA_GRUPO = {
  Reservas: 'Cuánto se cobra al reservar y cuánto tiempo se aparta la habitación mientras llega el anticipo.',
  'Cancelación': 'Política de devolución del anticipo cuando el huésped cancela o no se presenta.',
}

// "48 horas" -> "2 días"
function equivalente(p, valor) {
  if (p.tipo !== 'horas' || valor === '' || Number(valor) < 24 || Number(valor) % 24 !== 0) return null
  const dias = Number(valor) / 24
  return `= ${dias} ${dias === 1 ? 'día' : 'días'}`
}

export default function ConfiguracionForm() {
  const { can } = useAuth()
  const [parametros, setParametros] = useState([])
  const [valores, setValores] = useState({})
  const [originales, setOriginales] = useState({})
  const [errores, setErrores] = useState({})
  const [error, setError] = useState(null)
  const [aviso, setAviso] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)

  const puedeEditar = can('configuracion_update')

  function aplicar(lista) {
    const v = Object.fromEntries(lista.map((p) => [p.clave, p.valor ?? '']))
    setParametros(lista)
    setValores(v)
    setOriginales(v)
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

  const cambiados = useMemo(
    () => Object.keys(valores).filter((k) => String(valores[k]) !== String(originales[k])),
    [valores, originales]
  )

  if (cargando) {
    return <div className="form-page"><div className="tarjeta-simple"><span className="esqueleto" /><br /><span className="esqueleto" /></div></div>
  }

  const grupos = [...new Set(parametros.map((p) => p.grupo))]

  return (
    <div className="form-page">
      <div className="page-head">
        <div>
          <h1 className="page-title">Configuración</h1>
          <p className="page-sub">Parámetros de negocio del hotel. Se aplican a las reservas nuevas; las existentes conservan sus condiciones.</p>
        </div>
      </div>

      {!puedeEditar && <div className="alerta alerta--info">Puedes consultar estos valores, pero no modificarlos.</div>}
      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

      <form className="form" onSubmit={onSubmit}>
        {grupos.map((grupo) => (
          <FormSeccion key={grupo} titulo={grupo} descripcion={AYUDA_GRUPO[grupo]}>
            {parametros
              .filter((p) => p.grupo === grupo)
              .map((p) => {
                const err = errores[`valores.${p.clave}`]
                const cambio = cambiados.includes(p.clave)
                return (
                  <div key={p.clave} className={`ac-parametro ${cambio ? 'is-cambiado' : ''}`}>
                    <label htmlFor={`p-${p.clave}`} className="ac-parametro__texto">
                      {p.descripcion}
                      {cambio && <small>Antes: {originales[p.clave]} {UNIDAD[p.tipo]}</small>}
                      {err && <small className="campo-error">{err[0]}</small>}
                    </label>
                    <div className="ac-parametro__campo">
                      <div className="ac-unidad">
                        <input
                          id={`p-${p.clave}`}
                          type="number"
                          min="0"
                          max={p.tipo === 'porcentaje' ? 100 : 8760}
                          step={p.tipo === 'porcentaje' ? '0.01' : '1'}
                          value={valores[p.clave] ?? ''}
                          disabled={!puedeEditar}
                          className={err ? 'is-invalido' : ''}
                          onChange={(e) => setValores((v) => ({ ...v, [p.clave]: e.target.value }))}
                        />
                        <span>{UNIDAD[p.tipo]}</span>
                      </div>
                      {equivalente(p, valores[p.clave]) && <small className="form-nota">{equivalente(p, valores[p.clave])}</small>}
                    </div>
                  </div>
                )
              })}
          </FormSeccion>
        ))}

        {puedeEditar && (
          <div className="form-acciones ac-acciones-con-texto">
            <span className="form-nota">
              {cambiados.length ? `${cambiados.length} ${cambiados.length === 1 ? 'valor modificado' : 'valores modificados'} sin guardar.` : 'Sin cambios.'}
            </span>
            {cambiados.length > 0 && (
              <button type="button" className="btn btn--ghost" onClick={() => setValores(originales)}>Descartar</button>
            )}
            <button className="btn" disabled={guardando || cambiados.length === 0}>
              {guardando ? 'Guardando…' : 'Guardar cambios'}
            </button>
          </div>
        )}
      </form>
    </div>
  )
}
