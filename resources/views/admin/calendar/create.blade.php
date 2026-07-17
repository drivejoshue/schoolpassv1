@extends('layouts.app')

@section('title', 'Agregar fecha especial | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Agregar fecha especial o periodo')

@section('content')
    <div class="row">
        <div class="col-xl-8">
            <form method="POST" action="{{ route('admin.calendar.store') }}" class="card">
                @csrf

                <div class="card-header">
                    <div>
                        <h3 class="card-title">Fecha especial o periodo</h3>
                        <p class="card-subtitle">
                            Sirve para vacaciones, suspensiones, festivos, consejo técnico, eventos o exámenes.
                        </p>
                    </div>
                </div>

                <div class="card-body">
                    @include('admin.calendar.partials.form', [
                        'dayRow' => null,
                        'isEdit' => false,
                    ])
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('admin.calendar.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                    <button class="btn btn-primary">
                        Guardar fecha especial
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection