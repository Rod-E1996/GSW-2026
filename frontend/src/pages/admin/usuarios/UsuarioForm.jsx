import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../../../api'
import { useAuth } from '../../../auth/AuthContext'
import Icono from '../../../components/Icono'
import { Avatar, FormSeccion } from '../../../components/ui'
import '../../../styles/admin-accesos.css'

// Que puede hacer cada rol del hotel, para elegir con criterio
const DESCRIPCION_ROL = {
  'Super Administrador': 'Acceso total: configuración, usuarios, roles y todos los módulos.',
  Recepcionista: 'Operación diaria: reservas, entradas, salidas y consulta de catálogos.',
  'Huésped': 'Portal de reservas: sus datos y sus propias reservas.',
}

export default function UsuarioForm() {
  const { id } = useParams()
  const editando = Boolean(id)
  const navigate = useNavigate()
  const { can } = useAuth()

  const [rolesCatalogo, setRolesCatalogo] = useState([])
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' })
  const [usuario, setUsuario] = useState(null)
  const [rolesSel, setRolesSel] = useState([])
  const [loginNotif, setLoginNotif] = useState(0)
  const [verClave, setVerClave] = useState(false)
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
          setUsuario(u)
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
      if (err.response?.status === 422) {
        setErrores(err.response.data.errors || {})
        setError('Revisa los campos marcados.')
      } else setError(err.response?.data?.message || 'No se pudo guardar.')
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

  if (cargando) {
    return (
      <div className="form-page">
        <div className="tarjeta-simple"><span className="esqueleto" /><br /><span className="esqueleto" /></div>
      </div>
    )
  }

  return (
    <div className="form-page">
      <Link to="/panel/usuarios" className="ac-volver"><Icono nombre="volver" size={15} /> Usuarios</Link>

      {editando && usuario ? (
        <div className="ac-cabecera">
          <Avatar nombre={form.name || usuario.name} size="lg" />
          <div className="ac-cabecera__datos">
            <h1 className="page-title">{usuario.name}</h1>
            <p>
              {usuario.email}
              <span className={`badge ${usuario.estado === 1 ? 'badge--ok' : 'badge--danger'}`}>
                {usuario.estado === 1 ? 'Activo' : 'Inactivo'}
              </span>
            </p>
          </div>
          {can('usuario_sessiones') && (
            <Link to={`/panel/usuarios/${id}/sesiones`} className="btn btn--ghost ac-cabecera__accion">
              <Icono nombre="sesiones" size={16} /> Ver sesiones
            </Link>
          )}
        </div>
      ) : (
        <div className="page-head">
          <div>
            <h1 className="page-title">Nuevo usuario</h1>
            <p className="page-sub">La cuenta queda activa y puede iniciar sesión con el correo y la contraseña que definas.</p>
          </div>
        </div>
      )}

      {error && <div className="alerta alerta--error">{error}</div>}
      {aviso && <div className="alerta alerta--ok">{aviso}</div>}

      <form className="form" onSubmit={onSubmit}>
        <FormSeccion titulo="Datos de la cuenta" descripcion="El correo es el usuario para iniciar sesión y adonde llegan los avisos.">
          <label>
            Nombre completo
            <input value={form.name} onChange={(e) => set('name', e.target.value)} autoFocus={!editando} />
            {errores.name && <small className="campo-error">{errores.name[0]}</small>}
          </label>
          <label>
            Correo electrónico
            <input type="email" value={form.email} onChange={(e) => set('email', e.target.value)} />
            {errores.email && <small className="campo-error">{errores.email[0]}</small>}
          </label>
        </FormSeccion>

        <FormSeccion
          titulo="Contraseña"
          descripcion={editando ? 'Déjala vacía para conservar la actual.' : 'Mínimo 8 caracteres. Compártela con la persona por un medio seguro.'}
        >
          <div className="form-row">
            <label>
              {editando ? 'Nueva contraseña' : 'Contraseña'}
              <input
                type={verClave ? 'text' : 'password'}
                value={form.password}
                onChange={(e) => set('password', e.target.value)}
                autoComplete="new-password"
                placeholder={editando ? 'Sin cambios' : ''}
              />
              {errores.password && <small className="campo-error">{errores.password[0]}</small>}
            </label>
            <label>
              Confirmar contraseña
              <input
                type={verClave ? 'text' : 'password'}
                value={form.password_confirmation}
                onChange={(e) => set('password_confirmation', e.target.value)}
                autoComplete="new-password"
              />
            </label>
          </div>
          <label className="form-check">
            <input type="checkbox" checked={verClave} onChange={(e) => setVerClave(e.target.checked)} />
            Mostrar contraseña
          </label>
        </FormSeccion>

        <FormSeccion titulo="Roles" descripcion="Definen qué módulos ve y qué puede hacer. Puede tener más de uno.">
          <div className="ac-roles">
            {rolesCatalogo.map((r) => (
              <label key={r.id} className={`ac-rol ${rolesSel.includes(r.id) ? 'is-elegido' : ''}`}>
                <input type="checkbox" checked={rolesSel.includes(r.id)} onChange={() => toggleRol(r.id)} />
                <span>
                  <strong>{r.name}</strong>
                  {DESCRIPCION_ROL[r.name] && <small>{DESCRIPCION_ROL[r.name]}</small>}
                </span>
              </label>
            ))}
          </div>
          {errores.roles && <small className="campo-error">{errores.roles[0]}</small>}
        </FormSeccion>

        {editando && can('usuario_login_notificacion') && (
          <FormSeccion titulo="Seguridad" descripcion="Avisos al usuario sobre accesos a su cuenta.">
            <div className="switch-row">
              <div>
                <strong>Aviso de nuevos inicios de sesión</strong>
                <p className="switch-row__hint">Envía un correo cuando la cuenta inicia sesión desde un dispositivo nuevo.</p>
              </div>
              <button
                type="button"
                role="switch"
                aria-checked={loginNotif === 1}
                className={`ac-interruptor ${loginNotif === 1 ? 'is-activo' : ''}`}
                onClick={toggleNotif}
              >
                <span className="ac-interruptor__perilla" />
                <span className="ac-interruptor__texto">{loginNotif === 1 ? 'Activado' : 'Desactivado'}</span>
              </button>
            </div>
          </FormSeccion>
        )}

        <div className="form-acciones">
          <button type="button" className="btn btn--ghost" onClick={() => navigate('/panel/usuarios')}>
            Cancelar
          </button>
          <button className="btn" disabled={guardando}>
            {guardando ? 'Guardando…' : editando ? 'Guardar cambios' : 'Crear usuario'}
          </button>
        </div>
      </form>
    </div>
  )
}
