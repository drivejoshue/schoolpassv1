<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'SchoolPass')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body>
      @include('partials.support-impersonation-bar')
      @include('partials.license-warning')
    <div class="page">
        @include('layouts.partials.sidebar')

        @include('layouts.partials.topbar')

        <div class="page-wrapper">
            <div class="page-body sp-page-body">
                <div class="container-fluid sp-container">
                    @yield('content')
                </div>
            </div>

            @include('layouts.partials.footer')
        </div>
    </div>

    @stack('scripts')
</body>
</html>