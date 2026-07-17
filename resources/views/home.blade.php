<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SchoolPass</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-white">
    <main class="mx-auto flex min-h-screen max-w-6xl flex-col justify-center px-6 py-10">
        <section class="mb-10">
            <p class="mb-3 text-sm font-semibold uppercase tracking-[0.3em] text-blue-400">
                Control de acceso escolar
            </p>

            <h1 class="text-5xl font-bold tracking-tight">
                SchoolPass
            </h1>

            <p class="mt-4 max-w-2xl text-lg text-slate-300">
                Plataforma para asistencia, credenciales QR/NFC, áreas restringidas,
                kioscos, dispositivos autorizados y reportes escolares.
            </p>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <a href="{{ route('admin.dashboard') }}" class="rounded-2xl bg-white/10 p-5 transition hover:bg-white/15">
                <h2 class="text-xl font-semibold">Dirección</h2>
                <p class="mt-2 text-sm text-slate-300">
                    Dashboard y administración escolar.
                </p>
            </a>

            <a href="{{ route('prefect.access') }}" class="rounded-2xl bg-white/10 p-5 transition hover:bg-white/15">
                <h2 class="text-xl font-semibold">Prefectura</h2>
                <p class="mt-2 text-sm text-slate-300">
                    Escaneo y control de acceso.
                </p>
            </a>

            <a href="{{ route('guardian.home') }}" class="rounded-2xl bg-white/10 p-5 transition hover:bg-white/15">
                <h2 class="text-xl font-semibold">Padres</h2>
                <p class="mt-2 text-sm text-slate-300">
                    Historial y notificaciones.
                </p>
            </a>

            <a href="{{ route('student.home') }}" class="rounded-2xl bg-white/10 p-5 transition hover:bg-white/15">
                <h2 class="text-xl font-semibold">Alumno</h2>
                <p class="mt-2 text-sm text-slate-300">
                    Credencial y asistencia.
                </p>
            </a>

            <a href="{{ route('kiosk.access') }}" class="rounded-2xl bg-blue-600 p-5 transition hover:bg-blue-500">
                <h2 class="text-xl font-semibold">Kiosco</h2>
                <p class="mt-2 text-sm text-blue-100">
                    Punto de acceso autorizado.
                </p>
            </a>
        </section>

        <form method="POST" action="{{ route('logout') }}" class="mt-10">
            @csrf
            <button class="rounded-xl bg-white/10 px-4 py-2 text-sm hover:bg-white/15">
                Cerrar sesión
            </button>
        </form>
    </main>
</body>
</html>