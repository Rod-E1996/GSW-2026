// Menu del panel agrupado por secciones. Cada item se muestra solo si el usuario
// tiene el permiso; una seccion sin items visibles no se muestra.
// icon: nombre de un icono de components/Icono.jsx
export const MENU = [
  {
    grupo: 'Operación',
    items: [
      { label: 'Panel de control', path: '/panel', permiso: 'dashboard', icon: 'panel', end: true },
      { label: 'Reservas', path: '/panel/reservas', permiso: 'reserva_index', icon: 'reservas' },
      { label: 'Calendario', path: '/panel/calendario', permiso: 'reserva_index', icon: 'calendario' },
      { label: 'Verificar comprobante', path: '/panel/verificar', permiso: 'comprobante_verificar', icon: 'qr' },
    ],
  },
  {
    grupo: 'Hotel',
    items: [
      { label: 'Tipos de habitación', path: '/panel/tipos-habitacion', permiso: 'tipo_habitacion_index', icon: 'cama' },
      { label: 'Habitaciones', path: '/panel/habitaciones', permiso: 'habitacion_index', icon: 'puerta' },
      { label: 'Tarifas', path: '/panel/tarifas', permiso: 'tarifa_index', icon: 'tarifa' },
      { label: 'Servicios', path: '/panel/servicios', permiso: 'servicio_index', icon: 'servicios' },
    ],
  },
  {
    grupo: 'Accesos',
    items: [
      { label: 'Usuarios', path: '/panel/usuarios', permiso: 'usuario_index', icon: 'usuarios' },
      { label: 'Sesiones', path: '/panel/sesiones', permiso: 'session_index', icon: 'sesiones' },
      { label: 'Roles', path: '/panel/roles', permiso: 'role_index', icon: 'roles' },
      { label: 'Permisos', path: '/panel/permisos', permiso: 'permiso_index', icon: 'permisos' },
    ],
  },
  {
    grupo: 'Sistema',
    items: [
      { label: 'Auditoría', path: '/panel/auditoria', permiso: 'auditar_index', icon: 'auditoria' },
      { label: 'Error logs', path: '/panel/error-logs', permiso: 'error_log_index', icon: 'alerta' },
      { label: 'Configuración', path: '/panel/configuracion', permiso: 'configuracion_index', icon: 'ajustes' },
    ],
  },
]

// Rutas extra que no estan en el menu pero deben marcar una seccion en la barra superior
export const SECCIONES_EXTRA = [
  { path: '/panel/bloqueos', label: 'Habitaciones' },
  { path: '/panel/perfil', label: 'Mi perfil' },
]
