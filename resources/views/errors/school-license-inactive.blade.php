<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Licencia no disponible · SchoolPass</title>
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            background: #07111f;
            color: #e8f0f8;
            font-family: system-ui, sans-serif;
        }
        main {
            width: min(620px, calc(100% - 32px));
            padding: 28px;
            border: 1px solid #203752;
            border-radius: 18px;
            background: #0c1a2d;
        }
        p { color: #91a4b8; line-height: 1.6; }
        a, button {
            display: inline-block;
            border: 0;
            border-radius: 10px;
            padding: 10px 14px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<body>
<main>
    <h1>Licencia no disponible</h1>
    <p>
        El acceso administrativo está restringido porque la escuela no cuenta
        con una licencia vigente, se encuentra suspendida o terminó su periodo
        de gracia.
    </p>

    @if ($license)
        <p>
            Estado registrado: <strong>{{ $license->status }}</strong>.
            @if ($license->expires_at)
                Vencimiento: <strong>{{ $license->expires_at->format('d/m/Y') }}</strong>.
            @endif
        </p>
    @endif

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Cerrar sesión</button>
    </form>
</main>
</body>
</html>
