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

// Abre la pagina de la pasarela. La de pruebas vive en este mismo portal: se navega sin recargar.
export function irAPasarela(url, navigate) {
  try {
    const destino = new URL(url, window.location.origin)
    if (destino.pathname.startsWith('/pasarela-pruebas/')) {
      navigate(destino.pathname)
      return
    }
  } catch {
    // URL invalida: se deja al navegador
  }
  window.location.assign(url)
}

// Descarga un archivo de la API (con el token) y lo guarda con su nombre.
export async function descargar(api, ruta, nombre) {
  const res = await api.get(ruta, { responseType: 'blob' })
  const enlace = document.createElement('a')
  enlace.href = URL.createObjectURL(res.data)
  enlace.download = nombre
  document.body.appendChild(enlace)
  enlace.click()
  enlace.remove()
  setTimeout(() => URL.revokeObjectURL(enlace.href), 1000)
}
