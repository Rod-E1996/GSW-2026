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

// Fecha de hoy en el hotel (AAAA-MM-DD), aunque el visitante este en otra zona horaria.
export function hoyEnHotel() {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/El_Salvador' }).format(new Date())
}

// Suma dias a una fecha AAAA-MM-DD sin que la zona horaria la corra.
export function sumarDias(valor, dias) {
  const [anio, mes, dia] = valor.split('-').map(Number)
  return new Date(Date.UTC(anio, mes - 1, dia + dias)).toISOString().slice(0, 10)
}
