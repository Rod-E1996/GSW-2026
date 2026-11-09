import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../api'
import { useAuth } from '../../auth/AuthContext'
import { fechaCorta, formatoUSD } from '../../utils'
import { COLOR_RESERVA } from './reservas/ReservasList'
import Icono from '../../components/Icono'

function Lista({ titulo, icono, filas, vacio, detalle }) {
  return (
    <section className="tarjeta-simple panel-lista">
      <h3 className="panel-lista__titulo">
        <Icono nombre={icono} size={18} />
        {titulo}
        <span className="panel-lista__conteo">{filas.length}</span>
      </h3>
      {filas.length === 0 ? (
        <p className="vacio-mini">{vacio}</p>
      ) : (
        <ul>
          {filas.map((r) => (
            <li key={r.codigo}>
              <Link to={`/panel/reservas/${r.codigo}`}>
                <strong>{r.codigo}</strong> · {r.titular}
                <span className="form-nota"> · {detalle(r)}</span>
              </Link>
              <span className={`badge ${COLOR_RESERVA[r.estado] || ''}`}>{r.estado_nombre}</span>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}

export default function Dashboard() {
  const { user, can } = useAuth()
  const [panel, setPanel] = useState(null)
  const [error, setError] = useState(null)
  const operativo = can('reserva_index')

  useEffect(() => {
    if (!operativo) return
    api.get('/admin/panel').then((res) => setPanel(res.data)).catch(() => setError('No se pudo cargar el panel de control.'))
  }, [operativo])

  if (!operativo) {
    return (
      <div>
        <h1 className="page-title">Hola, {user?.name}</h1>
        <p className="page-sub">Usa el menú de la izquierda para navegar.</p>
      </div>
    )
  }

  const i = panel?.indicadores
  const habitacion = (r) => `${r.tipo}${r.habitacion ? ` ${r.habitacion}` : ''}`

  return (
    <div>
      <div className="page-head">
        <div>
          <h1 className="page-title">Panel de control</h1>
          <p className="page-sub">Hola, {user?.name}. Así está el hotel hoy.</p>
        </div>
        <div className="page-head__acciones">
          <Link to="/panel/calendario" className="btn btn--ghost">Calendario</Link>
          {can('reserva_store') && <Link to="/panel/reservas/nueva" className="btn">+ Nueva reserva</Link>}
        </div>
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}
      {!panel && !error && <p className="estado">Cargando…</p>}

      {panel && (
        <>
          <div className="cards-info">
            <div className="info-card">
              <span className="info-card__icono"><Icono nombre="ocupacion" size={20} /></span>
              <span className="info-card__label">Ocupación esta noche</span>
              <span className="info-card__value">{i.ocupacion} %</span>
              <span className="form-nota">{i.ocupadas} de {i.habitaciones} habitaciones</span>
            </div>
            <div className="info-card">
              <span className="info-card__icono"><Icono nombre="reservas" size={20} /></span>
              <span className="info-card__label">Reservas activas</span>
              <span className="info-card__value">{i.reservas_activas}</span>
              <span className="form-nota">{i.en_estadia} en estadía · {i.pendientes} pendientes de anticipo</span>
            </div>
            <div className="info-card">
              <span className="info-card__icono"><Icono nombre="entrada" size={20} /></span>
              <span className="info-card__label">Entradas de hoy</span>
              <span className="info-card__value">{i.entradas_registradas} / {i.entradas_hoy}</span>
              <span className="form-nota">registradas · {i.salidas_hoy} salidas por registrar</span>
            </div>
            <div className="info-card">
              <span className="info-card__icono"><Icono nombre="dinero" size={20} /></span>
              <span className="info-card__label">Ingresos de {i.mes}</span>
              <span className="info-card__value">{formatoUSD(i.ingresos_mes)}</span>
              <span className="form-nota">pagos recibidos</span>
            </div>
          </div>

          <div className="panel-grid">
            <Lista icono="entrada" titulo="Entradas de hoy" filas={panel.entradas} vacio="Nadie llega hoy."
              detalle={(r) => `${habitacion(r)}${r.hora_estimada_llegada ? ` · ${r.hora_estimada_llegada}` : ''}`} />
            <Lista icono="salir" titulo="Salidas por registrar" filas={panel.salidas} vacio="No hay salidas pendientes."
              detalle={(r) => `${habitacion(r)} · sale ${fechaCorta(r.fecha_salida)}`} />
            <Lista icono="calendario" titulo="Próximas entradas (7 días)" filas={panel.proximas} vacio="Sin llegadas en la próxima semana."
              detalle={(r) => `${fechaCorta(r.fecha_llegada)} · ${habitacion(r)}`} />
            <Lista icono="reservas" titulo="Reservas recientes" filas={panel.recientes} vacio="Todavía no hay reservas."
              detalle={(r) => `${fechaCorta(r.fecha_llegada)} → ${fechaCorta(r.fecha_salida)} · ${formatoUSD(r.total)}`} />
          </div>
        </>
      )}
    </div>
  )
}
