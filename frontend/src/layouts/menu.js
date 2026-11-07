// Menu del panel. Cada item se muestra solo si el usuario tiene el permiso.
export const MENU = [
  { label: 'Panel de control', path: '/panel', permiso: 'dashboard', icon: '▣', end: true },
  { label: 'Reservas', path: '/panel/reservas', permiso: 'reserva_index', icon: '▤' },
  { label: 'Calendario', path: '/panel/calendario', permiso: 'reserva_index', icon: '▥' },
  { label: 'Verificar comprobante', path: '/panel/verificar', permiso: 'comprobante_verificar', icon: '▦' },
  { label: 'Tipos de habitación', path: '/panel/tipos-habitacion', permiso: 'tipo_habitacion_index', icon: '◫' },
  { label: 'Habitaciones', path: '/panel/habitaciones', permiso: 'habitacion_index', icon: '⌂' },
  { label: 'Tarifas', path: '/panel/tarifas', permiso: 'tarifa_index', icon: '$' },
  { label: 'Servicios', path: '/panel/servicios', permiso: 'servicio_index', icon: '✦' },
  { label: 'Usuarios', path: '/panel/usuarios', permiso: 'usuario_index', icon: '◍' },
  { label: 'Sesiones', path: '/panel/sesiones', permiso: 'session_index', icon: '⚇' },
  { label: 'Roles', path: '/panel/roles', permiso: 'role_index', icon: '◆' },
  { label: 'Permisos', path: '/panel/permisos', permiso: 'permiso_index', icon: '◈' },
  { label: 'Auditoría', path: '/panel/auditoria', permiso: 'auditar_index', icon: '◉' },
  { label: 'Error logs', path: '/panel/error-logs', permiso: 'error_log_index', icon: '⚠' },
  { label: 'Configuración', path: '/panel/configuracion', permiso: 'configuracion_index', icon: '⚙' },
  { label: 'Perfil', path: '/panel/perfil', permiso: 'perfil_show', icon: '☺' },
]
