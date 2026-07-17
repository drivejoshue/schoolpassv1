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

    <title>Acceso temporalmente bloqueado · SchoolPass</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>

<body>
<div
    class="min-vh-100 d-flex align-items-center
           justify-content-center p-4"
>
    <div
        class="card shadow-sm"
        style="max-width: 36rem; width: 100%;"
    >
        <div class="card-body text-center p-5">
            <span
                class="avatar avatar-xl
                       bg-red-lt text-red mb-4"
            >
                <i class="ti ti-lock fs-1"></i>
            </span>

            <h1 class="h2">
                Acceso temporalmente bloqueado
            </h1>

            <p class="text-secondary mt-3">
                {{ $state['message'] }}
            </p>

            @if ($state['plan_name'])
                <div class="mt-4">
                    <div class="subheader">
                        Plan
                    </div>

                    <strong>
                        {{ $state['plan_name'] }}
                    </strong>
                </div>
            @endif

            @if ($state['expires_at'])
                <div class="mt-3">
                    <div class="subheader">
                        Vencimiento
                    </div>

                    <strong>
                        {{ \Illuminate\Support\Carbon::parse(
                            $state['expires_at']
                        )->format('d/m/Y') }}
                    </strong>
                </div>
            @endif

            <div class="alert alert-info mt-4 text-start">
                Contacta al administrador de tu institución para revisar
                la renovación de SchoolPass.
            </div>

            <form
                method="POST"
                action="{{ route('logout') }}"
                class="mt-4"
            >
                @csrf

                <button
                    type="submit"
                    class="btn btn-outline-secondary"
                >
                    <i class="ti ti-logout me-2"></i>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>