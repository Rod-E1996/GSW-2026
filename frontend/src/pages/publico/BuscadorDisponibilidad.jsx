import { useEffect, useState } from 'react'
import { api } from '../../api'
import { hoyEnHotel, sumarDias } from '../../utils'
import { useIdioma } from '../../i18n'

// Buscador del portal: lo usan la pagina de inicio y la pantalla de resultados.
export default function BuscadorDisponibilidad({ inicial = {}, onBuscar }) {
  const { t, campo } = useIdioma()
  const hoy = hoyEnHotel()

  const [form, setForm] = useState({
    llegada: inicial.llegada || '',
    salida: inicial.salida || '',
    huespedes: inicial.huespedes || 2,
    tipo_habitacion_id: inicial.tipo_habitacion_id || '',
  })
  const [tipos, setTipos] = useState([])
  const [error, setError] = useState(null)

  useEffect(() => {
    api.get('/tipos-habitacion').then((res) => setTipos(res.data.data))
  }, [])

  function set(campo, valor) {
    setForm((f) => ({ ...f, [campo]: valor }))
  }

  // Si la llegada alcanza a la salida, la salida pasa al dia siguiente.
  function cambiarLlegada(valor) {
    setForm((f) => ({
      ...f,
      llegada: valor,
      salida: valor && (!f.salida || f.salida <= valor) ? sumarDias(valor, 1) : f.salida,
    }))
  }

  function onSubmit(e) {
    e.preventDefault()
    if (!form.llegada || !form.salida) {
      setError(t('bus.error_fechas'))
      return
    }
    if (form.salida <= form.llegada) {
      setError(t('bus.error_orden'))
      return
    }
    setError(null)
    onBuscar(form)
  }

  return (
    <form className="buscador" onSubmit={onSubmit}>
      <label>
        {t('bus.llegada')}
        <input type="date" min={hoy} value={form.llegada} onChange={(e) => cambiarLlegada(e.target.value)} />
      </label>
      <label>
        {t('bus.salida')}
        <input
          type="date"
          min={form.llegada ? sumarDias(form.llegada, 1) : sumarDias(hoy, 1)}
          value={form.salida}
          onChange={(e) => set('salida', e.target.value)}
        />
      </label>
      <label>
        {t('bus.huespedes')}
        <input
          type="number"
          min="1"
          max="20"
          value={form.huespedes}
          onChange={(e) => set('huespedes', e.target.value)}
        />
      </label>
      <label>
        {t('bus.tipo')}
        <select value={form.tipo_habitacion_id} onChange={(e) => set('tipo_habitacion_id', e.target.value)}>
          <option value="">{t('bus.todos')}</option>
          {tipos.map((tipo) => (
            <option key={tipo.id} value={tipo.id}>{campo(tipo, 'nombre')}</option>
          ))}
        </select>
      </label>
      <button className="btn">{t('bus.buscar')}</button>
      {error && <p className="buscador__error">{error}</p>}
    </form>
  )
}
