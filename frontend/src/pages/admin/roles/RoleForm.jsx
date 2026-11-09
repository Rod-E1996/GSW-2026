import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { FormSeccion } from '../../../components/ui'
import '../../../styles/admin-accesos.css'

export default function RoleForm() {
  const { id } = useParams()
  const editando = Boolean(id)
  const navigate = useNavigate()
  const { can } = useAuth()

  const [name, setName] = useState('')
  const [original, setOriginal] = useState('')
  const [errores, setErrores] = useState({})
  const [error, setError] = useState(null)
  const [cargando, setCargando] = useState(editando)
  const [guardando, setGuardando] = useState(false)

  useEffect(() => {
    if (!editando) return
    api
      .get(`/admin/roles/${id}`)
      .then((res) => {
        setName(res.data.data.name)
        setOriginal(res.data.data.name)
      })
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
      if (editando) {
        await api.put(`/admin/roles/${id}`, { name })
        navigate('/panel/roles')
      } else {
        const res = await api.post('/admin/roles', { name })
        // Un rol nuevo no sirve sin permisos: se lleva directo a asignarlos
        const nuevoId = res.data?.data?.id
        navigate(nuevoId && can('role_move_permiso') ? `/panel/roles/${nuevoId}/permisos` : '/panel/roles')
      }
    } catch (err) {
      if (err.response?.status === 422) setErrores(err.response.data.errors || {})
      else setError(err.response?.data?.message || 'No se pudo guardar.')
    } finally {
      setGuardando(false)
    }
  }

  if (cargando) {
    return <div className="form-page"><div className="tarjeta-simple"><span className="esqueleto" /></div></div>
  }

  return (
    <div className="form-page">
      <Link to="/panel/roles" className="ac-volver"><Icono nombre="volver" size={15} /> Roles</Link>
      <div className="page-head">
        <div>
          <h1 className="page-title">{editando ? `Renombrar «${original}»` : 'Nuevo rol'}</h1>
          <p className="page-sub">
            {editando ? 'El cambio de nombre no afecta a los permisos ni a los usuarios del rol.' : 'Después de crearlo podrás elegir sus permisos.'}
          </p>
        </div>
        {editando && can('role_move_permiso') && (
          <Link to={`/panel/roles/${id}/permisos`} className="btn btn--ghost"><Icono nombre="permisos" size={15} /> Editar permisos</Link>
        )}
      </div>

      {error && <div className="alerta alerta--error">{error}</div>}

      <form className="form" onSubmit={onSubmit}>
        <FormSeccion titulo="Nombre del rol" descripcion="Usa un nombre que describa el puesto, por ejemplo «Gerencia» o «Contabilidad».">
          <label>
            Nombre
            <input value={name} onChange={(e) => setName(e.target.value)} autoFocus maxLength={191} />
            {errores.name && <small className="campo-error">{errores.name[0]}</small>}
          </label>
        </FormSeccion>

        <div className="form-acciones">
          <button type="button" className="btn btn--ghost" onClick={() => navigate('/panel/roles')}>
            Cancelar
          </button>
          <button className="btn" disabled={guardando || !name.trim()}>
            {guardando ? 'Guardando…' : editando ? 'Guardar nombre' : 'Crear y elegir permisos'}
          </button>
        </div>
      </form>
    </div>
  )
}
