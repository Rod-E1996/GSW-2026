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
