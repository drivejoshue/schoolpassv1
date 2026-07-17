@extends('layouts.auth')

@section('title', 'Iniciar sesión')

@section('content')
<div class="card">
    <div class="card-body p-4 p-md-5">

        <div class="mb-4">
            <div class="text-uppercase text-primary small fw-bold mb-2">
                Acceso institucional
            </div>

            <h2 class="card-title h1 mb-2">
                Iniciar sesión
            </h2>

            <p class="text-secondary mb-0">
                Ingresa con las credenciales asignadas
                por tu institución.
            </p>
        </div>

        @if(session('status'))
            <div
                class="alert alert-success"
                role="alert"
            >
                <i class="ti ti-circle-check me-2"></i>
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div
                class="alert alert-danger"
                role="alert"
            >
                <i class="ti ti-alert-circle me-2"></i>
                {{ $errors->first() }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('login.store') }}"
            autocomplete="on"
        >
            @csrf

            <div class="mb-3">
                <label class="form-label">
                    Correo electrónico
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
                        autocomplete="username"
                        class="form-control form-control-lg
                            @error('email') is-invalid @enderror"
                        placeholder="nombre@institucion.edu.mx"
                    >
                </div>

                @error('email')
                    <div class="invalid-feedback d-block">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div class="mb-2">
                <div class="d-flex justify-content-between">
                    <label class="form-label">
                        Contraseña
                    </label>

                    <a
                        href="{{ route('password.request') }}"
                        class="small text-decoration-none"
                    >
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>

                <div class="input-icon">
                    <span class="input-icon-addon">
                        <i class="ti ti-lock"></i>
                    </span>

                    <input
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="form-control form-control-lg
                            @error('password') is-invalid @enderror"
                        placeholder="Escribe tu contraseña"
                    >
                </div>

                @error('password')
                    <div class="invalid-feedback d-block">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div class="mb-4 mt-3">
                <label class="form-check">
                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                        class="form-check-input"
                    >

                    <span class="form-check-label">
                        Mantener sesión iniciada en este equipo
                    </span>
                </label>
            </div>

            <button
                type="submit"
                class="btn btn-primary btn-lg w-100"
            >
                <i class="ti ti-login me-2"></i>
                Entrar a SchoolPass
            </button>
        </form>

        <div class="text-secondary small mt-4">
            <i class="ti ti-info-circle me-1"></i>
            El acceso está reservado al personal,
            estudiantes y familias autorizadas.
        </div>
    </div>
</div>

@if(app()->environment('local'))
    <div class="card mt-3">
        <div class="card-body py-3">
            <div class="fw-semibold mb-2">
                <i class="ti ti-code me-1"></i>
                Accesos de desarrollo
            </div>

            <div class="small text-secondary">
                <div>director@demo.test</div>
                <div>prefecto@demo.test</div>
                <div>kiosco@demo.test</div>
                <div class="mt-2">
                    Contraseña:
                    <strong>12345678</strong>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection