// Convierte la URL absoluta de una imagen a ruta relativa (para el proxy de Vite).
export function rutaImagen(url) {
  if (!url) return null
  try {
    return new URL(url).pathname
  } catch {
    return url
  }
}

// Formatea un numero como USD.
export function formatoUSD(valor) {
  return new Intl.NumberFormat('es-SV', { style: 'currency', currency: 'USD' }).format(valor ?? 0)
}

// Muestra una fecha AAAA-MM-DD sin convertirla de zona horaria (no se corre un dia).
export function fechaCorta(valor) {
  if (!valor) return '—'
  const [anio, mes, dia] = valor.split('-')
  return `${dia}/${mes}/${anio}`
}
