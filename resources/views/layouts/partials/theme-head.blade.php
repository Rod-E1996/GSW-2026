{{--
    TEMA — bloque del <head>

    Debe ir lo MAS ARRIBA POSIBLE del <head> y siempre ANTES de
    cualquier <link rel="stylesheet">. El script inline aplica el
    atributo data-theme antes del primer render, de modo que el
    navegador nunca llega a pintar el tema equivocado (sin FOUC).

    Es deliberadamente sincrono y sin dependencias: no puede
    esperar a jQuery ni a un archivo externo.
--}}

<meta name="color-scheme" content="light dark">

<script>
    (function () {
        try {
            var saved = localStorage.getItem('theme');

            // Si el usuario ya eligio, manda su eleccion.
            // Si no, se sigue la preferencia del sistema.
            var dark = saved === 'dark' || (
                saved !== 'light' &&
                window.matchMedia &&
                window.matchMedia('(prefers-color-scheme: dark)').matches
            );

            document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
        } catch (e) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
</script>

{{-- Tokens: se cargan antes que el resto de hojas para que
     cualquier archivo posterior pueda consumirlos. --}}
<link href="{{ asset('css/theme-tokens.css') }}" rel="stylesheet">
