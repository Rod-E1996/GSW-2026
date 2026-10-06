import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'

export default function UsuarioForm() {
  const { id } = useParams()
  const editando = Boolean(id)
  const navigate = useNavigate()
  const { can } = useAuth()

  const [rolesCatalogo, setRolesCatalogo] = useState([])
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' })
  const [rolesSel, setRolesSel] = useState([])
  const [loginNotif, setLoginNotif] = useState(0)
  const [errores, setErrores] = useState({})
  const [error, setError] = useState(null)
  const [aviso, setAviso] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)

  useEffect(() => {
    const cargarRoles = api.get('/admin/usuarios/roles').then((res) => setRolesCatalogo(res.data.data))
    const cargarUser = editando
      ? api.get(`/admin/usuarios/${id}`).then((res) => {
          const u = res.data.data
          setForm({ name: u.name, email: u.email, password: '', password_confirmation: '' })
          setRolesSel(u.roles_ids)
          setLoginNotif(u.login_notificacion)
        })
      : Promise.resolve()

    Promise.all([cargarRoles, cargarUser])
      .catch(() => setError('No se pudo cargar la información.'))
      .finally(() => setCargando(false))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  function set(campo, valor) {
    setForm((f) => ({ ...f, [campo]: valor }))
  }

  function toggleRol(rid) {
    setRolesSel((xs) => (xs.includes(rid) ? xs.filter((x) => x !== rid) : [...xs, rid]))
  }

  async function onSubmit(e) {
    e.preventDefault()
    setGuardando(true)
    setErrores({})
    setError(null)
    const payload = { ...form, roles: rolesSel }
    try {
      if (editando) await api.put(`/admin/usuarios/${id}`, payload)
      else await api.post('/admin/usuarios', payload)
      navigate('/panel/usuarios')
    } catch (err) {
      if (err.response?.status === 422) setErrores(err.response.data.errors || {})
      else setError(err.response?.data?.message || 'No se pudo guardar.')
    } finally {
      setGuardando(false)
    }
  }

  async function toggleNotif() {
    try {
      const res = await api.put(`/admin/usuarios/${id}/login-notificacion`)
      setLoginNotif(res.data.data.login_notificacion)
      setAviso('Preferencia de aviso de inicio de sesión actualizada.')
    } catch {
      setError('No se pudo actualizar el aviso de inicio de sesión.')
    }
  }

  if (cargando) return <p className="estado">Cargando…</p>

  return (
    <div className="form-page">
      <h1 className="page-title">{editando ? 'Editar usuario' : 'Nuevo usuario'}</h1>

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

      <form className="form" onSubmit={onSubmit}>
        <label>
          Nombre
          <input value={form.name} onChange={(e) => set('name', e.target.value)} autoFocus />
          {errores.name && <small className="campo-error">{errores.name[0]}</small>}
        </label>

        <label>
          Correo electrónico
          <input type="email" value={form.email} onChange={(e) => set('email', e.target.value)} />
          {errores.email && <small className="campo-error">{errores.email[0]}</small>}
        </label>

        <div className="form-row">
          <label>
            {editando ? 'Nueva contraseña (opcional)' : 'Contraseña'}
            <input
              type="password"
              value={form.password}
              onChange={(e) => set('password', e.target.value)}
              autoComplete="new-password"
              placeholder={editando ? 'Dejar vacío para no cambiarla' : 'Mínimo 8 caracteres'}
            />
            {errores.password && <small className="campo-error">{errores.password[0]}</small>}
          </label>
          <label>
            Confirmar contraseña
            <input
              type="password"
              value={form.password_confirmation}
              onChange={(e) => set('password_confirmation', e.target.value)}
              autoComplete="new-password"
            />
          </label>
        </div>

        <fieldset className="roles-fieldset">
          <legend>Roles</legend>
          <div className="roles-check">
            {rolesCatalogo.map((r) => (
              <label key={r.id} className="roles-check__item">
                <input
                  type="checkbox"
                  checked={rolesSel.includes(r.id)}
                  onChange={() => toggleRol(r.id)}
                />
                {r.name}
              </label>
            ))}
          </div>
          {errores.roles && <small className="campo-error">{errores.roles[0]}</small>}
        </fieldset>

        {editando && can('usuario_login_notificacion') && (
          <div className="switch-row">
            <div>
              <strong>Aviso de nuevos inicios de sesión</strong>
              <p className="switch-row__hint">
                Enviar un correo al usuario cuando inicie sesión desde un dispositivo nuevo.
              </p>
            </div>
            <button
              type="button"
              className={`btn btn--sm ${loginNotif === 1 ? '' : 'btn--ghost'}`}
              onClick={toggleNotif}
            >
              {loginNotif === 1 ? 'Activado' : 'Desactivado'}
            </button>
          </div>
        )}

        <div className="form-acciones">
          <button type="button" className="btn btn--ghost" onClick={() => navigate('/panel/usuarios')}>
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
