// Tablas responsivas: en pantallas estrechas cada fila de una .tabla se muestra
// como una tarjeta (ver "Tablas apiladas" en index.css). Para que cada dato
// conserve su nombre, aqui se copia el texto de la cabecera de su columna en el
// atributo data-label de cada celda.
//
// Funciona para todas las tablas sin tocar cada pantalla: un MutationObserver
// vuelve a etiquetar cuando React pinta o cambia filas. Una tabla que deba
// mantenerse como tabla (p. ej. una matriz) lleva la clase .tabla--fija.

function etiquetar(tabla) {
  const cabeceras = [...tabla.querySelectorAll('thead th')].map((th) => th.textContent.trim())
  if (!cabeceras.length) return
  for (const fila of tabla.querySelectorAll('tbody tr')) {
    let col = 0
    for (const celda of fila.children) {
      const etiqueta = cabeceras[col] || ''
      if (celda.getAttribute('data-label') !== etiqueta) celda.setAttribute('data-label', etiqueta)
      col += celda.colSpan || 1
    }
  }
}

function etiquetarTodas(raiz = document) {
  raiz.querySelectorAll('table.tabla:not(.tabla--fija)').forEach(etiquetar)
}

export function activarTablasResponsivas() {
  if (typeof window === 'undefined' || !('MutationObserver' in window)) return
  let pendiente = false
  const observador = new MutationObserver(() => {
    if (pendiente) return
    pendiente = true
    requestAnimationFrame(() => {
      pendiente = false
      etiquetarTodas()
    })
  })
  observador.observe(document.body, { childList: true, subtree: true, characterData: true })
  etiquetarTodas()
}
