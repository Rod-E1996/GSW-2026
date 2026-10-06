// Menu del panel. Cada item se muestra solo si el usuario tiene el permiso.
// (Los modulos que aun no existen apuntan a una pantalla "en construccion".)
export const MENU = [
  { label: 'Dashboard', path: '/panel', permiso: 'dashboard', icon: '▣', end: true },
  { label: 'Reservas', path: '/panel/reservas', permiso: 'reserva_index', icon: '▤' },
  { label: 'Tipos de habitación', path: '/panel/tipos-habitacion', permiso: 'tipo_habitacion_index', icon: '◫' },
  { label: 'Habitaciones', path: '/panel/habitaciones', permiso: 'habitacion_index', icon: '⌂' },
  { label: 'Servicios', path: '/panel/servicios', permiso: 'servicio_index', icon: '✦' },
  { label: 'Usuarios', path: '/panel/usuarios', permiso: 'usuario_index', icon: '◍' },
  { label: 'Sesiones', path: '/panel/sesiones', permiso: 'session_index', icon: '⚇' },
  { label: 'Roles', path: '/panel/roles', permiso: 'role_index', icon: '◆' },
  { label: 'Permisos', path: '/panel/permisos', permiso: 'permiso_index', icon: '◈' },
  { label: 'Auditoría', path: '/panel/auditoria', permiso: 'auditar_index', icon: '◉' },
  { label: 'Error logs', path: '/panel/error-logs', permiso: 'error_log_index', icon: '⚠' },
  { label: 'Perfil', path: '/panel/perfil', permiso: 'perfil_show', icon: '☺' },
]
