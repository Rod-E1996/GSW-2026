import BarraPublica from '../../components/BarraPublica'

// Aviso de privacidad (Ley para la Proteccion de Datos Personales, D. L. 144).
// El hotel debe revisarlo y completar sus datos de contacto antes de publicar el portal.
export default function Privacidad() {
  return (
    <div className="app">
      <BarraPublica />
      <main className="pagina-simple">
        <article className="tarjeta-simple aviso">
          <h1>Aviso de privacidad</h1>

          <h2>Qué datos recopilamos</h2>
          <p>
            Al crear tu cuenta y reservar te pedimos nombres, apellidos, correo electrónico, teléfono y
            documento de identidad. También guardamos las fechas, servicios e importes de tus reservas y los
            datos técnicos de tus inicios de sesión (dirección IP, navegador y dispositivo).
          </p>

          <h2>Para qué los usamos</h2>
          <p>
            Solo para gestionar tus reservas: confirmarlas, enviarte comprobantes y avisos, registrar tu entrada
            y salida, atender cancelaciones y proteger tu cuenta. No vendemos ni cedemos tus datos a terceros
            con fines comerciales.
          </p>

          <h2>Cuánto tiempo los conservamos</h2>
          <p>
            Mientras tu cuenta esté activa y durante el tiempo que exijan las obligaciones legales y fiscales
            del hotel.
          </p>

          <h2>Tus derechos</h2>
          <p>
            Puedes solicitar el acceso, la rectificación, la cancelación, la oposición, la limitación del
            tratamiento y la portabilidad de tus datos. Puedes corregir tus datos de contacto en Mi cuenta; para
            el resto de solicitudes, escríbenos o acércate a la recepción del hotel.
          </p>

          <h2>Consentimiento</h2>
          <p>
            Al marcar la casilla de aceptación al registrarte autorizas este tratamiento. Guardamos la fecha en
            que lo aceptaste.
          </p>
        </article>
      </main>
    </div>
  )
}
