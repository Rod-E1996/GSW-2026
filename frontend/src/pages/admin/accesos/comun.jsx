import Icono from '../../../components/Icono'

// Piezas compartidas por las pantallas de accesos y sistema
// (usuarios, sesiones, roles, permisos, auditoria, errores, configuracion y perfil).

export function fechaHora(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('es-SV', { dateStyle: 'medium', timeStyle: 'short' })
}

// "hace 5 min", "hace 3 h", "hace 2 días"; para fechas viejas, la fecha corta
export function haceCuanto(iso) {
  if (!iso) return '—'
  const segundos = Math.round((Date.now() - new Date(iso).getTime()) / 1000)
  if (segundos < 60) return 'hace un momento'
  const minutos = Math.round(segundos / 60)
  if (minutos < 60) return `hace ${minutos} min`
  const horas = Math.round(minutos / 60)
  if (horas < 24) return `hace ${horas} h`
  const dias = Math.round(horas / 24)
  if (dias < 7) return `hace ${dias} día${dias === 1 ? '' : 's'}`
  return new Date(iso).toLocaleDateString('es-SV', { dateStyle: 'medium' })
}

export function Paginacion({ meta, page, setPage, nombre = 'registros' }) {
  if (!meta || meta.last_page <= 1) {
    return meta?.total ? <p className="ac-total">{meta.total} {nombre}</p> : null
  }
  return (
    <div className="paginacion">
      <span>{meta.total} {nombre} · página {meta.current_page} de {meta.last_page}</span>
      <div className="ac-paginas">
        <button className="btn btn--ghost btn--sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
          <Icono nombre="volver" size={14} /> Anterior
        </button>
        <button className="btn btn--ghost btn--sm" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>
          Siguiente <Icono nombre="flecha" size={14} />
        </button>
      </div>
    </div>
  )
}

// Celda de dispositivo de una sesion: icono segun el tipo, navegador y sistema
export function Dispositivo({ s }) {
  const movil = /m[oó]vil|tel[eé]fono|tablet/i.test(s.device_type_es || '')
  return (
    <div className="celda-entidad">
      <span className="ac-dispositivo"><Icono nombre={movil ? 'telefono' : 'sesiones'} size={16} /></span>
      <div>
        <strong>{s.browser || 'Navegador desconocido'}</strong>
        <small>{[s.device_type_es, s.platform].filter((x) => x && x !== 'Desconocido').join(' · ') || 'Dispositivo desconocido'}</small>
      </div>
    </div>
  )
}

// ---------------------------------------------------------------
// Permisos agrupados por modulo
// ---------------------------------------------------------------

// Prefijo del permiso -> nombre del modulo. El prefijo mas largo que coincida gana.
const MODULOS = {
  dashboard: 'Panel de control',
  reserva: 'Reservas',
  comprobante: 'Comprobantes',
  huesped: 'Huéspedes',
  tipo_habitacion: 'Tipos de habitación',
  habitacion: 'Habitaciones',
  bloqueo: 'Bloqueos',
  temporada: 'Temporadas',
  tarifa: 'Tarifas',
  categoria_servicio: 'Categorías de servicio',
  servicio: 'Servicios',
  usuario: 'Usuarios',
  session: 'Sesiones',
  role: 'Roles',
  permiso: 'Permisos',
  perfil: 'Mi perfil',
  auditar: 'Auditoría',
  error_log: 'Error logs',
  queue_control: 'Procesos en segundo plano',
  configuracion: 'Configuración',
  valoracion: 'Valoraciones',
  reporte: 'Reportes',
  respaldo: 'Respaldos',
  ejemplo: 'Ejemplo (plantilla)',
}

const ACCIONES = {
  index: 'Ver listado',
  show: 'Ver detalle',
  create: 'Abrir formulario de alta',
  store: 'Crear',
  edit: 'Abrir formulario de edición',
  update: 'Editar',
  destroy: 'Eliminar',
  estado: 'Activar o desactivar',
  move: 'Gestionar',
  move_permiso: 'Asignar permisos',
  confirmar: 'Confirmar',
  cancelar: 'Cancelar',
  checkin: 'Registrar entrada',
  checkout: 'Registrar salida',
  solicitar: 'Reservar en línea',
  verificar: 'Verificar',
  login_notificacion: 'Aviso de inicio de sesión',
  sessiones: 'Ver sesiones del usuario',
  cerrar_session: 'Cerrar una sesión',
  cerrar_todas_sessiones: 'Cerrar todas las sesiones',
  cerrar: 'Cerrar sesiones',
  edit_password: 'Cambiar contraseña',
  update_porcentaje: 'Actualizar progreso',
  moderar: 'Moderar',
  exportar: 'Exportar',
}

const PREFIJOS = Object.keys(MODULOS).sort((a, b) => b.length - a.length)

export function moduloDe(permiso) {
  const prefijo = PREFIJOS.find((p) => permiso === p || permiso.startsWith(p + '_'))
  if (prefijo) return { clave: prefijo, nombre: MODULOS[prefijo] }
  const clave = permiso.split('_')[0]
  return { clave, nombre: clave.charAt(0).toUpperCase() + clave.slice(1) }
}

export function accionDe(permiso) {
  const { clave } = moduloDe(permiso)
  const resto = permiso === clave ? '' : permiso.slice(clave.length + 1)
  if (!resto) return 'Acceso'
  if (ACCIONES[resto]) return ACCIONES[resto]
  const texto = resto.replace(/_/g, ' ')
  return texto.charAt(0).toUpperCase() + texto.slice(1)
}

// [{ clave, nombre, permisos: [...] }] ordenado por nombre de modulo
export function agruparPorModulo(lista, nombreDe = (x) => x) {
  const grupos = new Map()
  lista.forEach((item) => {
    const m = moduloDe(nombreDe(item))
    if (!grupos.has(m.clave)) grupos.set(m.clave, { ...m, permisos: [] })
    grupos.get(m.clave).permisos.push(item)
  })
  return [...grupos.values()].sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'))
}

// ---------------------------------------------------------------
// Auditoria
// ---------------------------------------------------------------

export const COLOR_ACCION = {
  agregar: 'badge--ok',
  editar: 'badge--info',
  eliminar: 'badge--danger',
  activar: 'badge--ok',
  desactivar: 'badge--aviso',
  validar: 'badge--ok',
  rechazar: 'badge--danger',
}

const ENTIDADES = {
  users: 'Usuarios',
  usuario: 'Usuarios',
  reservas: 'Reservas',
  huespedes: 'Huéspedes',
  habitaciones: 'Habitaciones',
  tipos_habitacion: 'Tipos de habitación',
  tipo_habitacion_imagenes: 'Fotos de habitación',
  servicios: 'Servicios',
  categorias_servicio: 'Categorías de servicio',
  temporadas: 'Temporadas',
  tarifas: 'Tarifas',
  bloqueos_habitacion: 'Bloqueos',
  configuraciones: 'Configuración',
  pagos: 'Pagos',
  comprobantes: 'Comprobantes',
  sessions_logs: 'Inicios de sesión',
  roles: 'Roles',
  permissions: 'Permisos',
  error_logs: 'Error logs',
}

export function nombreEntidad(tabla) {
  if (!tabla) return '—'
  if (ENTIDADES[tabla]) return ENTIDADES[tabla]
  const texto = tabla.replace(/_/g, ' ')
  return texto.charAt(0).toUpperCase() + texto.slice(1)
}

// "App\Http\Controllers\Api\ReservaController@store" -> "ReservaController@store"
export function controllerCorto(c) {
  if (!c) return '—'
  return c.split('\\').pop()
}
