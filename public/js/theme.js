/*==============================================================
 * CONTROL DE TEMA  —  claro / oscuro
 *--------------------------------------------------------------
 * El tema es uno de 'light' o 'dark' y vive en
 * <html data-theme="...">. Se guarda en localStorage bajo la
 * clave 'theme'.
 *
 * Mientras el usuario no haya elegido nada, se respeta
 * prefers-color-scheme del sistema. En cuanto pulsa el boton,
 * su eleccion manda y deja de seguir al sistema.
 *
 * El parpadeo (FOUC) lo evita un script inline en el <head>
 * (ver layouts/partials/theme-head.blade.php) que aplica el
 * atributo antes del primer render. Este archivo solo se ocupa
 * de la interaccion, asi que puede cargarse con defer.
 *============================================================*/

(function (window, document) {
    'use strict';

    var STORAGE_KEY = 'theme';
    var root = document.documentElement;
    var mql = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    /* ---------- Estado ---------- */

    function stored() {
        try {
            var v = window.localStorage.getItem(STORAGE_KEY);
            return (v === 'light' || v === 'dark') ? v : null;
        } catch (e) {
            // Modo privado o almacenamiento bloqueado.
            return null;
        }
    }

    function save(theme) {
        try {
            window.localStorage.setItem(STORAGE_KEY, theme);
        } catch (e) {
            /* sin persistencia, pero el tema funciona en la sesion */
        }
    }

    function systemPrefersDark() {
        return mql ? mql.matches : false;
    }

    /* Tema efectivo: lo guardado si existe, si no lo del sistema. */
    function current() {
        return stored() || (systemPrefersDark() ? 'dark' : 'light');
    }

    /* ---------- Aplicacion ---------- */

    var transitionTimer = null;

    function apply(theme, animate) {
        if (theme !== 'dark') theme = 'light';

        if (animate) {
            // La transicion solo se habilita durante el cambio,
            // para no penalizar la carga ni otras animaciones.
            root.classList.add('theme-transition');
            window.clearTimeout(transitionTimer);
            transitionTimer = window.setTimeout(function () {
                root.classList.remove('theme-transition');
            }, 250);
        }

        root.setAttribute('data-theme', theme);
        syncControls(theme);

        // Permite que widgets de terceros reaccionen sin recargar.
        var evt;
        try {
            evt = new CustomEvent('themechange', { detail: { theme: theme } });
        } catch (e) {
            evt = document.createEvent('CustomEvent');
            evt.initCustomEvent('themechange', false, false, { theme: theme });
        }
        document.dispatchEvent(evt);
    }

    function set(theme, animate) {
        save(theme);
        apply(theme, animate !== false);
    }

    function toggle() {
        set(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark', true);
    }

    /* ---------- Interfaz del boton ---------- */

    function syncControls(theme) {
        var isDark = theme === 'dark';

        // El icono muestra a donde se va al pulsar: luna cuando
        // estas en claro, sol cuando estas en oscuro.
        var icon = isDark ? 'bi-sun-fill' : 'bi-moon-stars-fill';
        var label = isDark ? 'Activar modo claro' : 'Activar modo oscuro';

        var buttons = document.querySelectorAll('[data-theme-toggle]');
        for (var i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            var el = btn.querySelector('[data-theme-icon]');
            if (el) {
                el.className = el.className.replace(/\bbi-[\w-]+\b/g, '').trim();
                el.classList.add('bi', icon);
            }
            btn.setAttribute('aria-label', label);
            btn.setAttribute('title', label);
            btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        }
    }

    function bind() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-theme-toggle]') : null;
            if (!btn) return;
            e.preventDefault();
            toggle();
        });
    }

    /* ---------- Sincronizacion externa ---------- */

    function watchSystem() {
        if (!mql) return;
        var handler = function () {
            // Solo si el usuario todavia no ha elegido manualmente.
            if (!stored()) apply(systemPrefersDark() ? 'dark' : 'light', true);
        };
        if (mql.addEventListener) mql.addEventListener('change', handler);
        else if (mql.addListener) mql.addListener(handler);
    }

    function watchOtherTabs() {
        window.addEventListener('storage', function (e) {
            if (e.key === STORAGE_KEY) apply(current(), true);
        });
    }

    /* ---------- API publica ---------- */

    window.Theme = {
        get: current,
        isDark: function () { return root.getAttribute('data-theme') === 'dark'; },
        set: set,
        toggle: toggle,
        /* Lee un token del tema actual; util para pasar colores a
           librerias JS (SweetAlert, graficas, etc.). */
        token: function (name) {
            return getComputedStyle(root).getPropertyValue('--' + name).trim();
        }
    };

    /* ---------- Arranque ---------- */

    function init() {
        // Sin animacion: el tema ya venia aplicado desde el <head>,
        // esto solo sincroniza el icono y las etiquetas del boton.
        apply(current(), false);
        bind();
        watchSystem();
        watchOtherTabs();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})(window, document);
