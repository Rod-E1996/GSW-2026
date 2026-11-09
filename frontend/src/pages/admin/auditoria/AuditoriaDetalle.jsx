import { useEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../../../api'
import Icono from '../../../components/Icono'
import { Avatar, EstadoVacio } from '../../../components/ui'
import { COLOR_ACCION, fechaHora, nombreEntidad } from '../accesos/comun'
import '../../../styles/admin-accesos.css'

// Campos que cambian siempre y no aportan al revisar un cambio
const RUIDO = ['updated_at']

function texto(valor) {
  if (valor === undefined) return null
  if (valor === null) return 'null'
  if (typeof valor === 'object') return JSON.stringify(valor)
  return String(valor)
}

export default function AuditoriaDetalle() {
  const { id } = useParams()
  const [reg, setReg] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(null)
  const [vista, setVista] = useState('cambios')

  useEffect(() => {
    api
      .get(`/admin/auditoria/${id}`)
      .then((res) => setReg(res.data.data))
      .catch(() => setError('No se pudo cargar el registro.'))
      .finally(() => setCargando(false))
  }, [id])

  // Una fila por campo, con su valor antes y despues y si cambio
  const campos = useMemo(() => {
    if (!reg) return []
    const antes = reg.antes && typeof reg.antes === 'object' ? reg.antes : {}
    const despues = reg.despues && typeof reg.despues === 'object' ? reg.despues : {}
    const claves = [...new Set([...Object.keys(antes), ...Object.keys(despues)])]
    return claves.map((clave) => {
      const a = texto(antes[clave])
      const d = texto(despues[clave])
      const hayAmbos = reg.antes && reg.despues
      return { clave, antes: a, despues: d, cambio: hayAmbos ? a !== d : true }
    })
  }, [reg])

  if (cargando) return <div className="tarjeta-simple"><span className="esqueleto" /><br /><span className="esqueleto" /></div>
  if (error) return <div className="alerta alerta--error">{error}</div>

  const comparable = Boolean(reg.antes && reg.despues)
  const cambiados = campos.filter((c) => c.cambio && !RUIDO.includes(c.clave))
  const filas = vista === 'cambios' && comparable ? cambiados : campos
  const accion = reg.accion ? reg.accion.charAt(0).toUpperCase() + reg.accion.slice(1) : '—'

  return (
    <div>
      <Link to="/panel/auditoria" className="ac-volver"><Icono nombre="volver" size={15} /> Auditoría</Link>

      <div className="ac-cabecera">
        <span className="ac-cabecera__icono"><Icono nombre="auditoria" size={22} /></span>
        <div className="ac-cabecera__datos">
          <h1 className="page-title">
            {accion} en {nombreEntidad(reg.entidad)}
            {reg.nombre_modificado && <span className="ac-cabecera__sub"> · {reg.nombre_modificado}</span>}
          </h1>
          <p>
            <span className={`badge ${COLOR_ACCION[reg.accion] || ''}`}>{accion}</span>
            {fechaHora(reg.fecha)}
          </p>
        </div>
      </div>

      <div className="ac-ficha">
        <div>
          <span>Usuario</span>
          {reg.usuario ? (
            <strong className="celda-entidad"><Avatar nombre={reg.usuario} size="sm" />{reg.usuario}</strong>
          ) : (
            <strong>Sistema (sin sesión)</strong>
          )}
        </div>
        <div>
          <span>Entidad</span>
          <strong>{nombreEntidad(reg.entidad)} <code className="ac-codigo">{reg.entidad}</code></strong>
        </div>
        <div>
          <span>Campos {comparable ? 'modificados' : 'registrados'}</span>
          <strong>{comparable ? cambiados.length : campos.length}</strong>
        </div>
        <div>
          <span>Registro n.º</span>
          <strong className="ac-mono">{reg.id}</strong>
        </div>
      </div>

      <section className="tarjeta-simple">
        <div className="ac-titulo-barra">
          <h3 className="ac-titulo">{comparable ? 'Qué cambió' : reg.antes ? 'Datos eliminados' : 'Datos registrados'}</h3>
          <div className="pestanas">
            {comparable && (
              <button type="button" className={vista === 'cambios' ? 'is-activa' : ''} onClick={() => setVista('cambios')}>
                Solo cambios <span className="pestanas__conteo">{cambiados.length}</span>
              </button>
            )}
            <button type="button" className={vista === 'todo' || (!comparable && vista !== 'json') ? 'is-activa' : ''} onClick={() => setVista('todo')}>
              Todos los campos <span className="pestanas__conteo">{campos.length}</span>
            </button>
            <button type="button" className={vista === 'json' ? 'is-activa' : ''} onClick={() => setVista('json')}>JSON</button>
          </div>
        </div>

        {vista === 'json' ? (
          <div className="comparar">
            <div className="comparar__col">
              <h4 className="ac-subtitulo">Antes</h4>
              {reg.antes ? <pre className="json-box">{JSON.stringify(reg.antes, null, 2)}</pre> : <p className="form-nota">Sin datos previos.</p>}
            </div>
            <div className="comparar__col">
              <h4 className="ac-subtitulo">Después</h4>
              {reg.despues ? <pre className="json-box">{JSON.stringify(reg.despues, null, 2)}</pre> : <p className="form-nota">Sin datos posteriores.</p>}
            </div>
          </div>
        ) : filas.length === 0 ? (
          <EstadoVacio icono="check" titulo="Sin cambios en los datos" texto="Solo se actualizó la fecha de modificación." />
        ) : (
          <div className="tabla-wrap ac-tabla-diff">
            <table className="tabla">
              <thead>
                <tr>
                  <th>Campo</th>
                  {reg.antes && <th>Antes</th>}
                  {reg.despues && <th>Después</th>}
                </tr>
              </thead>
              <tbody>
                {filas.map((c) => (
                  <tr key={c.clave} className={comparable && c.cambio ? 'is-cambiado' : ''}>
                    <td><code className="ac-codigo">{c.clave}</code></td>
                    {reg.antes && <td className={`ac-valor ${comparable && c.cambio ? 'ac-valor--antes' : ''}`}>{c.antes ?? <span className="form-nota">—</span>}</td>}
                    {reg.despues && <td className={`ac-valor ${comparable && c.cambio ? 'ac-valor--despues' : ''}`}>{c.despues ?? <span className="form-nota">—</span>}</td>}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  )
}
