{{--
    TOGGLE DE TEMA — un solo boton que alterna claro / oscuro

    Accesibilidad:
      - <button> real: entra en el orden de tabulacion y responde
        a Enter y Espacio sin JS adicional.
      - aria-label descriptivo que theme.js mantiene al dia segun
        el estado (dice que va a pasar al pulsar, no el estado).
      - aria-pressed refleja si el modo oscuro esta activo, que es
        la semantica correcta de un boton de dos estados.
      - El icono va con aria-hidden porque la informacion ya esta
        en el aria-label; si no, el lector la leeria dos veces.
--}}

<li class="nav-item theme-toggle-item">

    <button type="button"
            class="nav-link nav-icon theme-toggle-btn"
            id="themeToggle{{ $id ?? 'Main' }}"
            data-theme-toggle
            aria-pressed="false"
            aria-label="Activar modo oscuro"
            title="Activar modo oscuro">
        <i class="bi bi-moon-stars-fill" data-theme-icon aria-hidden="true"></i>
    </button>

</li>
