@extends('layouts.sysadmin')

@section('title', 'Nueva escuela')
@section('page_title', 'Nueva escuela')

@section('content')
<div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">
                <a
                    href="{{ route('sysadmin.schools.index') }}"
                    class="text-secondary text-decoration-none"
                >
                    <i class="ti ti-arrow-left me-1"></i>
                    Escuelas
                </a>
            </div>

            <h2 class="page-title">Crear escuela</h2>

            <div class="text-secondary mt-1">
                Registra la institución y su primer administrador.
                Después podrás asignar plan y configurar las apps.
            </div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('sysadmin.schools.store') }}">
    @csrf

    @include('sysadmin.schools.partials.initial-admin')
    @include('sysadmin.schools.partials.form')
</form>
@endsection
