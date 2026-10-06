import { useAuth } from '../../auth/AuthContext'

export default function Dashboard() {
  const { user } = useAuth()

  return (
    <div>
      <h1 className="page-title">Hola, {user?.name} 👋</h1>
      <p className="page-sub">Bienvenido al panel de HotelLink.</p>

      <div className="cards-info">
        <div className="info-card">
          <span className="info-card__label">Tu rol</span>
          <span className="info-card__value">{user?.roles?.join(', ') || '—'}</span>
        </div>
        <div className="info-card">
          <span className="info-card__label">Permisos</span>
          <span className="info-card__value">{user?.permissions?.length ?? 0}</span>
        </div>
        <div className="info-card">
          <span className="info-card__label">Correo</span>
          <span className="info-card__value info-card__value--sm">{user?.email}</span>
        </div>
      </div>

      <p className="page-note">
        Usa el menú de la izquierda para navegar. Los módulos se irán habilitando
        conforme se migran desde el panel anterior.
      </p>
    </div>
  )
}
