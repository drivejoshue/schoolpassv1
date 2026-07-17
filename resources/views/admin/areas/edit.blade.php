@extends('layouts.app')

@section('title', 'Editar área | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Editar área')

@section('content')
    <div class="row">
        <div class="col-lg-8 col-xl-7">
            <form method="POST" action="{{ route('admin.areas.update', $areaRow->id) }}" class="card">
                @csrf
                @method('PUT')

                <div class="card-header">
                    <h3 class="card-title">Datos del área</h3>
                </div>

                <div class="card-body">
                    @include('admin.areas.partials.form', ['areaRow' => $areaRow])
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('admin.areas.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                    <button class="btn btn-primary">
                        Actualizar área
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection