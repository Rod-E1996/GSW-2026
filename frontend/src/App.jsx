import { Routes, Route } from 'react-router-dom'
import Portal from './pages/Portal'
import Resultados from './pages/publico/Resultados'
import Login from './pages/Login'
import Register from './pages/Register'
import ForgotPassword from './pages/ForgotPassword'
import ResetPassword from './pages/ResetPassword'
import ProtectedRoute from './auth/ProtectedRoute'
import AdminLayout from './layouts/AdminLayout'
import Dashboard from './pages/admin/Dashboard'
import EnConstruccion from './pages/admin/EnConstruccion'
import TiposList from './pages/admin/tipos/TiposList'
import TipoForm from './pages/admin/tipos/TipoForm'
import AuditoriaList from './pages/admin/auditoria/AuditoriaList'
import AuditoriaDetalle from './pages/admin/auditoria/AuditoriaDetalle'
import ErroresList from './pages/admin/errores/ErroresList'
import ErrorDetalle from './pages/admin/errores/ErrorDetalle'
import RolesList from './pages/admin/roles/RolesList'
import RoleForm from './pages/admin/roles/RoleForm'
import RolePermisos from './pages/admin/roles/RolePermisos'
import PermisosList from './pages/admin/permisos/PermisosList'
import UsuariosList from './pages/admin/usuarios/UsuariosList'
import UsuarioForm from './pages/admin/usuarios/UsuarioForm'
import UsuarioSesiones from './pages/admin/usuarios/UsuarioSesiones'
import SesionesList from './pages/admin/sesiones/SesionesList'
import ConfiguracionForm from './pages/admin/configuracion/ConfiguracionForm'
import HabitacionesList from './pages/admin/habitaciones/HabitacionesList'
import HabitacionForm from './pages/admin/habitaciones/HabitacionForm'
import BloqueosList from './pages/admin/habitaciones/BloqueosList'
import TarifasPage from './pages/admin/tarifas/TarifasPage'
import TemporadaForm from './pages/admin/tarifas/TemporadaForm'
import ServiciosList from './pages/admin/servicios/ServiciosList'
import ServicioForm from './pages/admin/servicios/ServicioForm'
import CategoriasList from './pages/admin/servicios/CategoriasList'
import Perfil from './pages/admin/perfil/Perfil'

export default function App() {
  return (
    <Routes>
      {/* Portal publico */}
      <Route path="/" element={<Portal />} />
      <Route path="/disponibilidad" element={<Resultados />} />
      <Route path="/login" element={<Login />} />
      <Route path="/registro" element={<Register />} />
      <Route path="/recuperar-contrasena" element={<ForgotPassword />} />
      <Route path="/restablecer-contrasena" element={<ResetPassword />} />

      {/* Panel protegido */}
      <Route
        path="/panel"
        element={
          <ProtectedRoute soloStaff>
            <AdminLayout />
          </ProtectedRoute>
        }
      >
        <Route index element={<Dashboard />} />
        <Route path="reservas" element={<EnConstruccion titulo="Reservas" />} />
        <Route path="tipos-habitacion" element={<TiposList />} />
        <Route path="tipos-habitacion/nuevo" element={<TipoForm />} />
        <Route path="tipos-habitacion/:id/editar" element={<TipoForm />} />
        <Route path="habitaciones" element={<HabitacionesList />} />
        <Route path="habitaciones/nueva" element={<HabitacionForm />} />
        <Route path="habitaciones/:id/editar" element={<HabitacionForm />} />
        <Route path="bloqueos" element={<BloqueosList />} />
        <Route path="tarifas" element={<TarifasPage />} />
        <Route path="tarifas/temporadas/nueva" element={<TemporadaForm />} />
        <Route path="tarifas/temporadas/:id/editar" element={<TemporadaForm />} />
        <Route path="servicios" element={<ServiciosList />} />
        <Route path="servicios/nuevo" element={<ServicioForm />} />
        <Route path="servicios/categorias" element={<CategoriasList />} />
        <Route path="servicios/:id/editar" element={<ServicioForm />} />
        <Route path="usuarios" element={<UsuariosList />} />
        <Route path="usuarios/nuevo" element={<UsuarioForm />} />
        <Route path="usuarios/:id/editar" element={<UsuarioForm />} />
        <Route path="usuarios/:id/sesiones" element={<UsuarioSesiones />} />
        <Route path="sesiones" element={<SesionesList />} />
        <Route path="roles" element={<RolesList />} />
        <Route path="roles/nuevo" element={<RoleForm />} />
        <Route path="roles/:id/editar" element={<RoleForm />} />
        <Route path="roles/:id/permisos" element={<RolePermisos />} />
        <Route path="permisos" element={<PermisosList />} />
        <Route path="auditoria" element={<AuditoriaList />} />
        <Route path="auditoria/:id" element={<AuditoriaDetalle />} />
        <Route path="error-logs" element={<ErroresList />} />
        <Route path="error-logs/:id" element={<ErrorDetalle />} />
        <Route path="configuracion" element={<ConfiguracionForm />} />
        <Route path="perfil" element={<Perfil />} />
      </Route>

      {/* Cualquier otra ruta vuelve al portal */}
      <Route path="*" element={<Portal />} />
    </Routes>
  )
}
