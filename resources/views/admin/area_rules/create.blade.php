@extends('layouts.app')

@section('title', 'Nueva regla | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Nueva regla de acceso')

@section('content')
    <div class="row">
        <div class="col-xl-8">
            <form method="POST" action="{{ route('admin.area-rules.store') }}" class="card">
                @csrf

                <div class="card-header">
                    <h3 class="card-title">Datos de la regla</h3>
                </div>

                <div class="card-body">
                    @include('admin.area_rules.partials.form', ['ruleRow' => null])
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('admin.area-rules.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                    <button class="btn btn-primary">
                        Guardar regla
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection