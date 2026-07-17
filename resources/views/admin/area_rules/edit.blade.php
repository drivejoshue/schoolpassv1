@extends('layouts.app')

@section('title', 'Editar regla | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Editar regla de acceso')

@section('content')
    <div class="row">
        <div class="col-xl-8">
            <form method="POST" action="{{ route('admin.area-rules.update', $ruleRow->id) }}" class="card">
                @csrf
                @method('PUT')

                <div class="card-header">
                    <h3 class="card-title">Datos de la regla</h3>
                </div>

                <div class="card-body">
                    @include('admin.area_rules.partials.form', ['ruleRow' => $ruleRow])
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('admin.area-rules.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                    <button class="btn btn-primary">
                        Actualizar regla
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection