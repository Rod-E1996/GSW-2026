import React from 'react'
import ReactDOM from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
// Los estilos base van ANTES que App: cada pantalla importa su propia hoja
// (styles/*.css) y esas deben cargarse despues para poder ajustar la base.
import './index.css'
import App from './App.jsx'
import { AuthProvider } from './auth/AuthContext'
import { IdiomaProvider } from './i18n'
import { activarTablasResponsivas } from './tablasResponsivas'

activarTablasResponsivas()

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <BrowserRouter>
      <AuthProvider>
        <IdiomaProvider>
          <App />
        </IdiomaProvider>
      </AuthProvider>
    </BrowserRouter>
  </React.StrictMode>,
)
