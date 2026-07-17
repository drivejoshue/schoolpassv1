<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>
        @yield('title', 'Acceso') · SchoolPass
    </title>

    @include('partials.theme-bootstrap')

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])

    <style>
        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
            background:
                radial-gradient(
                    circle at top left,
                    rgba(37, 99, 235, .20),
                    transparent 32rem
                ),
                linear-gradient(
                    145deg,
                    #081426 0%,
                    #10213a 54%,
                    #16345c 100%
                );
        }

        .sp-auth-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns:
                minmax(0, 1.05fr)
                minmax(28rem, .95fr);
        }

        .sp-auth-intro {
            min-height: 100vh;
            padding: 4rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        .sp-auth-intro-inner {
            width: 100%;
            max-width: 39rem;
        }

        .sp-auth-logo {
            width: 6.5rem;
            height: 6.5rem;
            object-fit: contain;
            display: block;
            margin-bottom: 2rem;
            filter:
                drop-shadow(
                    0 1rem 2rem rgba(0, 0, 0, .20)
                );
        }

        .sp-auth-title {
            font-size: clamp(2.35rem, 4vw, 4.6rem);
            font-weight: 750;
            line-height: 1;
            letter-spacing: -.045em;
            margin: 0;
        }

        .sp-auth-description {
            max-width: 34rem;
            margin-top: 1.4rem;
            color: rgba(255, 255, 255, .73);
            font-size: 1.05rem;
            line-height: 1.75;
        }

        .sp-auth-benefits {
            margin-top: 2.25rem;
            display: grid;
            gap: .9rem;
        }

        .sp-auth-benefit {
            display: flex;
            align-items: center;
            gap: .8rem;
            color: rgba(255, 255, 255, .87);
        }

        .sp-auth-benefit-icon {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: .75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, .10);
            border: 1px solid rgba(255, 255, 255, .12);
        }

        .sp-auth-panel {
            min-height: 100vh;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(247, 249, 253, .98);
            border-left: 1px solid rgba(255, 255, 255, .10);
        }

        [data-bs-theme="dark"] .sp-auth-panel {
            background: rgba(16, 24, 39, .98);
        }

        .sp-auth-card {
            width: 100%;
            max-width: 31rem;
        }

        .sp-auth-card .card {
            border: 1px solid var(--tblr-border-color);
            border-radius: 1.15rem;
            box-shadow: 0 1.5rem 4rem rgba(15, 23, 42, .12);
        }

        .sp-auth-mobile-brand {
            display: none;
        }

        @media (max-width: 991.98px) {
            .sp-auth-shell {
                display: block;
            }

            .sp-auth-intro {
                display: none;
            }

            .sp-auth-panel {
                min-height: 100vh;
                padding: 1.25rem;
                border-left: 0;
            }

            .sp-auth-mobile-brand {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: .85rem;
                margin-bottom: 1.5rem;
            }

            .sp-auth-mobile-brand img {
                width: 4rem;
                height: 4rem;
                object-fit: contain;
            }
        }
    </style>

    @stack('styles')
</head>

<body>
<div class="sp-auth-shell">

    <section class="sp-auth-intro">
        <div class="sp-auth-intro-inner">
            <img
                src="{{ asset('images/logo.png') }}"
                alt="SchoolPass"
                class="sp-auth-logo"
            >

            <div class="text-uppercase small fw-bold opacity-75 mb-3">
                Plataforma institucional
            </div>

            <h1 class="sp-auth-title">
                SchoolPass
            </h1>

            <p class="sp-auth-description">
                Control de acceso, asistencia y comunicación
                escolar desde una plataforma centralizada,
                segura y preparada para cada institución.
            </p>

            <div class="sp-auth-benefits">
                <div class="sp-auth-benefit">
                    <span class="sp-auth-benefit-icon">
                        <i class="ti ti-shield-check"></i>
                    </span>

                    <span>
                        Acceso protegido por roles institucionales
                    </span>
                </div>

                <div class="sp-auth-benefit">
                    <span class="sp-auth-benefit-icon">
                        <i class="ti ti-scan"></i>
                    </span>

                    <span>
                        Registro de entradas y salidas en tiempo real
                    </span>
                </div>

                <div class="sp-auth-benefit">
                    <span class="sp-auth-benefit-icon">
                        <i class="ti ti-building-community"></i>
                    </span>

                    <span>
                        Administración independiente por escuela
                    </span>
                </div>
            </div>
        </div>
    </section>

    <section class="sp-auth-panel">
        <div class="sp-auth-card">
            <div class="sp-auth-mobile-brand">
                <img
                    src="{{ asset('images/logo.png') }}"
                    alt="SchoolPass"
                >

                <div>
                    <div class="fw-bold fs-2">
                        SchoolPass
                    </div>

                    <div class="text-secondary">
                        Control de acceso escolar
                    </div>
                </div>
            </div>

            @yield('content')

            <div class="text-center text-secondary small mt-4">
                © {{ date('Y') }} SchoolPass
                · Acceso institucional protegido
            </div>
        </div>
    </section>

</div>

@stack('scripts')
</body>
</html>