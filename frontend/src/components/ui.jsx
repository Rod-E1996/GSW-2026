import Icono from './Icono'

// Piezas de interfaz compartidas por las pantallas del panel.

// Iniciales para avatares: "Mariela Fuentes" -> "MF"
export function iniciales(nombre = '') {
  return String(nombre || '')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0].toUpperCase())
    .join('')
}

export function Avatar({ nombre, size = 'md' }) {
  return <span className={`avatar avatar--${size}`} aria-hidden="true">{iniciales(nombre) || '?'}</span>
}

// Mensaje para listas vacias o busquedas sin resultados. accion: un boton o enlace opcional.
export function EstadoVacio({ icono = 'panel', titulo, texto, accion }) {
  return (
    <div className="estado-vacio">
      <span className="estado-vacio__icono"><Icono nombre={icono} size={22} /></span>
      <strong>{titulo}</strong>
      {texto && <p>{texto}</p>}
      {accion}
    </div>
  )
}

// Fila de tabla que muestra un EstadoVacio ocupando todas las columnas
export function FilaVacia({ columnas, ...props }) {
  return (
    <tr className="tabla__fila-vacia">
      <td colSpan={columnas}><EstadoVacio {...props} /></td>
    </tr>
  )
}

// Filas grises mientras carga una tabla
export function FilasCargando({ columnas, filas = 5 }) {
  return Array.from({ length: filas }, (_, i) => (
    <tr key={i} className="tabla__fila-cargando" aria-hidden="true">
      {Array.from({ length: columnas }, (_, j) => (
        <td key={j}><span className="esqueleto" /></td>
      ))}
    </tr>
  ))
}

// Seccion de formulario en dos columnas: a la izquierda que es, a la derecha los campos
export function FormSeccion({ titulo, descripcion, children }) {
  return (
    <section className="form-seccion">
      <div className="form-seccion__info">
        <h3>{titulo}</h3>
        {descripcion && <p>{descripcion}</p>}
      </div>
      <div className="form-seccion__campos">{children}</div>
    </section>
  )
}

// Indicador pequeno: etiqueta, valor y nota opcional. tono: ola | azul | ok | mango | error
export function Indicador({ icono, etiqueta, valor, nota, tono = 'ola' }) {
  return (
    <div className={`indicador indicador--${tono}`}>
      {icono && <span className="indicador__icono"><Icono nombre={icono} size={18} /></span>}
      <div>
        <span className="indicador__etiqueta">{etiqueta}</span>
        <strong className="indicador__valor">{valor}</strong>
        {nota && <span className="indicador__nota">{nota}</span>}
      </div>
    </div>
  )
}
