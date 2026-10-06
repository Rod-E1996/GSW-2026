import { Routes, Route } from 'react-router-dom'
import Portal from './pages/Portal'
import Login from './pages/Login'
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

export default function App() {
  return (
    <Routes>
      {/* Portal publico */}
      <Route path="/" element={<Portal />} />
      <Route path="/login" element={<Login />} />
      <Route path="/recuperar-contrasena" element={<ForgotPassword />} />
      <Route path="/restablecer-contrasena" element={<ResetPassword />} />

      {/* Panel protegido */}
      <Route
        path="/panel"
        element={
          <ProtectedRoute>
            <AdminLayout />
          </ProtectedRoute>
        }
      >
        <Route index element={<Dashboard />} />
        <Route path="reservas" element={<EnConstruccion titulo="Reservas" />} />
        <Route path="tipos-habitacion" element={<TiposList />} />
        <Route path="tipos-habitacion/nuevo" element={<TipoForm />} />
        <Route path="tipos-habitacion/:id/editar" element={<TipoForm />} />
        <Route path="habitaciones" element={<EnConstruccion titulo="Habitaciones" />} />
        <Route path="servicios" element={<EnConstruccion titulo="Servicios" />} />
        <Route path="usuarios" element={<EnConstruccion titulo="Usuarios" />} />
        <Route path="roles" element={<EnConstruccion titulo="Roles" />} />
        <Route path="permisos" element={<EnConstruccion titulo="Permisos" />} />
        <Route path="auditoria" element={<AuditoriaList />} />
        <Route path="auditoria/:id" element={<AuditoriaDetalle />} />
        <Route path="error-logs" element={<EnConstruccion titulo="Error logs" />} />
        <Route path="perfil" element={<EnConstruccion titulo="Perfil" />} />
      </Route>

      {/* Cualquier otra ruta vuelve al portal */}
      <Route path="*" element={<Portal />} />
    </Routes>
  )
}
