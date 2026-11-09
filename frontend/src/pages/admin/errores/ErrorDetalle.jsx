import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { controllerCorto, fechaHora, haceCuanto } from '../accesos/comun'
import '../../../styles/admin-accesos.css'

function Json({ data }) {
  if (data === null || data === undefined || data === '') return <p className="form-nota">Sin parámetros.</p>
  if (typeof data === 'string') return <pre className="json-box">{data}</pre>
  return <pre className="json-box">{JSON.stringify(data, null, 2)}</pre>
}

export default function ErrorDetalle() {
  const { id } = useParams()
  const { can } = useAuth()
  const [reg, setReg] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(null)
  const [guardando, setGuardando] = useState(false)
  const [copiado, setCopiado] = useState(false)

  useEffect(() => {
    api
      .get(`/admin/error-logs/${id}`)
      .then((res) => setReg(res.data.data))
      .catch(() => setError('No se pudo cargar el registro.'))
      .finally(() => setCargando(false))
  }, [id])

  async function resolver() {
    if (!window.confirm('¿Marcar este error como resuelto?')) return
    setGuardando(true)
    try {
      const res = await api.put(`/admin/error-logs/${id}/estado`)
      setReg(res.data.data)
    } catch {
      setError('No se pudo actualizar el estado.')
    } finally {
      setGuardando(false)
    }
  }

  async function copiar() {
    const parametros = reg.parametros_json ?? reg.parametros
    const contenido = [
      `Error #${reg.id} · ${fechaHora(reg.fecha)}`,
      `Controlador: ${reg.controller || '—'}`,
      `Usuario: ${reg.usuario || 'Sin usuario'}`,
      `Mensaje: ${reg.mensaje || '—'}`,
      `Parámetros: ${typeof parametros === 'string' ? parametros : JSON.stringify(parametros, null, 2)}`,
    ].join('\n')
    try {
      await navigator.clipboard.writeText(contenido)
      setCopiado(true)
      setTimeout(() => setCopiado(false), 2000)
    } catch {
      setError('No se pudo copiar al portapapeles.')
    }
  }

  if (cargando) return <div className="tarjeta-simple"><span className="esqueleto" /><br /><span className="esqueleto" /></div>
  if (error && !reg) return <div className="alerta alerta--error">{error}</div>
  if (!reg) return null

  const resuelto = reg.estado === 1

  return (
    <div>
      <Link to="/panel/error-logs" className="ac-volver"><Icono nombre="volver" size={15} /> Error logs</Link>

      <div className="ac-cabecera">
        <span className={`ac-cabecera__icono ${resuelto ? 'is-ok' : 'is-error'}`}>
          <Icono nombre={resuelto ? 'check' : 'alerta'} size={22} />
        </span>
        <div className="ac-cabecera__datos">
          <h1 className="page-title">Error n.º {reg.id}</h1>
          <p>
            <span className={`badge ${resuelto ? 'badge--ok' : 'badge--danger'}`}>{resuelto ? 'Resuelto' : 'Sin resolver'}</span>
            {haceCuanto(reg.fecha)} · {fechaHora(reg.fecha)}
          </p>
        </div>
        <div className="ac-cabecera__accion">
          <button className="btn btn--ghost" onClick={copiar}>
            <Icono nombre={copiado ? 'check' : 'copiar'} size={15} /> {copiado ? 'Copiado' : 'Copiar detalle'}
          </button>
          {!resuelto && can('error_log_estado') && (
            <button className="btn" onClick={resolver} disabled={guardando}>
              <Icono nombre="check" size={15} /> {guardando ? 'Guardando…' : 'Marcar como resuelto'}
            </button>
          )}
        </div>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}

      <div className="ac-ficha">
        <div>
          <span>Controlador</span>
          <strong className="ac-mono" title={reg.controller}>{controllerCorto(reg.controller)}</strong>
        </div>
        <div>
          <span>Usuario</span>
          <strong>{reg.usuario || 'Sin usuario'}</strong>
        </div>
        <div>
          <span>Fecha</span>
          <strong>{fechaHora(reg.fecha)}</strong>
        </div>
      </div>

      <section className="tarjeta-simple">
        <h3 className="ac-titulo">Mensaje</h3>
        <p className="mensaje-error">{reg.mensaje || '—'}</p>
        {reg.controller && reg.controller !== controllerCorto(reg.controller) && (
          <p className="form-nota ac-mono ac-ruta">{reg.controller}</p>
        )}
      </section>

      <section className="tarjeta-simple">
        <h3 className="ac-titulo">Parámetros de la petición</h3>
        <Json data={reg.parametros_json ?? reg.parametros ?? null} />
      </section>
    </div>
  )
}
