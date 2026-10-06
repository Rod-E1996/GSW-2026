// Manejo centralizado del token de Sanctum.
// "Recordarme" marcado  -> localStorage (persiste al cerrar el navegador).
// "Recordarme" sin marcar -> sessionStorage (se borra al cerrar la pestana).
// Todo va envuelto en try/catch por si el almacenamiento no esta disponible
// (modo privado, cookies bloqueadas, etc.).

const KEY = 'token'

export function getToken() {
  try {
    return localStorage.getItem(KEY) || sessionStorage.getItem(KEY)
  } catch {
    return null
  }
}

export function setToken(token, recordar = true) {
  try {
    clearToken()
    if (recordar) localStorage.setItem(KEY, token)
    else sessionStorage.setItem(KEY, token)
  } catch {
    // Sin almacenamiento disponible: la sesion vivira solo en memoria.
  }
}

export function clearToken() {
  try {
    localStorage.removeItem(KEY)
    sessionStorage.removeItem(KEY)
  } catch {
    // Nada que limpiar.
  }
}
