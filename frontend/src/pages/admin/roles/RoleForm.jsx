import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'

export default function RoleForm() {
  const { id } = useParams()
  const editando = Boolean(id)
  const navigate = useNavigate()

  const [name, setName] = useState('')
  const [errores, setErrores] = useState({})
  const [error, setError] = useState(null)
  const [cargando, setCargando] = useState(editando)
  const [guardando, setGuardando] = useState(false)

  useEffect(() => {
    if (!editando) return
    api
      .get(`/admin/roles/${id}`)
      .then((res) => setName(res.data.data.name))
      .catch(() => setError('No se pudo cargar el rol.'))
      .finally(() => setCargando(false))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  async function onSubmit(e) {
    e.preventDefault()
    setGuardando(true)
    setErrores({})
    setError(null)
    try {
      if (editando) await api.put(`/admin/roles/${id}`, { name })
      else await api.post('/admin/roles', { name })
      navigate('/panel/roles')
    } catch (err) {
      if (err.response?.status === 422) setErrores(err.response.data.errors || {})
      else setError(err.response?.data?.message || 'No se pudo guardar.')
    } finally {
      setGuardando(false)
    }
  }

  if (cargando) return <p className="estado">Cargando…</p>

  return (
    <div className="form-page">
      <h1 className="page-title">{editando ? 'Editar rol' : 'Nuevo rol'}</h1>

      {error && <div className="alerta alerta--error">{error}</div>}

      <form className="form" onSubmit={onSubmit}>
        <label>
          Nombre
          <input value={name} onChange={(e) => setName(e.target.value)} autoFocus />
          {errores.name && <small className="campo-error">{errores.name[0]}</small>}
        </label>

        <div className="form-acciones">
          <button type="button" className="btn btn--ghost" onClick={() => navigate('/panel/roles')}>
            Cancelar
          </button>
          <button className="btn" disabled={guardando}>
            {guardando ? 'Guardando…' : 'Guardar'}
          </button>
        </div>
      </form>
    </div>
  )
}
