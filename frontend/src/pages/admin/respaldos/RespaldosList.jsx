import { useEffect, useState } from 'react'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { descargar } from '../../../utils'

function tamano(bytes) {
  if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`
  return `${Math.max(1, Math.round(bytes / 1024))} KB`
}

function fechaHora(iso) {
  return iso ? new Date(iso).toLocaleString('es-SV', { dateStyle: 'short', timeStyle: 'short' }) : '—'
}

export default function RespaldosList() {
  const { can } = useAuth()
  const [respaldos, setRespaldos] = useState(null)
  const [generando, setGenerando] = useState(false)
  const [mensaje, setMensaje] = useState(null)

  function cargar() {
    api.get('/admin/respaldos').then((res) => setRespaldos(res.data.data))
      .catch(() => setMensaje({ tipo: 'error', texto: 'No se pudieron cargar los respaldos.' }))
  }

  useEffect(cargar, [])

  async function generar() {
    setGenerando(true)
    setMensaje(null)
    try {
      await api.post('/admin/respaldos')
      setMensaje({ tipo: 'ok', texto: 'Respaldo generado.' })
      cargar()
    } catch (e) {
      setMensaje({ tipo: 'error', texto: e.response?.data?.message || 'No se pudo generar el respaldo.' })
    } finally {
      setGenerando(false)
    }
  }

  return (
    <div>
      <div className="page-head">
        <div>
          <h1 className="page-title">Respaldos</h1>
          <p className="page-sub">Copias completas de la base de datos. También se genera una automática cada día a las 2:00.</p>
        </div>
        {can('respaldo_store') && (
          <div className="page-head__acciones">
            <button type="button" className="btn" onClick={generar} disabled={generando}>
              {generando ? 'Generando…' : <><Icono nombre="mas" size={16} /> Generar respaldo</>}
            </button>
          </div>
        )}
      </div>

      {mensaje && <div className={`alerta alerta--${mensaje.tipo}`}>{mensaje.texto}</div>}

      <div className="tabla-wrap">
        <table className="tabla">
          <thead>
            <tr><th>Fecha</th><th>Archivo</th><th>Tamaño</th><th>Origen</th><th>Generado por</th><th></th></tr>
          </thead>
          <tbody>
            {respaldos === null && <tr><td colSpan="6" className="tabla__vacio">Cargando…</td></tr>}
            {respaldos?.length === 0 && <tr><td colSpan="6" className="tabla__vacio">Todavía no hay respaldos.</td></tr>}
            {respaldos?.map((r) => (
              <tr key={r.id}>
                <td className="celda-sin-corte">{fechaHora(r.fecha)}</td>
                <td className="celda-sin-corte">{r.archivo}</td>
                <td className="celda-sin-corte">{tamano(r.tamano)}</td>
                <td>{r.origen === 'programado' ? 'Automático' : 'Manual'}</td>
                <td>{r.usuario || '—'}</td>
                <td className="tabla__acciones">
                  <button type="button" className="btn btn--ghost btn--sm" onClick={() => descargar(api, `/admin/respaldos/${r.id}/descargar`, r.archivo)}>
                    <Icono nombre="descargar" size={15} /> Descargar
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
