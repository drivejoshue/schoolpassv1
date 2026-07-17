@extends('layouts.auth')

@section('title', 'Recuperar contraseña')

@section('content')
<div class="card">
    <div class="card-body p-4 p-md-5">

        <a
            href="{{ route('login') }}"
            class="text-secondary text-decoration-none"
        >
            <i class="ti ti-arrow-left me-1"></i>
            Regresar al acceso
        </a>

        <div class="mt-4 mb-4">
            <div class="text-uppercase text-primary small fw-bold mb-2">
                Recuperación administrativa
            </div>

            <h2 class="card-title h1 mb-2">
                Recuperar contraseña
            </h2>

            <p class="text-secondary mb-0">
                Ingresa el correo asociado a tu cuenta.
                Te enviaremos un enlace temporal para
                establecer una nueva contraseña.
            </p>
        </div>

        @if(session('status'))
            <div class="alert alert-success">
                <i class="ti ti-mail-check me-2"></i>
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <i class="ti ti-alert-circle me-2"></i>
                {{ $errors->first() }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('password.email') }}"
        >
            @csrf

            <div class="mb-4">
                <label class="form-label">
                    Correo administrativo
                </label>

                <div class="input-icon">
                    <span class="input-icon-addon">
                        <i class="ti ti-mail"></i>
                    </span>

                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="email"
                        class="form-control form-control-lg"
                        placeholder="direccion@institucion.edu.mx"
                    >
                </div>
            </div>

            <button
                type="submit"
                class="btn btn-primary btn-lg w-100"
            >
                <i class="ti ti-send me-2"></i>
                Enviar enlace de recuperación
            </button>
        </form>

        <div class="alert alert-info mt-4 mb-0">
            <i class="ti ti-shield-lock me-2"></i>
            Por seguridad, el sistema no confirma
            públicamente si un correo está registrado.
        </div>
    </div>
</div>
@endsection