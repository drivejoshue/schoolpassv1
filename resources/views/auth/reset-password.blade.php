@extends('layouts.auth')

@section('title', 'Nueva contraseña')

@section('content')
<div class="card">
    <div class="card-body p-4 p-md-5">

        <div class="mb-4">
            <div class="text-uppercase text-primary small fw-bold mb-2">
                Seguridad de la cuenta
            </div>

            <h2 class="card-title h1 mb-2">
                Crear nueva contraseña
            </h2>

            <p class="text-secondary mb-0">
                La nueva contraseña debe tener al menos
                ocho caracteres, letras y números.
            </p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <i class="ti ti-alert-circle me-2"></i>
                {{ $errors->first() }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('password.update') }}"
        >
            @csrf

            <input
                type="hidden"
                name="token"
                value="{{ $token }}"
            >

            <div class="mb-3">
                <label class="form-label">
                    Correo electrónico
                </label>

                <input
                    type="email"
                    name="email"
                    value="{{ old('email', $email) }}"
                    required
                    readonly
                    class="form-control form-control-lg"
                >
            </div>

            <div class="mb-3">
                <label class="form-label">
                    Nueva contraseña
                </label>

                <input
                    type="password"
                    name="password"
                    required
                    autofocus
                    autocomplete="new-password"
                    class="form-control form-control-lg"
                >
            </div>

            <div class="mb-4">
                <label class="form-label">
                    Confirmar contraseña
                </label>

                <input
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="form-control form-control-lg"
                >
            </div>

            <button
                type="submit"
                class="btn btn-primary btn-lg w-100"
            >
                <i class="ti ti-lock-check me-2"></i>
                Guardar nueva contraseña
            </button>
        </form>
    </div>
</div>
@endsection