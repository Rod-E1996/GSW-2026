<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="laravelbase" name="description">
    <meta content="laravelbase" name="keywords">

    {{-- Tema: debe ir antes de cualquier hoja de estilo (evita el FOUC) --}}
    @include('layouts.partials.theme-head')

    @include('layouts.googleAnalytics')

    <title>@yield('title', config('app.name') )</title>

    <!-- Favicon icon -->
    <link rel="shortcut icon" type="image/png" href="{{ asset('NiceAdmin/assets/img/favicon.png')}}">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- jQuery --}}
    <script src="{{ asset('js/jquery.js') }}"></script>

    <!-- Vendor CSS Files -->
    <link href="{{ asset('NiceAdmin/assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('NiceAdmin/assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
    <link href="{{ asset('NiceAdmin/assets/vendor/remixicon/remixicon.css') }}" rel="stylesheet">
    <link href="{{ asset('NiceAdmin/assets/vendor/boxicons/css/boxicons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('NiceAdmin/assets/vendor/quill/quill.snow.css') }}" rel="stylesheet">
    <link href="{{ asset('NiceAdmin/assets/vendor/quill/quill.bubble.css') }}" rel="stylesheet">

    <!-- Template Main CSS File -->
    <link href="{{ asset('NiceAdmin/assets/css/style.css') }}" rel="stylesheet">

    {{-- iconos de fontawesome --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta2/css/all.min.css" />

    {{-- bootstrap-toggle --}}
    <link href="{{ asset('css/bootstrap4-toggle.min.css') }}" rel="stylesheet">
    <script src="{{ asset('js/bootstrap4-toggle.min.js') }}"></script>

    {{-- select2 --}}
    <link href="{{ asset('css/select2.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/select2-bootstrap4.min.css') }}" rel="stylesheet">

    {{-- Alertas Toastr --}}
    <link href="{{ asset('css/toastr.css') }}" rel="stylesheet">
    <script src="{{ asset('js/toastr.min.js') }}"></script>

    {{-- sweetalert2 --}}
    <script type="text/javascript" src="{{ asset('plugins/sweetalert2/sweetalert2.all.min.js') }}"></script>

    {{-- Pure counter vanilla --}}
    <script type="text/javascript" src="{{ asset('NiceAdmin/assets/vendor/purecounter/purecounter_vanilla.js') }}"></script>

    <!-- Custom styles -->
    <link href="{{ asset('css/custom_styles.css') }}" rel="stylesheet">

    <!-- Dual list box -->
    <link href="{{ asset('css/dual-list.css') }}" rel="stylesheet">
    <script src="{{ asset('js/dual-list.js') }}"></script>

    {{-- Correcciones de contraste WCAG AA (aplican a ambos temas) --}}
    <link href="{{ asset('css/a11y-contrast.css') }}" rel="stylesheet">

    {{-- Modo oscuro: va al final para poder pisar al tema y a los plugins --}}
    <link href="{{ asset('css/dark-mode.css') }}" rel="stylesheet">
    <link href="{{ asset('css/print.css') }}" rel="stylesheet" media="print">
    <script src="{{ asset('js/theme.js') }}" defer></script>

</head>
<body>

    <!-- ======= Header ======= -->
    @include('layouts.admin.header')
    <!-- End Header -->

    <!-- ======= Sidebar ======= -->
    @include('layouts.admin.menu')
    <!-- End Sidebar-->

    <main id="main" class="main">

        <!-- ======= Page Title ======= -->
        @include('layouts.admin.titleAndPagination')
        <!-- End Page Title -->

        <section class="section">
            @yield('content')
        </section>

    </main><!-- End main -->

    <!-- ======= Footer ======= -->
    @include('layouts.admin.footer')
    <!-- End Footer -->

    <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

    <!-- Vendor JS Files -->
    <script src="{{ asset('NiceAdmin/assets/vendor/bootstrap/js/bootstrap.bundle.js') }}"></script>
    <script src="{{ asset('NiceAdmin/assets/vendor/php-email-form/validate.js') }}"></script>
    <script src="{{ asset('NiceAdmin/assets/vendor/quill/quill.min.js') }}"></script>
    <script src="{{ asset('NiceAdmin/assets/vendor/tinymce/tinymce.min.js') }}"></script>

    <!-- Template Main JS File -->
    <script src="{{ asset('NiceAdmin/assets/js/main.js') }}"></script>

    {{-- select2 --}}
    <script src="{{ asset('js/select2.min.js') }}"></script>

    {{-- jquery mask --}}
    <script src="{{ asset('js/jquery.mask.min.js') }}"></script>

    <script type="text/javascript">
        //Para el contador de numeros cuando exista
        new PureCounter();

        // alertas

        //Muestra la alerta tipo toastr
        let alerta_top=(mensaje, tipo = 'success', tiempo = '2000')=>{
            toastr.options = {
                "closeButton": true,
                "positionClass": "toast-top-right",
                "preventDuplicates": false,
                "timeOut": tiempo,
                //"progressBar": true,
            }
            toastr[tipo](mensaje);
        }

        //Captura la alerta enviada
        @if( !empty(session()->has('alerta')) )
            $(document).ready(function() {
                alerta_top("{!! session()->get('alerta') !!}","{{ session()->get('tipo') ?? 'success' }}","{{ session()->has('tiempo')?session()->get('tiempo'):'2000' }}");
            });
        @endif

        //Alerta con confirmacion sweet alert
        let alerta=(form, texto, btnConfirmar = 'Si, Eliminar')=>{
            // Los colores salen de los tokens del tema, asi que
            // el dialogo acompana al modo claro u oscuro.
            let tk = (n, fallback) => (window.Theme ? window.Theme.token(n) : '') || fallback;

            Swal.fire({
                type: 'question',
                title: '¡ADVERTENCIA!',
                text: texto,
                showCancelButton: true,
                cancelButtonColor: tk('color-danger', 'red'),
                cancelButtonText: 'Cancelar',
                confirmButtonText: btnConfirmar,
                confirmButtonColor: tk('color-success', 'green'),
                background: tk('color-surface-elevated', '#fff'),
                color: tk('color-text', '#444444')
            }).then(result=>{
                if(result.value){
                    $("#" + form ).submit();
                }
            });
        }
        // fin alertas

        //Funcion para evitar el doble click
        $(document).on("submit", "form", function(event, messages, deferreds) {
            $(this).find(':submit').attr('disabled', true);
        }).on("afterValidate", "form", function(event, messages, errorAttributes) {
            if (errorAttributes.length > 0) {
                $(this).find(':submit').attr('disabled', false);
            }
        });
        //Fin de la funcion para evitar el doble click

    </script>

</body>
</html>
