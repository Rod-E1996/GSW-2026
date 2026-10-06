// Pantalla temporal para los modulos que todavia no se han migrado a React.
export default function EnConstruccion({ titulo }) {
  return (
    <div>
      <h1 className="page-title">{titulo}</h1>
      <div className="construccion">
        <span className="construccion__icon">🚧</span>
        <p>Este módulo se está migrando desde el panel anterior.</p>
      </div>
    </div>
  )
}
